import json
import os
import signal
import subprocess
import sys
import time
import unittest
from pathlib import Path

from scripts.tests import test_batch_runtime as fixtures


class RunnerTests(unittest.TestCase):
    def setUp(self):
        fixtures.RuntimeTests.setUp(self)
        source = self.fake.read_text().replace("else:\n    outcomes = []", """else:
    event = Path(r['repository']).parent / 'events'
    with event.open('a') as log: log.write(r['batch']['id'] + ' start ' + str(time.time()) + '\\n')
    time.sleep(0.3)
    outcomes = []""")
        source = source.replace("    result = {k: r[k] for k in ('run_id', 'project')}", """    if 'question' in r['batch']['id']:
        ticket = outcomes[-1]
        ticket.clear(); ticket.update(id=r['batch']['tickets'][-1]['id'], status='product_question',
            question='Which behavior?', state='Backlog', triage_issue={'id': 'DRA-99',
            'project': r['project'], 'cause': 'product-question'})
    with event.open('a') as log: log.write(r['batch']['id'] + ' finish ' + str(time.time()) + '\\n')
    result = {k: r[k] for k in ('run_id', 'project')}""")
        source = source.replace("status='complete', tickets=outcomes", "status='partial' if 'question' in r['batch']['id'] else 'complete', tickets=outcomes")
        source = source.replace("ready=a.mode != 'reject'",
                                "ready=a.mode != 'reject' and not (a.mode == 'reject-repeat' and Path(a.result).name.startswith('before-'))")
        self.fake.write_text(source)

    def add_batch(self, number, dependencies=()):
        self.manifest['batches'].append({'id': f'batch-{number}', 'branch': f'feature/dra-{number}-batch',
            'tickets': [{'id': f'DRA-{number}', 'depends_on': list(dependencies)}]})

    def launch(self):
        path = self.home / 'manifest.json'; path.write_text(json.dumps(self.manifest))
        return subprocess.Popen(['bash', 'scripts/run-batches.sh', str(path)],
                                cwd=Path(__file__).resolve().parents[2], stdout=subprocess.PIPE,
                                stderr=subprocess.PIPE, text=True)

    def run_manifest(self):
        process = self.launch(); stdout, stderr = process.communicate(timeout=20)
        summaries = list((self.repo / '.batch-runs').glob('*/summary.json'))
        summary = json.loads(summaries[-1].read_text()) if summaries else None
        return process.returncode, summary, stdout + stderr

    def test_independent_batches_overlap_with_distinct_worktrees_and_databases(self):
        self.add_batch(3); self.add_batch(4)
        code, summary, output = self.run_manifest()
        self.assertEqual(code, 0, output)
        outcomes = list(summary['batches'].values())
        self.assertEqual(len({item['worktree'] for item in outcomes}), 3)
        self.assertEqual((Path(summary['batches']['batch-3']['worktree']) / 'DRA-3').read_text(), 'testing_dra3')
        events = [line.split() for line in (self.home / 'events').read_text().splitlines()]
        starts = [float(row[2]) for row in events if row[1] == 'start']
        finishes = [float(row[2]) for row in events if row[1] == 'finish']
        self.assertLess(max(starts), min(finishes))

    def test_dependency_waits_for_delivery_and_stacks_on_parent_branch(self):
        self.add_batch(3, ['DRA-2'])
        code, summary, output = self.run_manifest()
        self.assertEqual(code, 0, output)
        child = Path(summary['batches']['batch-3']['worktree'])
        self.assertTrue((child / 'DRA-1').exists())
        request = json.loads(next((self.repo / '.batch-runs').glob('*/batch-3/request.json')).read_text())
        self.assertEqual(request['base'], 'feature/dra-1-first')
        self.assertEqual(summary['batches']['batch-3']['delivery']['base'], request['base'])

    def test_partial_question_stops_its_dependents_but_delivered_ticket_dependency_proceeds(self):
        self.manifest['batches'][0]['id'] = 'first-question'
        self.add_batch(3, ['DRA-2']); self.add_batch(4, ['DRA-1']); self.add_batch(5)
        code, summary, output = self.run_manifest()
        self.assertEqual(code, 1, output)
        self.assertEqual(summary['batches']['batch-3']['status'], 'blocked')
        self.assertEqual(summary['batches']['batch-4']['status'], 'complete')
        self.assertEqual(summary['batches']['batch-5']['status'], 'complete')
        self.assertFalse(any((self.home / 'worktrees').glob('*-batch-3')))

    def test_failed_parent_blocks_child_and_invalid_manifest_starts_nothing(self):
        self.add_batch(3, ['DRA-2'])
        self.manifest['adapter'] += ['--mode', 'exit']
        code, summary, output = self.run_manifest()
        self.assertEqual(code, 1, output)
        self.assertEqual(summary['batches']['batch-3']['status'], 'blocked')
        self.manifest['project'] = ''
        process = self.launch(); stdout, stderr = process.communicate(timeout=5)
        self.assertEqual(process.returncode, 2, stdout + stderr)
        self.assertEqual(len(list((self.repo / '.batch-runs').iterdir())), 1)

    def test_sigterm_preserves_summary_and_reaps_children(self):
        self.manifest['adapter'] += ['--mode', 'sleep']
        process = self.launch()
        deadline = time.monotonic() + 5
        while not list((self.repo / '.batch-runs').glob('*/first/result.json.pid')):
            self.assertLess(time.monotonic(), deadline)
            time.sleep(0.05)
        process.send_signal(signal.SIGTERM)
        stdout, stderr = process.communicate(timeout=5)
        self.assertEqual(process.returncode, 130, stdout + stderr)
        summary = json.loads(next((self.repo / '.batch-runs').glob('*/summary.json')).read_text())
        self.assertEqual(summary['status'], 'cancelled')
        pid = int(next((self.repo / '.batch-runs').glob('*/first/result.json.pid')).read_text())
        with self.assertRaises(ProcessLookupError): os.kill(pid, 0)

    def test_literal_adapter_arguments_are_never_interpreted_by_a_shell(self):
        self.manifest['adapter'] += ['--mode', 'ok;$(touch shell-marker)']
        code, summary, output = self.run_manifest()
        self.assertEqual(code, 0, output)
        self.assertFalse((Path(summary['batches']['first']['worktree']) / 'shell-marker').exists())

    def test_child_rechecks_live_parent_delivery_before_starting(self):
        self.add_batch(3, ['DRA-1'])
        self.manifest['verifier'] += ['--mode', 'reject-repeat']
        code, summary, output = self.run_manifest()
        self.assertEqual(code, 1, output)
        self.assertEqual(summary['batches']['first']['status'], 'complete')
        self.assertEqual(summary['batches']['batch-3']['status'], 'failed')
        self.assertFalse(any((self.home / 'worktrees').glob('*-batch-3')))
