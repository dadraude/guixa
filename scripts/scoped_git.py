"""Trusted host operations, never imported as a worker service or command listener.

This module and its dependencies must come from trusted host code, never the
worker-modified checkout. Approvals must be constructed by the host from owner authorization and independent
review evidence and required checks completed outside this module. Hooks are
disabled because they are executable worker-controlled inputs; this module does
not replace required host verification. Worker JSON is not an approval. The host must stop workers and
exclude other writers for the whole operation. Ref CAS detects head races, but
filesystem/index/remote operations cannot be made atomic against concurrent host
writers. Delivery may push successfully before PR creation fails; no rollback is
claimed. The executable files and their parent directories are host-trusted.
"""
from dataclasses import dataclass
import hashlib
import os
from pathlib import Path
import re
import subprocess
import tempfile
from scripts.codex_profile import PROTECTED, comparison_parts, reject_pattern_syntax


class Refused(ValueError):
    pass


@dataclass(frozen=True)
class CommitApproval:
    worktree: Path
    branch: str
    base: str
    head: str
    files: dict[str, str]
    modes: dict[str, str]
    message: str
    author: str
    email: str




class HostGit:
    def __init__(self, worktree: Path, repository: Path, git: Path, gh: Path | None = None):
        self.root = worktree.resolve(strict=True)
        self.git = self._executable(git)
        self.gh = self._executable(gh) if gh else None
        self.env = {'PATH': '/usr/bin:/bin', 'HOME': '/nonexistent',
                    'GIT_CONFIG_NOSYSTEM': '1', 'GIT_CONFIG_GLOBAL': '/dev/null',
                    'GIT_TERMINAL_PROMPT': '0', 'GIT_NO_REPLACE_OBJECTS': '1',
                    'LC_ALL': 'C'}
        if self._git('rev-parse', '--show-toplevel').strip() != str(self.root):
            raise Refused('Not the approved worktree root')
        self.repository = repository.resolve(strict=True)
        self.common = Path(self._git("rev-parse", "--path-format=absolute", "--git-common-dir").strip())
        other = self._run([self.git, "-C", str(self.repository), "rev-parse", "--path-format=absolute", "--git-common-dir"]).strip()
        if str(self.common) != other or not (self.root / ".git").is_file():
            raise Refused("Expected linked worktree of the explicit repository")
        self.gitdir = Path(self._git('rev-parse', '--absolute-git-dir').strip()).resolve()

    @staticmethod
    def _executable(path):
        path = Path(path)
        if not path.is_absolute() or not path.is_file() or not os.access(path, os.X_OK):
            raise Refused('Executable must be a trusted absolute path')
        return str(path.resolve(strict=True))

    def _run(self, argv, env=None):
        result = subprocess.run(argv, cwd=self.root, env=env or self.env,
                                capture_output=True, text=True, timeout=60)
        if result.returncode:
            raise Refused('Host operation failed: ' + argv[0])
        return result.stdout

    def _git(self, *args):
        return self._run([self.git, '-C', str(self.root), '-c', 'core.fsmonitor=false',
                          '-c', 'core.hooksPath=/dev/null', '-c', 'core.untrackedCache=false', '-c', 'core.splitIndex=false',
                          '-c', 'core.pager=cat', *args])

    @staticmethod
    def _sha(value):
        if not re.fullmatch('[0-9a-f]{40}', value):
            raise Refused('Expected immutable SHA-1 object id')

    @staticmethod
    def _branch(value):
        if not re.fullmatch(r'(feature|bugfix|fix|chore)/[A-Za-z0-9_-][A-Za-z0-9_/-]*', value):
            raise Refused('Only scoped feature branches are allowed')
        if '..' in value or '//' in value or value.endswith('/'):
            raise Refused('Invalid branch')

    def _identity(self, approval):
        if Path(approval.worktree).resolve() != self.root:
            raise Refused('Worktree mismatch')
        if Path(self._git("rev-parse", "--path-format=absolute", "--git-common-dir").strip()) != self.common:
            raise Refused("Repository identity changed")
        config = self._git('config', '--local', '--list').lower()
        if any(x in config for x in ('insteadof=', 'pushurl=', 'include.', 'includeif.', 'filter.', 'textconv=', 'attributesfile=', 'extensions.worktreeconfig=')):
            raise Refused('Executable filters or config rewriting are forbidden')
        self._branch(approval.branch)
        self._sha(approval.head)
        if self._git('symbolic-ref', '--short', 'HEAD').strip() != approval.branch:
            raise Refused('Branch changed')
        if self._git('rev-parse', 'HEAD').strip() != approval.head:
            raise Refused('HEAD changed')

    def _bytes(self, files, modes):
        result = {}
        if set(modes) != set(files):
            raise Refused('Every reviewed file requires a reviewed Git mode')
        if not files:
            raise Refused('No approved files')
        for name, digest in files.items():
            path = Path(name)
            if path.is_absolute() or any(p in ('..', '.', '') for p in path.parts):
                raise Refused('Unsafe path')
            reject_pattern_syntax(name)
            parts = comparison_parts(path)
            protected_names = {comparison_parts(item)[-1] for item in PROTECTED}
            if str(path) != name or path.parts[0] not in ('scripts', 'docs') or len(path.parts) < 2:
                raise Refused('Only exact scripts/docs files are approved')
            if any(part.startswith('.env') or part in protected_names or part == '.gitattributes' for part in parts):
                raise Refused('Protected component')
            if any(parts[:len(comparison_parts(p))] == comparison_parts(p) for p in PROTECTED):
                raise Refused('Protected path')
            target = self.root
            for part in path.parts:
                target = target / part
                if target.is_symlink():
                    raise Refused('Symlink path')
            if not target.is_file() or target.resolve().parent != (self.root / path.parent).resolve():
                raise Refused('Expected regular file')
            data = target.read_bytes()
            if hashlib.sha256(data).hexdigest() != digest:
                raise Refused('Reviewed content changed')
            mode = '100755' if target.stat().st_mode & 0o111 else '100644'
            if modes[name] != mode:
                raise Refused('Reviewed file mode changed')
            result[name] = (data, mode)
        return result

    def _changes(self, files):
        if self._git('diff', '--no-ext-diff', '--no-textconv', '--cached', '--name-only', '-z'):
            raise Refused('Staged changes are not approved')
        dirty = set(self._git('diff', '--no-ext-diff', '--no-textconv', '--name-only', '-z').split('\0')) - {''}
        dirty |= set(self._git('ls-files', '--others', '--exclude-standard', '-z').split('\0')) - {''}
        if dirty != set(files):
            raise Refused('Dirty paths differ from approval')

    def commit(self, approval: CommitApproval):
        self._identity(approval)
        self._sha(approval.base)
        self._git('merge-base', '--is-ancestor', approval.base, approval.head)
        if '\n' in approval.message or not approval.message or any('\n' in x for x in (approval.author, approval.email)):
            raise Refused('Invalid commit metadata')
        self._changes(approval.files)
        contents = self._bytes(approval.files, approval.modes)
        with tempfile.TemporaryDirectory(prefix='host-git-') as tmp:
            env = dict(self.env, GIT_INDEX_FILE=tmp + '/index',
                       GIT_AUTHOR_NAME=approval.author, GIT_AUTHOR_EMAIL=approval.email,
                       GIT_COMMITTER_NAME=approval.author, GIT_COMMITTER_EMAIL=approval.email)
            def git(*args, data=None):
                argv = [self.git, '-C', str(self.root), '-c', 'core.hooksPath=/dev/null',
                        '-c', 'commit.gpgsign=false', '-c', 'core.fsmonitor=false',
                        '-c', 'core.sparseCheckout=false', '-c', 'core.splitIndex=false', *args]
                out = subprocess.run(argv, input=data, env=env, capture_output=True, timeout=60)
                if out.returncode:
                    raise Refused('Commit construction failed')
                return out.stdout.decode().strip()
            git('read-tree', approval.head)
            for name, (data, mode) in contents.items():
                blob = git('hash-object', '-w', '--no-filters', '--stdin', data=data)
                git('update-index', '--add', '--cacheinfo', mode, blob, name)
            tree = git('write-tree')
            commit = git('commit-tree', tree, '-p', approval.head, '-m', approval.message)
            self._identity(approval)
            self._changes(approval.files)
            if self._bytes(approval.files, approval.modes) != contents:
                raise Refused('Files changed during commit')
            self._git('update-ref', 'refs/heads/' + approval.branch, commit, approval.head)
            self._git('-c', 'core.sparseCheckout=false', 'read-tree', commit)
            return commit

