import json
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
