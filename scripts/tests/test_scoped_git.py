import hashlib
import pathlib
import subprocess
import tempfile
import unittest
from scripts.scoped_git import CommitApproval, HostGit, Refused


class GitFixture:
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.repository = pathlib.Path(self.tmp.name) / 'repo'
        self.repository.mkdir()
        self.root = self.repository
        self.git('init', '-b', 'feature/original')
        self.git('config', 'user.name', 'Test')
        self.git('config', 'user.email', 'test@example.com')
        (self.root / 'scripts').mkdir()
        (self.root / 'scripts/file.txt').write_text('before')
        self.git('add', '.')
        self.git('commit', '-m', 'initial')
        self.head = self.git('rev-parse', 'HEAD')
        subprocess.check_call(['/usr/bin/git', '-C', str(self.repository), 'worktree', 'add', '-b', 'feature/worker', str(pathlib.Path(self.tmp.name) / 'worker')], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        self.root = pathlib.Path(self.tmp.name) / 'worker'
        self.host = HostGit(self.root, self.repository, pathlib.Path('/usr/bin/git'))
        (self.root / 'scripts/file.txt').write_text('after')
        self.approval = CommitApproval(self.root, 'feature/worker', self.head, self.head,
                                       {'scripts/file.txt': hashlib.sha256(b'after').hexdigest()},
                                       {'scripts/file.txt': '100644'},
                                       'fix: approved (DRA-135)', 'Test', 'test@example.com')

    def tearDown(self):
        self.tmp.cleanup()

    def git(self, *args):
        return subprocess.check_output(['/usr/bin/git', '-C', str(self.root), *args],
                                       stderr=subprocess.DEVNULL, text=True).strip()



class ScopedGitTests(GitFixture, unittest.TestCase):
    def test_commit_exact_bytes_without_hooks(self):
        hook = self.repository / '.git/hooks/pre-commit'
        hook.write_text('#!/bin/sh\nexit 99\n')
        hook.chmod(0o755)
        sha = self.host.commit(self.approval)
        self.assertEqual(self.git('show', sha + ':scripts/file.txt'), 'after')
        self.assertEqual(self.git('rev-parse', sha + '^'), self.head)
        self.assertEqual(self.git('status', '--porcelain'), '')

    def test_configured_clean_filter_rejected_before_execution(self):
        marker = self.root / 'executed'
        self.git('config', 'filter.hostile.clean', 'touch ' + str(marker))
        (self.root / 'scripts/.gitattributes').write_text('*.txt filter=hostile')
        with self.assertRaises(Refused):
            self.host.commit(self.approval)
        self.assertFalse(marker.exists())

    def test_changed_mode_rejected(self):
        (self.root / 'scripts/file.txt').chmod(0o755)
        with self.assertRaises(Refused):
            self.host.commit(self.approval)

    def test_protected_casefold_component_rejected(self):
        from dataclasses import replace
        path = self.root / 'scripts/AGENTS.md'
        path.write_text('after')
        approval = replace(self.approval, files={'scripts/AGENTS.md': hashlib.sha256(b'after').hexdigest()},
                           modes={'scripts/AGENTS.md': '100644'})
        with self.assertRaises(Refused):
            self.host._bytes(approval.files, approval.modes)

    def test_changed_content_rejected(self):
        (self.root / 'scripts/file.txt').write_text('unreviewed')
        with self.assertRaises(Refused):
            self.host.commit(self.approval)

    def test_unrelated_dirty_and_staged_rejected(self):
        (self.root / 'other').write_text('extra')
        with self.assertRaises(Refused):
            self.host.commit(self.approval)
        (self.root / 'other').unlink()
        self.git('add', 'scripts/file.txt')
        with self.assertRaises(Refused):
            self.host.commit(self.approval)

    def test_symlink_rejected(self):
        (self.root / 'scripts/file.txt').unlink()
        (self.root / 'scripts/file.txt').symlink_to('/etc/passwd')
        with self.assertRaises(Refused):
            self.host.commit(self.approval)

    def test_changed_head_rejected(self):
        self.git('commit', '--allow-empty', '-m', 'other')
        with self.assertRaises(Refused):
            self.host.commit(self.approval)


if __name__ == '__main__':
    unittest.main()
