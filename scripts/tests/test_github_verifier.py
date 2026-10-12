import copy
import json
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path


class GithubVerifierTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(); self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name); self.repo = self.root / 'repo'; self.repo.mkdir()
        self.git('init', '-b', 'main'); self.git('config', 'user.name', 'Test')
        self.git('config', 'user.email', 'test@example.com')
        workflow = self.repo / '.github/workflows/ci.yml'; workflow.parent.mkdir(parents=True)
        workflow.write_text("on:\n  pull_request:\n    paths-ignore:\n      - 'docs/**'\njobs: {}\n")
        self.git('add', '.'); self.git('commit', '-m', 'base')
        self.base = self.git('rev-parse', 'HEAD')
        self.git('checkout', '-b', 'feature/dra-1-first')
        (self.repo / 'docs').mkdir(); (self.repo / 'docs/ticket.md').write_text('ticket')
        self.git('add', '.'); self.git('commit', '-m', 'feat(test): deliver ticket (DRA-1)')
        self.head = self.git('rev-parse', 'HEAD')
        self.git('remote', 'add', 'origin', 'git@github.com:example/repo.git')
        self.pr = {'number': 1, 'html_url': 'https://github.com/example/repo/pull/1', 'state': 'open',
                   'draft': False, 'head': {'sha': self.head, 'ref': 'feature/dra-1-first',
                   'repo': {'full_name': 'example/repo'}}, 'base': {'sha': self.base, 'ref': 'main',
                   'repo': {'full_name': 'example/repo'}}, 'commits': 1, 'changed_files': 1,
                   'updated_at': '2000-01-01T00:00:00Z'}
        self.responses = {
            'pulls?state=open&per_page=100': [[self.pr]], 'pulls/1': self.pr,
            'pulls/1/commits?per_page=100': [[{'sha': self.head, 'commit': {
                'message': 'feat(test): deliver ticket (DRA-1)'}}]],
            f'commits/{self.head}/check-runs?filter=all&per_page=100': [{'total_count': 1, 'check_runs': [
                {'id': 1, 'name': 'ci', 'app': {'id': 1}, 'check_suite': {'id': 10}, 'head_sha': self.head,
                 'status': 'completed', 'conclusion': 'success'}]}],
            f'commits/{self.head}/statuses?per_page=100': [[]],
            'pulls/1/files?per_page=100': [[{'filename': 'docs/ticket.md'}]],
        }
        self.config = self.root / 'responses.json'
        self.gh = self.root / 'gh'; self.gh.write_text("#!/usr/bin/env python3\nimport json, sys\nfrom pathlib import Path\nassert sys.argv[1] == 'api'\nassert sys.argv[sys.argv.index('--method') + 1] == 'GET'\nassert sys.argv[sys.argv.index('--hostname') + 1] == 'github.com'\nendpoint = sys.argv[2].removeprefix('repos/example/repo/')\nif '?' in endpoint:\n    assert '--paginate' in sys.argv and '--slurp' in sys.argv\nconfig = json.loads(Path(__file__).with_name('responses.json').read_text())\nprint(json.dumps(config[endpoint]))\n"); self.gh.chmod(0o755)
        self.script = Path(__file__).resolve().parents[1] / 'adapters/github_verifier.py'

    def git(self, *args):
        return subprocess.check_output(['git', *args], cwd=self.repo, text=True,
                                       stderr=subprocess.DEVNULL).strip()

    def call(self, kind='verify_delivery', options=(), required=('1/ci',), required_statuses=(), **changes):
        self.config.write_text(json.dumps(self.responses))
        request = {'version': 1, 'kind': kind, 'run_id': 'run', 'project': 'P-DRA-2'}
        if kind == 'repository_preflight': request.update(repository=str(self.repo), cross_cutting=False)
        else: request.update(batch_id='first', branch='feature/dra-1-first', tickets=['DRA-1'],
            worktree=str(self.repo), delivery={'pr': self.pr['html_url'], 'head': self.head,
            'base': 'main', 'state': 'open', 'ci': 'green'})
        request.update(changes)
        request_path, result_path = self.root / 'request.json', self.root / 'result.json'
        result_path.unlink(missing_ok=True); request_path.write_text(json.dumps(request))
        options = list(options)
        for check in required: options += ['--required-check', check]
        for context in required_statuses: options += ['--required-status', context]
        run = subprocess.run([sys.executable, str(self.script), '--repository', 'example/repo',
            '--gh', str(self.gh), '--request', str(request_path), '--result', str(result_path), *options],
            capture_output=True, text=True)
        return run, json.loads(result_path.read_text()) if result_path.exists() else None

    def test_green_delivery_has_exact_identity_and_ticket_coverage(self):
        run, receipt = self.call()
        self.assertEqual(run.returncode, 0, run.stderr)
        self.assertTrue(receipt['ready']); self.assertEqual(receipt['tickets'], ['DRA-1'])
        for changes in ({'tickets': ['DRA-2']}, {'branch': 'other'}):
            with self.subTest(changes=changes): self.assertNotEqual(self.call(**changes)[0].returncode, 0)

    def test_paginated_preflight_requires_head_pinned_classification(self):
        second = copy.deepcopy(self.pr); second.update(number=2, html_url='https://github.com/example/repo/pull/2')
        self.responses['pulls?state=open&per_page=100'] = [[self.pr], [second]]
        self.assertNotEqual(self.call('repository_preflight')[0].returncode, 0)
        options = ['--ordinary-pr', f'1@{self.head}', '--cross-cutting-pr', f'2@{self.head}']
        run, receipt = self.call('repository_preflight', options=options)
        self.assertEqual(run.returncode, 0, run.stderr)
        self.assertTrue(receipt['active_cross_cutting']); self.assertEqual(len(receipt['open_prs']), 2)
        second['head']['sha'] = 'a' * 40
        self.assertNotEqual(self.call('repository_preflight', options=options)[0].returncode, 0)

    def test_wrong_repository_draft_head_and_base_are_rejected(self):
        for key, value in (('draft', True), ('state', 'closed'), ('html_url', 'https://github.com/other/repo/pull/1')):
            original = self.pr[key]; self.pr[key] = value
            self.assertNotEqual(self.call()[0].returncode, 0); self.pr[key] = original
        for field in ('head', 'base'):
            original = self.pr[field]['ref']; self.pr[field]['ref'] = 'wrong'
            self.assertNotEqual(self.call()[0].returncode, 0); self.pr[field]['ref'] = original
        self.git('remote', 'set-url', 'origin', 'https://github.com/other/repo.git')
        self.assertNotEqual(self.call()[0].returncode, 0)

    def test_pending_failed_stale_and_mixed_checks_never_pass(self):
        checks = self.responses[f'commits/{self.head}/check-runs?filter=all&per_page=100'][0]['check_runs']
        for change in ({'status': 'in_progress'}, {'conclusion': 'failure'}, {'head_sha': 'a' * 40}):
            original = dict(checks[0]); checks[0].update(change)
            self.assertNotEqual(self.call()[0].returncode, 0); checks[0] = original
        self.responses[f'commits/{self.head}/statuses?per_page=100'] = [[{'id': 1, 'context': 'other', 'state': 'pending'}]]
        self.assertNotEqual(self.call()[0].returncode, 0)
        checks.clear()
        self.responses[f'commits/{self.head}/check-runs?filter=all&per_page=100'][0]['total_count'] = 0
        self.responses[f'commits/{self.head}/statuses?per_page=100'][0][0]['state'] = 'success'
        self.assertEqual(self.call(required=(), required_statuses=['other'])[0].returncode, 0)

    def test_no_checks_require_trusted_ignored_paths_including_renames(self):
        self.responses[f'commits/{self.head}/check-runs?filter=all&per_page=100'] = [{'total_count': 0, 'check_runs': []}]
        self.assertNotEqual(self.call()[0].returncode, 0)
        delivery = {'pr': self.pr['html_url'], 'head': self.head, 'base': 'main', 'state': 'open', 'ci': 'not_required'}
        self.assertIn('required check has not appeared', self.call(delivery=delivery)[0].stderr)
        self.responses['pulls/1/files?per_page=100'][0][0]['previous_filename'] = 'app/old.php'
        self.assertNotEqual(self.call(delivery=delivery)[0].returncode, 0)
        self.responses['pulls/1/files?per_page=100'][0][0].pop('previous_filename')
        self.pr['updated_at'] = '2999-01-01T00:00:00Z'
        self.assertNotEqual(self.call(delivery=delivery)[0].returncode, 0)

    def test_truncated_pages_and_local_head_changes_are_rejected(self):
        self.pr['commits'] = 2
        self.assertNotEqual(self.call()[0].returncode, 0)
        self.pr['commits'] = 1
        self.git('commit', '--allow-empty', '-m', 'unverified advance')
        self.assertNotEqual(self.call()[0].returncode, 0)

    def test_no_checks_cannot_trust_modified_or_unfamiliar_workflows(self):
        self.responses[f'commits/{self.head}/check-runs?filter=all&per_page=100'] = [{'total_count': 0, 'check_runs': []}]
        delivery = {'pr': self.pr['html_url'], 'head': self.head, 'base': 'main', 'state': 'open', 'ci': 'not_required'}
        workflow = self.repo / '.github/workflows/ci.yml'
        workflow.write_text("on:\n  pull_request:\n    paths-ignore:\n      - '!docs/**'\njobs: {}\n")
        self.git('add', '.'); self.git('commit', '-m', 'change workflow')
        self.head = self.git('rev-parse', 'HEAD')
        self.pr['head']['sha'] = self.head; delivery['head'] = self.head; self.pr['commits'] = 2
        self.responses[f'commits/{self.head}/check-runs?filter=all&per_page=100'] = [{'total_count': 0, 'check_runs': []}]
        self.responses[f'commits/{self.head}/statuses?per_page=100'] = [[]]
        self.responses['pulls/1/commits?per_page=100'][0].append({'sha': self.head, 'commit': {'message': 'change workflow'}})
        self.assertIn('required check has not appeared', self.call(delivery=delivery)[0].stderr)
        self.pr['base']['sha'] = self.head
        self.assertIn('required check has not appeared', self.call(delivery=delivery)[0].stderr)

    def test_required_jobs_and_independent_suites_cannot_be_hidden(self):
        with self.subTest('missing configuration'):
            self.assertNotEqual(self.call(required=())[0].returncode, 0)
        with self.subTest('missing e2e'):
            self.assertNotEqual(self.call(required=['1/ci', '1/e2e'])[0].returncode, 0)
        checks = self.responses[f'commits/{self.head}/check-runs?filter=all&per_page=100'][0]
        failed = copy.deepcopy(checks['check_runs'][0]); failed['conclusion'] = 'failure'
        other = copy.deepcopy(checks['check_runs'][0]); other.update(id=2, check_suite={'id': 20})
        checks.update(total_count=2, check_runs=[failed, other])
        with self.subTest('independent failed suite'):
            self.assertNotEqual(self.call()[0].returncode, 0)
        checks.update(total_count=2, check_runs=[other])
        with self.subTest('truncated check runs'):
            self.assertNotEqual(self.call()[0].returncode, 0)

    def test_no_checks_cannot_ignore_unrestricted_push(self):
        self.git('checkout', 'main')
        workflow = self.repo / '.github/workflows/ci.yml'
        workflow.write_text("on:\n  push:\n  pull_request:\n    paths-ignore:\n      - 'docs/**'\njobs: {}\n")
        self.git('add', '.'); self.git('commit', '-m', 'base push workflow')
        self.base = self.git('rev-parse', 'HEAD')
        self.git('checkout', 'feature/dra-1-first'); self.git('merge', '--no-edit', 'main')
        self.head = self.git('rev-parse', 'HEAD'); self.pr['head']['sha'] = self.head
        self.pr['base']['sha'] = self.base
        commits = [{'sha': sha, 'commit': {'message': self.git('show', '-s', '--format=%B', sha)}}
                   for sha in self.git('rev-list', self.base + '..HEAD').splitlines()]
        self.pr['commits'] = len(commits); self.responses['pulls/1/commits?per_page=100'] = [commits]
        self.responses[f'commits/{self.head}/check-runs?filter=all&per_page=100'] = [{'total_count': 0, 'check_runs': []}]
        self.responses[f'commits/{self.head}/statuses?per_page=100'] = [[]]
        delivery = {'pr': self.pr['html_url'], 'head': self.head, 'base': 'main', 'state': 'open', 'ci': 'not_required'}
        self.assertIn('required check has not appeared', self.call(delivery=delivery)[0].stderr)

    def test_ignored_paths_cannot_waive_external_or_unbound_required_contexts(self):
        self.responses[f'commits/{self.head}/check-runs?filter=all&per_page=100'] = [{'total_count': 0, 'check_runs': []}]
        delivery = {'pr': self.pr['html_url'], 'head': self.head, 'base': 'main', 'state': 'open', 'ci': 'not_required'}
        for required, statuses in (([], ['external/security']), (['1/ci'], [])):
            with self.subTest(checks=required, statuses=statuses):
                self.assertNotEqual(self.call(delivery=delivery, required=required,
                                             required_statuses=statuses)[0].returncode, 0)
