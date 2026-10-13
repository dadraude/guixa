"""Host-controlled opt-in Codex launch; inventory is not tool exposure proof."""
import hashlib
import json
import os
from pathlib import Path
import re
import signal
import subprocess
import tomllib

import codex_profile
from codex_profile import absolute_path, comparison_parts, prepare

DISABLED = ('apps', 'plugins', 'browser_use', 'browser_use_external', 'computer_use',
            'image_generation', 'multi_agent')
CONFIG_KEYS = {'model', 'model_reasoning_effort', 'model_reasoning_summary',
               'approval_policy', 'web_search', 'mcp_servers', 'features', 'plugins',
               'projects', 'hooks', 'notice'}
SYSTEM_CONFIGS = (Path('/etc/codex'),
                  Path('/Library/Application Support/com.openai.codex'))


def fingerprint(path):
    path = absolute_path(str(path))
    if not path.is_file():
        raise ValueError('audited files must be regular canonical files')
    stat = path.stat()
    return {'sha256': hashlib.sha256(path.read_bytes()).hexdigest(),
            'device': stat.st_dev, 'inode': stat.st_ino}


def inventory(repository, worktree, codex_home, executable, user_home=None):
    """Fingerprint trust inputs; the host must separately audit effective config layers."""
    paths = {Path(executable), Path(codex_home) / 'config.toml',
             Path(__file__).resolve(), Path(codex_profile.__file__).resolve()}
    for root in (Path(repository), Path(worktree), Path(codex_home),
                 Path(user_home or codex_home)):
        directories = [root / '.claude', root / '.agents', root / 'rules']
        codex_dir = root / '.codex'
        directories += [codex_dir / 'rules', codex_dir / 'hooks']
        for name in ('config.toml', 'AGENTS.md'):
            if (codex_dir / name).exists():
                paths.add(codex_dir / name)
        for directory in directories:
            if directory.exists():
                absolute_path(str(directory))
                paths.update(path for path in directory.rglob('*') if not path.is_dir())
        for name in ('AGENTS.md', 'CLAUDE.md'):
            if (root / name).exists():
                paths.add(root / name)
    # Parent config layers are refused, including changes after host audit.
    for parent in Path(worktree).parents:
        if (parent / '.codex/config.toml').exists():
            raise ValueError('parent Codex configuration is unsupported')
    if any(path.exists() for path in SYSTEM_CONFIGS):
        raise ValueError('system/managed Codex configuration requires a separate verifier')
    return {str(path): fingerprint(path) for path in sorted(paths)}


def outside(path, roots):
    parts = comparison_parts(path)
    for root in roots:
        prefix = comparison_parts(root)
        if parts[:len(prefix)] == prefix:
            raise ValueError('host trust inputs must be outside repository and grants')


def audited_config(home, tree):
    if (tree / '.codex/config.toml').exists():
        raise ValueError('project config layers are unsupported')
    if list(home.glob('*.config.toml')):
        raise ValueError('profile config layers are unsupported')
    config = tomllib.loads((home / 'config.toml').read_text())
    if set(config) - CONFIG_KEYS:
        raise ValueError('unknown or legacy configuration keys')
    features = config.get('features', {})
    if set(features) - set(DISABLED) - {'hooks'}:
        raise ValueError('unknown enabled tool feature configuration')
    if 'hooks' in features and features['hooks'] is not True:
        raise ValueError('existing hooks must remain enabled')
    servers = config.get('mcp_servers', {})
    if not isinstance(servers, dict) or any(not re.fullmatch(r'[A-Za-z0-9_-]+', key)
                                            for key in servers):
        raise ValueError('ambiguous MCP server key')
    return servers


