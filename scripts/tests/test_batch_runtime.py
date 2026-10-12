import json
import os
import signal
import subprocess
import sys
import tempfile
import threading
import time
import unittest
from pathlib import Path
from unittest.mock import patch

from scripts.batch_contract import ContractError, validate_manifest
from scripts.batch_runtime import BatchRuntime


FAKE = r"""
import argparse, json, os, subprocess, time
from pathlib import Path
p = argparse.ArgumentParser()
p.add_argument('--request'); p.add_argument('--result'); p.add_argument('--mode', default='ok')
a = p.parse_args(); r = json.loads(Path(a.request).read_text())
if a.mode == 'sleep':
    Path(a.result + '.pid').write_text(str(os.getpid()))
    time.sleep(30)
if a.mode == 'exit': raise SystemExit(9)
if a.mode == 'malformed': Path(a.result).write_text('invalid'); raise SystemExit()
if r['kind'] == 'repository_preflight':
    result = {'version': 1, 'run_id': r['run_id'], 'project': r['project'],
              'repository': r['repository'], 'active_cross_cutting': a.mode == 'sweep-open',
              'open_prs': ['https://github.com/example/repo/pull/99'] if a.mode == 'prs-open' else []}
elif r['kind'] == 'verify_delivery':
    result = {k: r[k] for k in ('run_id', 'project', 'batch_id', 'branch')}
    result.update(version=1, ready=a.mode != 'reject', tickets=r['tickets'], delivery=r['delivery'])
else:
    outcomes = []
    for ticket in r['batch']['tickets']:
        Path(ticket['id']).write_text(os.environ['DB_DATABASE'])
        subprocess.run(['git', 'add', ticket['id']], check=True)
        subprocess.run(['git', 'commit', '-m', ticket['id']], check=True, capture_output=True)
        sha = subprocess.check_output(['git', 'rev-parse', 'HEAD'], text=True).strip()
        outcomes.append({'id': ticket['id'], 'status': 'delivered', 'commit': sha})
    result = {k: r[k] for k in ('run_id', 'project')}
    result.update(version=1, batch_id=r['batch']['id'], branch=r['batch']['branch'],
                  status='complete', tickets=outcomes, checks='passed', correction_passes=0,
                  reviews={'spec': 'approved', 'correctness': 'approved'},
                  delivery={'pr': 'https://github.com/example/repo/pull/1', 'state': 'open',
                            'ci': 'green', 'head': sha, 'base': r['base']})
Path(a.result).write_text(json.dumps(result))
"""


class RuntimeTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.home = Path(self.temp.name)
        self.repo = self.home / 'repo'; self.repo.mkdir()
        self.env = patch.dict(os.environ, {'HOME': str(self.home)})
        self.env.start(); self.addCleanup(self.env.stop)
        for args in (['init', '-b', 'main'], ['config', 'user.name', 'Test'],
                     ['config', 'user.email', 'test@example.com'], ['commit', '--allow-empty', '-m', 'base']):
            subprocess.run(['git', *args], cwd=self.repo, check=True, capture_output=True)
        self.fake = self.home / 'fake.py'; self.fake.write_text(FAKE)
        self.manifest = {
            'version': 1, 'project': 'P-DRA-2', 'repository': str(self.repo),
            'worktree_root': str(self.home / 'worktrees'), 'base': 'main',
            'adapter': [sys.executable, str(self.fake)], 'verifier': [sys.executable, str(self.fake)],
            'models': {role: 'local-fake' for role in
                       ('orchestrator', 'implementer', 'spec_reviewer', 'correctness_reviewer')},
            'batches': [{'id': 'first', 'branch': 'feature/dra-1-first',
                         'tickets': [{'id': 'DRA-1'}, {'id': 'DRA-2', 'depends_on': ['DRA-1']}]}],
        }

    def runtime(self):
        runtime = BatchRuntime(validate_manifest(self.manifest))
        self.addCleanup(runtime.close)
        return runtime

    def test_two_tickets_create_one_branch_with_distinct_commits_and_database(self):
        runtime = self.runtime(); runtime.preflight()
        outcome = runtime.execute(runtime.manifest['batches'][0], 'main')
        self.assertEqual(outcome['status'], 'complete')
        self.assertNotEqual(outcome['tickets'][0]['commit'], outcome['tickets'][1]['commit'])
        worktree = Path(outcome['worktree'])
        self.assertEqual((worktree / 'DRA-2').read_text(), 'testing_dra1')
        self.assertEqual(subprocess.check_output(['git', 'branch', '--show-current'],
                                                cwd=self.repo, text=True).strip(), 'main')
        self.assertTrue((runtime.state / 'first' / 'request.json').exists())

    def test_nonzero_and_malformed_adapters_preserve_recoverable_worktree(self):
        for mode in ('exit', 'malformed'):
            with self.subTest(mode=mode):
                self.manifest['batches'][0]['branch'] = f'feature/dra-1-{mode}'
                self.manifest['adapter'] = [sys.executable, str(self.fake), '--mode', mode]
                runtime = self.runtime(); runtime.preflight()
                with self.assertRaises(ContractError):
                    runtime.execute(runtime.manifest['batches'][0], 'main')
                self.assertTrue(any((self.home / 'worktrees').iterdir()))
                runtime.close()

    def test_interruption_terminates_and_reaps_adapter(self):
        self.manifest['adapter'] += ['--mode', 'sleep']
        runtime = self.runtime(); runtime.preflight()
        timer = threading.Timer(0.5, runtime.cancelled.set); timer.start()
        try:
            with self.assertRaises(InterruptedError):
                runtime.execute(runtime.manifest['batches'][0], 'main')
        finally:
            timer.cancel()
        pid = int((runtime.state / 'first' / 'result.json.pid').read_text())
        with self.assertRaises(ProcessLookupError): os.kill(pid, 0)
        self.assertTrue((runtime.state / 'first' / 'request.json').exists())

    def test_rejected_receipts_and_repository_sweeps_fail_visibly(self):
        for mode in ('reject', 'sweep-open', 'prs-open'):
            with self.subTest(mode=mode):
                self.manifest['verifier'] = [sys.executable, str(self.fake), '--mode', mode]
                self.manifest['batches'][0]['cross_cutting'] = mode == 'prs-open'
                runtime = self.runtime()
                with self.assertRaises(ContractError):
                    runtime.preflight()
                    runtime.execute(runtime.manifest['batches'][0], 'main')
                runtime.close()
                self.manifest['batches'][0]['branch'] = f'feature/dra-1-after-{mode}'

    def test_old_ancestor_cannot_be_reported_as_a_ticket_commit(self):
        subprocess.run(['git', 'commit', '--allow-empty', '-m', 'new base'], cwd=self.repo,
                       check=True, capture_output=True)
        source = self.fake.read_text().replace("    result = {k: r[k] for k in ('run_id', 'project')}",
            "    outcomes[0]['commit'] = subprocess.check_output(['git', 'rev-parse', 'main^'], text=True).strip()\n"
            "    result = {k: r[k] for k in ('run_id', 'project')}")
        self.fake.write_text(source)
        runtime = self.runtime(); runtime.preflight()
        with self.assertRaises(ContractError):
            runtime.execute(runtime.manifest['batches'][0], 'main')

    def test_close_cannot_release_locks_between_child_spawn_and_registration(self):
        self.manifest['verifier'] += ['--mode', 'sleep']
        runtime = self.runtime()
        original = subprocess.Popen
        created, release, closing = threading.Event(), threading.Event(), threading.Event()
        children, errors = [], []

        def paused_spawn(*args, **kwargs):
            process = original(*args, **kwargs)
            children.append(process); created.set()
            release.wait(timeout=3)
            return process

        def invoke():
            try: runtime.preflight()
            except (ContractError, InterruptedError) as error: errors.append(error)

        def close():
            closing.set(); runtime.close()

        with patch('scripts.batch_runtime.subprocess.Popen', side_effect=paused_spawn):
            worker = threading.Thread(target=invoke); worker.start()
            self.assertTrue(created.wait(timeout=3))
            closer = threading.Thread(target=close); closer.start()
            self.assertTrue(closing.wait(timeout=3))
            closer.join(timeout=0.05)
            was_waiting = closer.is_alive()
            release.set(); closer.join(timeout=3); worker.join(timeout=3)
        self.assertTrue(was_waiting, 'close must retain locks until spawned child is registered/reaped')
        self.assertFalse(closer.is_alive() or worker.is_alive())
        self.assertIsNotNone(children[0].poll())
        self.assertTrue(errors)
        with self.assertRaises(ContractError): runtime.preflight()

    def test_repository_and_database_locks_reject_overlapping_runs(self):
        runtime = self.runtime()
        with self.assertRaises(ContractError): self.runtime()
        runtime.close()
        self.manifest['batches'][0]['cross_cutting'] = True
        sweep = self.runtime()
        self.manifest['batches'][0]['cross_cutting'] = False
        with self.assertRaises(ContractError): self.runtime()
        sweep.close()


if __name__ == '__main__': unittest.main()
