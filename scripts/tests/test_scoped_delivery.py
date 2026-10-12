import json
from dataclasses import replace
import pathlib
import unittest
from unittest.mock import patch
from scripts.scoped_delivery import DeliveryApproval, HostDelivery
from scripts.scoped_git import Refused
from scripts.tests.test_scoped_git import GitFixture


class ScopedDeliveryTests(GitFixture, unittest.TestCase):
    def delivery(self):
        sha = self.host.commit(self.approval)
        self.git('remote', 'add', 'origin', 'https://github.com/dadraude/guixa.git')
        self.host = HostDelivery(self.root, self.repository, pathlib.Path("/usr/bin/git"))
        self.host.gh = '/trusted/gh'
        return DeliveryApproval(self.root, 'feature/worker', sha,
                                self.git('rev-parse', 'HEAD^{tree}'), self.head, 'main', 'Title', 'Body')

    def test_delivery_uses_exact_sha_and_repository(self):
        approval = self.delivery()
        calls = []
        real = self.host._run
        def transport(argv, env=None):
            if argv[0] == '/trusted/gh':
                calls.append(argv)
                if argv[1:3] == ['auth', 'token']:
                    return 'token'
                if argv[1:3] == ['pr', 'list']:
                    return '[]'
                if argv[1:3] == ['pr', 'view']:
                    return json.dumps(dict(headRefOid=approval.head, baseRefName='main', headRefName=approval.branch, isCrossRepository=False))
                return 'https://github.com/dadraude/guixa/pull/1'
            if 'push' in argv:
                calls.append(argv)
                return ''
            return real(argv, env)
        with patch.object(self.host, '_run', side_effect=transport):
            self.assertEqual(self.host.deliver(approval), 'https://github.com/dadraude/guixa/pull/1')
        push = next(c for c in calls if 'push' in c)
        self.assertEqual(push[-2:], ['https://github.com/dadraude/guixa.git',
                                  approval.head + ':refs/heads/feature/worker'])
        create = next(c for c in calls if c[1:3] == ['pr', 'create'])
        self.assertIn('--base', create)
        self.assertIn('main', create)

    def test_worktree_filter_is_refused_before_status(self):
        approval = self.delivery()
        marker = pathlib.Path(self.tmp.name) / 'executed'
        self.git('config', 'extensions.worktreeConfig', 'true')
        self.git('config', '--worktree', 'filter.hostile.clean', 'touch ' + str(marker) + '; cat')
        (self.root / 'scripts/.gitattributes').write_text('*.txt filter=hostile')
        with self.assertRaises(Refused):
            self.host.deliver(approval)
        self.assertFalse(marker.exists())

    def test_changed_head_tree_origin_and_base_refused_before_transport(self):
        approval = self.delivery()
        for changed in (replace(approval, head=self.head),
                        replace(approval, tree='0' * 40),
                        replace(approval, base='0' * 40),
                        replace(approval, base_branch=approval.branch),
                        replace(approval, base_branch='../main')):
            with self.subTest(approval=changed), self.assertRaises(Refused):
                self.host.deliver(changed)
        self.git('remote', 'set-url', 'origin', 'https://github.com/other/repo.git')
        with self.assertRaisesRegex(Refused, 'Origin'):
            self.host.deliver(approval)

    def transport(self, approval, existing=None, create_failure=False, wrong_view=False):
        calls = []
        real = self.host._run
        def run(argv, env=None):
            if 'push' in argv:
                calls.append('push')
                return ''
            if argv[0] != '/trusted/gh':
                return real(argv, env)
            action = argv[1:3]
            calls.append(action)
            if action == ['auth', 'token']:
                return 'token'
            if action == ['pr', 'list']:
                return json.dumps(existing or [])
            if action == ['pr', 'create']:
                if create_failure:
                    raise Refused('creation failed')
                return 'https://github.com/dadraude/guixa/pull/1'
            if action == ['pr', 'view']:
                return json.dumps(dict(headRefOid='0' * 40 if wrong_view else approval.head,
                                       baseRefName='main', headRefName=approval.branch,
                                       isCrossRepository=False))
            raise AssertionError(argv)
        return calls, run

    def test_existing_pr_requires_exact_reviewed_identity(self):
        approval = self.delivery()
        pr = dict(url='https://github.com/dadraude/guixa/pull/1', headRefOid=approval.head,
                  headRepositoryOwner={'login': 'dadraude'}, isCrossRepository=False)
        calls, run = self.transport(approval, [pr])
        with patch.object(self.host, '_run', side_effect=run):
            self.assertEqual(self.host.deliver(approval), pr['url'])
        self.assertIn('push', calls)
        self.assertNotIn(['pr', 'create'], calls)
        for changed in (dict(pr, headRefOid=self.head), dict(pr, isCrossRepository=True),
                        dict(pr, headRepositoryOwner={'login': 'other'})):
            calls, run = self.transport(approval, [changed])
            with self.subTest(pr=changed), patch.object(self.host, '_run', side_effect=run):
                with self.assertRaisesRegex(Refused, 'Push completed; PR delivery failed'):
                    self.host.deliver(approval)
            self.assertIn('push', calls)
            self.assertNotIn(['pr', 'create'], calls)

    def test_create_and_verification_failures_report_successful_push(self):
        approval = self.delivery()
        for options in ({'create_failure': True}, {'wrong_view': True}):
            calls, run = self.transport(approval, **options)
            with self.subTest(options=options), patch.object(self.host, '_run', side_effect=run):
                with self.assertRaisesRegex(Refused, 'Push completed; PR delivery failed or is unverified'):
                    self.host.deliver(approval)
            self.assertIn('push', calls)
            self.assertIn(['pr', 'create'], calls)

    def test_delivery_rejects_remote_rewrite_before_transport(self):
        approval = self.delivery()
        self.git('config', 'url.https://evil.example/.insteadOf', 'https://github.com/')
        with self.assertRaises(Refused):
            self.host.deliver(approval)

    def test_main_and_cross_repository_refused(self):
        from dataclasses import replace
        approval = self.delivery()
        for changed in (replace(approval, branch='main'), replace(approval, repo='other')):
            with self.assertRaises(Refused):
                self.host.deliver(changed)

if __name__ == '__main__':
    unittest.main()
