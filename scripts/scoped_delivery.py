"""Trusted host delivery after independent review/checks; no worker endpoint.

The host must supply owner-approved immutable head/tree/base evidence and exclude
concurrent writers. A successful push is not rolled back when PR creation fails.
"""
from dataclasses import dataclass
import base64
import json
import os
from pathlib import Path
import re
import subprocess
import tempfile
from scripts.scoped_git import HostGit, Refused


@dataclass(frozen=True)
class DeliveryApproval:
    worktree: Path
    branch: str
    head: str
    tree: str
    base: str
    base_branch: str
    title: str
    body: str
    owner: str = 'dadraude'
    repo: str = 'guixa'



class HostDelivery(HostGit):
    def deliver(self, approval: DeliveryApproval):
        self._identity(approval)
        self._sha(approval.tree)
        self._sha(approval.base)
        self._git("merge-base", "--is-ancestor", approval.base, approval.head)
        if (approval.owner, approval.repo) != ('dadraude', 'guixa') or not self.gh:
            raise Refused('Only the configured GitHub repository is allowed')
        if not re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_/-]*', approval.base_branch) or '..' in approval.base_branch or approval.base_branch == approval.branch:
            raise Refused('Invalid approved PR base')
        self._git('check-ref-format', 'refs/heads/' + approval.base_branch)
        if self._git('rev-parse', 'HEAD^{tree}').strip() != approval.tree:
            raise Refused('Reviewed tree changed')
        if self._git('status', '--porcelain', '--untracked-files=all'):
            raise Refused('Delivery requires a clean worktree')
        if self._git('config', '--local', '--list').splitlines():
            config = self._git('config', '--local', '--list').lower()
            if any(x in config for x in ('insteadof=', 'pushurl=', 'include.', 'includeif.')):
                raise Refused('Remote/config rewriting is forbidden')
        url = 'https://github.com/dadraude/guixa.git'
        origins = self._git('config', '--get-all', 'remote.origin.url').splitlines()
        if origins not in ([url], ['git@github.com:dadraude/guixa.git']):
            raise Refused('Origin differs from approved repository')
        gh_env = dict(self.env)
        # Authentication/configuration is supplied by the trusted host process only.
        for key in ('GH_TOKEN', 'GITHUB_TOKEN'):
            if os.environ.get(key):
                gh_env[key] = os.environ[key]
        token = self._run([self.gh, 'auth', 'token', '--hostname', 'github.com'], gh_env).strip()
        if not token or '\n' in token:
            raise Refused('Missing host GitHub authentication')
        with tempfile.TemporaryDirectory(prefix='host-push-') as tmp:
            self._run([self.git, 'init', '--bare', tmp])
            objects = Path(self._git('rev-parse', '--git-path', 'objects').strip())
            if not objects.is_absolute():
                objects = self.root / objects
            (Path(tmp) / 'objects/info/alternates').write_text(str(objects.resolve()) + '\n')
            auth = base64.b64encode(('x-access-token:' + token).encode()).decode()
            env = dict(self.env, GIT_CONFIG_COUNT='1',
                       GIT_CONFIG_KEY_0='http.https://github.com/.extraHeader',
                       GIT_CONFIG_VALUE_0='Authorization: Basic ' + auth)
            self._identity(approval)
            self._run([self.git, '--git-dir=' + tmp, '-c', 'core.hooksPath=/dev/null',
                       '-c', 'credential.helper=', 'push', '--no-verify', url,
                       approval.head + ':refs/heads/' + approval.branch], env)
        try:
            return self._pr(approval, gh_env)
        except (Refused, ValueError, KeyError, subprocess.TimeoutExpired) as error:
            raise Refused('Push completed; PR delivery failed or is unverified') from error

    def _pr(self, approval, gh_env):
        # Failure from this point means the push may already have succeeded.
        prs = json.loads(self._run([self.gh, 'pr', 'list', '--repo', 'dadraude/guixa',
                                    '--state', 'open', '--head', approval.branch,
                                    '--base', approval.base_branch, '--json',
                                    'url,headRefOid,headRepositoryOwner,isCrossRepository'], gh_env))
        if prs:
            if len(prs) != 1 or prs[0]['headRefOid'] != approval.head or prs[0]['isCrossRepository'] or prs[0]['headRepositoryOwner']['login'] != approval.owner:
                raise Refused('Pushed; existing PR does not match approved head/repository')
            return prs[0]['url']
        url = self._run([self.gh, 'pr', 'create', '--repo', 'dadraude/guixa',
                          '--head', approval.branch, '--base', approval.base_branch,
                          '--title', approval.title, '--body', approval.body], gh_env).strip()
        if not re.fullmatch(r'https://github.com/dadraude/guixa/pull/[1-9][0-9]*', url):
            raise Refused('Unexpected created PR URL')
        pr = json.loads(self._run([self.gh, 'pr', 'view', url, '--repo', 'dadraude/guixa',
                                  '--json', 'headRefOid,baseRefName,headRefName,isCrossRepository'], gh_env))
        if (pr['headRefOid'], pr['baseRefName'], pr['headRefName'], pr['isCrossRepository']) != (approval.head, approval.base_branch, approval.branch, False):
            raise Refused('Created PR differs from approval')
        return url
