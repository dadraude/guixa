import hashlib
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import codex_worker as worker


class WorkerTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name).resolve()
        self.repo = self.root / 'repo'
        self.repo.mkdir()
        self.git('init', cwd=self.repo)
        self.git('-c', 'user.name=Test', '-c', 'user.email=test@example.invalid',
                 'commit', '--allow-empty', '-m', 'fixture', cwd=self.repo)
        self.tree = self.root / 'tree'
        self.git('worktree', 'add', '-b', 'fixture', str(self.tree), cwd=self.repo)
        self.home = self.root / 'host'
        self.home.mkdir()
        self.config = self.home / 'config.toml'
        self.config.write_text('[mcp_servers.test]\ncommand="unused"\n')
        self.exe = self.home / 'codex'
        self.exe.write_text('#!/bin/sh\nif [ \"$2\" = features ]; then /bin/echo \"multi_agent stable true\"; else /bin/cat; fi\n')
        self.exe.chmod(0o700)
        self.policy = self.home / 'policy.json'
        self.data = dict(repository=str(self.repo), worktree=str(self.tree),
                         writes=['scripts/new.py'], executable=str(self.exe),
                         model='gpt-6.1-sol', codex_home=str(self.home), user_home=str(self.home),
                         features={'multi_agent': 'stable'},
                         activation=False, owner_tool_audit_authorized=False,
                         files=worker.inventory(self.repo, self.tree, self.home, self.exe))
        self.save()

    def git(self, *args, cwd):
        subprocess.run(['git', *args], cwd=cwd, check=True, capture_output=True)

    def save(self):
        self.policy.write_text(json.dumps(self.data))
        self.digest = hashlib.sha256(self.policy.read_bytes()).hexdigest()

    def activate(self):
        self.data.update(activation=True, owner_tool_audit_authorized=True, owner_config_audit_authorized=True)
        self.save()

    def prepare(self, **kwargs):
        return worker.prepare_launch(str(self.policy), self.digest, **kwargs)

    def test_preparation_never_activates_and_launch_requires_exact_host_gates(self):
        plan = self.prepare()
        self.assertFalse(plan['activation_ready'])
        with self.assertRaises(ValueError):
            worker.launch(str(self.policy), self.digest, 'prompt')
        self.activate()
        result = worker.launch(str(self.policy), self.digest, 'prompt')
        self.assertEqual(result.stdout, 'prompt')

    def test_fixed_native_argv_and_clean_environment(self):
        plan = self.prepare()
        argv = plan['argv']
        self.assertEqual(argv[0], str(self.exe))
        self.assertIn('mcp_servers.test.enabled=false', argv)
        self.assertIn('features.multi_agent=false', argv)
        self.assertNotIn('--sandbox', argv)
        self.assertNotIn('--ignore-user-config', argv)
        self.assertNotIn('--ignore-rules', argv)
        self.assertNotIn('PATH', plan['env'])
        self.assertEqual(set(plan['env']), {'HOME', 'CODEX_HOME'})
        self.assertEqual(argv[-1], '-')
        with self.assertRaises(TypeError):
            self.prepare(extra_flags=['--sandbox', 'danger-full-access'])

    def test_removed_dotted_feature_is_audited_without_unsupported_override(self):
        original = self.exe.read_text()
        self.exe.write_text(original.replace('multi_agent stable true',
                            'multi_agent stable true\nguardianv2.thread_context removed false'))
        self.data['features']['guardianv2.thread_context'] = 'removed'
        self.data['files'] = worker.inventory(self.repo, self.tree, self.home, self.exe)
        self.save()
        self.assertNotIn('features.guardianv2.thread_context=false', self.prepare()['argv'])

    def test_read_only_variant_has_no_write_grants(self):
        plan = self.prepare(read_only=True)
        filesystem = plan['filesystem']
        self.assertNotIn('write', filesystem.values())

    def test_config_executable_rules_and_policy_drift_fail_closed(self):
        for target in (self.config, self.exe):
            original = target.read_bytes()
            target.write_bytes(original + b'\n')
            with self.assertRaises(ValueError):
                self.prepare()
            target.write_bytes(original)
        (self.tree / '.codex').mkdir()
        (self.tree / '.codex/config.toml').write_text('sandbox_mode="danger-full-access"')
        with self.assertRaises(ValueError):
            self.prepare()
        self.policy.write_text('{}')
        with self.assertRaises(ValueError):
            self.prepare()

    def test_legacy_unknown_and_ambiguous_config_are_rejected_even_if_audited(self):
        for content in ('sandbox_mode="read-only"', '[features]\nunknown=true',
                        'unknown=true', '[mcp_servers."bad.key"]\ncommand="x"',
                        '[profiles.other]\nsandbox_mode="read-only"'):
            self.config.write_text(content)
            self.data['files'] = worker.inventory(self.repo, self.tree, self.home, self.exe)
            self.save()
            with self.subTest(content=content), self.assertRaises(ValueError):
                self.prepare()

    def test_path_attacks_and_writable_host_policy_fail(self):
        (self.tree / 'scripts').mkdir()
        (self.tree / 'scripts/new.py').symlink_to(self.policy)
        with self.assertRaises(ValueError):
            self.prepare()
        (self.tree / 'scripts/new.py').unlink()
        moved = self.tree / 'scripts/policy.json'
        moved.write_bytes(self.policy.read_bytes())
        with self.assertRaises(ValueError):
            worker.prepare_launch(str(moved), self.digest)
        alias = self.home / 'alias'
        alias.symlink_to(self.exe)
        self.data['executable'] = str(alias)
        self.save()
        with self.assertRaises(ValueError):
            self.prepare()

    def test_process_failure_and_timeout_are_reported(self):
        self.exe.write_text('#!/bin/sh\nif [ \"$2\" = features ]; then /bin/echo \"multi_agent stable true\"; else exit 3; fi\n')
        self.data['files'] = worker.inventory(self.repo, self.tree, self.home, self.exe)
        self.activate()
        with self.assertRaises(subprocess.CalledProcessError):
            worker.launch(str(self.policy), self.digest, 'prompt')
        self.exe.write_text('#!/bin/sh\nif [ \"$2\" = features ]; then /bin/echo \"multi_agent stable true\"; else /bin/sleep 2; fi\n')
        self.data['files'] = worker.inventory(self.repo, self.tree, self.home, self.exe)
        self.save()
        with self.assertRaises(subprocess.TimeoutExpired):
            worker.launch(str(self.policy), self.digest, 'prompt', timeout=0.01)


if __name__ == '__main__':
    unittest.main()
