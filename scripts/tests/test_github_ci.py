import copy
import unittest

from scripts.adapters.github_ci import ProofError, assert_green, prove_skipped


class GithubCIProofTests(unittest.TestCase):
    def setUp(self):
        self.head = 'a' * 40
        self.check = {'id': 1, 'name': 'ci', 'app': {'id': 1}, 'check_suite': {'id': 10},
                      'head_sha': self.head, 'status': 'completed', 'conclusion': 'success'}

    def test_unrelated_green_cannot_hide_a_missing_required_job(self):
        with self.assertRaises(ProofError):
            assert_green([self.check], [], ['1/ci', '1/e2e'], [], self.head)
        second = copy.deepcopy(self.check); second.update(id=2, name='e2e')
        assert_green([self.check, second], [], ['1/ci', '1/e2e'], [], self.head)
        with self.assertRaises(ProofError):
            assert_green([self.check], [], [], [], self.head)

    def test_independent_suite_failure_survives_another_suite_success(self):
        failed = copy.deepcopy(self.check); failed['conclusion'] = 'failure'
        other = copy.deepcopy(self.check); other.update(id=2, check_suite={'id': 20})
        with self.assertRaises(ProofError):
            assert_green([failed, other], [], ['1/ci'], [], self.head)
        retry = copy.deepcopy(self.check); retry['id'] = 3
        assert_green([failed, retry], [], ['1/ci'], [], self.head)

    def test_status_only_requires_every_configured_context(self):
        statuses = [{'id': 1, 'context': 'ci', 'state': 'success'}]
        assert_green([], statuses, [], ['ci'], self.head)
        with self.assertRaises(ProofError):
            assert_green([], statuses, [], ['ci', 'e2e'], self.head)
        statuses.append({'id': 2, 'context': 'ci', 'state': 'pending'})
        with self.assertRaises(ProofError):
            assert_green([], statuses, [], ['ci'], self.head)

    def test_skip_proof_requires_push_branch_exclusion_and_pr_paths(self):
        source = "on:\n  push:\n    branches:\n      - main\n  pull_request:\n    paths-ignore:\n      - 'docs/**'\njobs: {}\n"
        prove_skipped(source, {'docs/ticket.md'}, 'feature/dra-1-first')
        prove_skipped(source.replace('    branches:\n      - main', '    branches: [main]'),
                      {'docs/ticket.md'}, 'feature/dra-1-first')
        for bad_source, paths, branch in (
            (source.replace('    branches:\n      - main\n', ''), {'docs/ticket.md'}, 'feature/dra-1-first'),
            (source, {'docs/ticket.md'}, 'main'),
            (source, {'docs/ticket.md', 'app/renamed.php'}, 'feature/dra-1-first'),
            (source.replace("'docs/**'", "'!docs/**'"), {'docs/ticket.md'}, 'feature/dra-1-first'),
            (source + "'on': [push]\n", {'docs/ticket.md'}, 'feature/dra-1-first'),
        ):
            with self.subTest(source=bad_source, paths=paths, branch=branch), self.assertRaises(ProofError):
                prove_skipped(bad_source, paths, branch)