def prepare_launch(policy_path, policy_sha256, *, read_only=False):
    """Validate host policy and build argv without executing any worker."""
    policy_path = absolute_path(policy_path)
    if hashlib.sha256(policy_path.read_bytes()).hexdigest() != policy_sha256:
        raise ValueError('host policy digest changed')
    policy = json.loads(policy_path.read_text())
    allowed = {'repository', 'worktree', 'writes', 'executable', 'model', 'codex_home',
               'activation', 'owner_tool_audit_authorized', 'files', 'owner_config_audit_authorized', 'user_home', 'features'}
    if set(policy) - allowed:
        raise ValueError('unknown launch policy fields')
    fragment = prepare(policy['worktree'], policy['repository'], policy['writes'])
    tree, repo = Path(fragment['worktree']), Path(fragment['repository'])
    executable = absolute_path(policy['executable'])
    home = absolute_path(policy['codex_home'])
    user_home = absolute_path(policy['user_home'])
    trusted_paths = [policy_path, executable, home / 'config.toml',
                     Path(__file__).resolve(), Path(codex_profile.__file__).resolve()]
    trusted_paths += [Path(path) for path in policy['files']]
    trusted_parts = {comparison_parts(path) for path in trusted_paths}
    trusted_identities = {(path.stat().st_dev, path.stat().st_ino)
                          for path in trusted_paths if path.exists()}
    for name in fragment['approved_writes']:
        grant = Path(name)
        if comparison_parts(grant) in trusted_parts:
            raise ValueError('worker may not modify host launcher or trust inputs')
        if grant.exists():
            stat = grant.stat()
            if stat.st_nlink != 1 or (stat.st_dev, stat.st_ino) in trusted_identities:
                raise ValueError('worker write grant aliases a file or host trust input')
    roots = [tree, repo, Path(fragment['git_common_dir'])]
    for path in (policy_path, executable, home):
        outside(path, roots)
    if not os.access(executable, os.X_OK):
        raise ValueError('pinned Codex executable is not executable')
    if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9._-]+', policy['model']):
        raise ValueError('explicit model ID required')
    if inventory(repo, tree, home, executable, user_home) != policy['files']:
        raise ValueError('audited configuration or executable identity changed')
    servers = audited_config(home, tree)
    feature_result = subprocess.run([str(executable), '--no-daemon', 'features', 'list'],
                                    env={'HOME': str(user_home), 'CODEX_HOME': str(home)},
                                    input='', capture_output=True, text=True, check=True, timeout=10)
    features = {}
    for line in feature_result.stdout.splitlines():
        fields = line.split()
        if len(fields) < 3 or fields[-1] not in ('true', 'false') or not re.fullmatch(r'[A-Za-z0-9_.]+', fields[0]):
            raise ValueError('unknown feature inventory format')
        status = ' '.join(fields[1:-1])
        if status not in ('stable', 'experimental', 'under development', 'deprecated', 'removed'):
            raise ValueError('unknown feature status')
        features[fields[0]] = status
    if not features or features != policy['features']:
        raise ValueError('registered feature inventory changed')
    config = tomllib.loads(fragment['toml'])
    filesystem = config['permissions'][fragment['profile']]['filesystem']
    if read_only:
        filesystem = {path: 'read' if mode == 'write' else mode
                      for path, mode in filesystem.items()}
    # JSON object syntax is not TOML; inline-table keys must be individually quoted.
    table = '{' + ','.join(f'{json.dumps(path, ensure_ascii=False)}={json.dumps(mode)}'
                          for path, mode in filesystem.items()) + '}'
    overrides = [f'default_permissions="{fragment["profile"]}"',
                 f'permissions.{fragment["profile"]}.filesystem={table}',
                 f'permissions.{fragment["profile"]}.network.enabled=false',
                 'approval_policy="never"', 'web_search="disabled"',
                 'shell_environment_policy.inherit="none"',
                 'shell_environment_policy.ignore_default_excludes=false',
                 'shell_environment_policy.set={}']
    overrides += [f'mcp_servers.{key}.enabled=false' for key in sorted(servers)]
    overrides += [f'features.{key}=false' for key in sorted(features)
                  if features[key] != 'removed'
                  and key not in ('hooks', 'shell_tool', 'unified_exec', 'unified_exec_tty')]
    argv = [str(executable), '--no-daemon', 'exec', '--strict-config', '--ephemeral', '--json',
            '-C', str(tree), '-m', policy['model']]
    for override in overrides:
        argv.extend(['-c', override])
    argv.append('-')
    return {'argv': argv, 'env': {'HOME': str(user_home), 'CODEX_HOME': str(home)},
            'filesystem': filesystem, 'activation_ready': False,
            'owner_authorized': policy.get('activation') is True,
            'acceptance_recorded': policy.get('owner_tool_audit_authorized') is True,
            'layers_recorded': policy.get('owner_config_audit_authorized') is True,
            'limitations': ['host acceptance attestations are not measured exposure proof',
                            'root read access does not provide credential confidentiality',
                            'path checks cannot eliminate concurrent host filesystem changes']}


def launch(policy_path, policy_sha256, prompt, *, read_only=False, timeout=300):
    """Explicit host entry point. No activation or live calls occur during preparation."""
    plan = prepare_launch(policy_path, policy_sha256, read_only=read_only)
    if not all(plan[key] for key in ('owner_authorized', 'acceptance_recorded', 'layers_recorded')):
        raise ValueError('owner activation and native config/tool acceptance remain gated')
    if not isinstance(prompt, str) or not prompt or not 0 < timeout <= 3600:
        raise ValueError('nonempty prompt and bounded timeout required')
    process = subprocess.Popen(plan['argv'], env=plan['env'], stdin=subprocess.PIPE,
                               stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True,
                               start_new_session=True)
    try:
        stdout, stderr = process.communicate(prompt, timeout=timeout)
    except subprocess.TimeoutExpired:
        os.killpg(process.pid, signal.SIGKILL)
        process.communicate()
        raise
    if process.returncode:
        raise subprocess.CalledProcessError(process.returncode, plan['argv'], stdout, stderr)
    return subprocess.CompletedProcess(plan['argv'], process.returncode, stdout, stderr)


def exposure_report(plan):
    """Measure what this launch plan exposes from the CLI's own inventory, not from the plan's flags.

    The plan's override flags are only replayed so the CLI reports its effective state under them.
    """
    argv, env = plan['argv'], plan['env']
    overrides = [item for flag, item in zip(argv, argv[1:]) if flag == '-c']
    flags = [item for override in overrides for item in ('-c', override)]
    def inventory_output(*command):
        return subprocess.run([argv[0], '--no-daemon', *command, *flags], env=env, input='',
                              capture_output=True, text=True, check=True, timeout=10).stdout
    servers = json.loads(inventory_output('mcp', 'list', '--json'))
    if not isinstance(servers, list) or not all(
            isinstance(item, dict) and isinstance(item.get('name'), str)
            and isinstance(item.get('enabled'), bool) for item in servers):
        raise ValueError('unknown MCP inventory format')
    tools = []
    for line in inventory_output('features', 'list').splitlines():
        fields = line.split()
        if len(fields) < 3 or fields[-1] not in ('true', 'false'):
            raise ValueError('unknown feature inventory format')
        if fields[-1] == 'true':
            tools.append(fields[0])
    writable = [path for path, mode in plan['filesystem'].items() if mode == 'write']
    return {'version': 1, 'tools': sorted(tools),
            'mcp_servers': sorted(item['name'] for item in servers if item['enabled']),
            'writable_paths': sorted(writable),
            'network': not any(item.endswith('network.enabled=false') for item in overrides),
            'inherited_config': [str(Path(env['CODEX_HOME']) / 'config.toml')]}
