import { spawnSync } from 'node:child_process';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

// Security floors verified against upstream advisories on 2026-10-28.
const minimumVersions = {
  php: { '8.2': 35, '8.3': 36, '8.4': 27, '8.5': 12 },
  node: { '22.23': 4, '24.22': 1, '26.12': 1 },
  nginx: { '1.30': 6, '1.31': 7 },
};

export function isPatched(runtime, version) {
  const match = /^(\d+)\.(\d+)\.(\d+)$/.exec(version);
  if (!match) {
    return false;
  }

  const [, major, minor, patch] = match.map(Number);
  return Object.entries(minimumVersions[runtime] ?? {}).some(([branch, floor]) => {
    const [allowedMajor, allowedMinor] = branch.split('.').map(Number);
    // Accept later minor releases only within supported Node.js majors.
    return major === allowedMajor && (
      (runtime === 'node' && minor > allowedMinor)
      || (minor === allowedMinor && patch >= floor)
    );
  });
}

const phpProbe = 'echo json_encode(["version" => PHP_VERSION, "uploadProgress" => (bool) ini_get("session.upload_progress.enabled")]);';
const probes = [
  { service: 'gg-php_8.4', runtime: 'php', executable: 'php', args: ['-r', phpProbe] },
  { service: 'gg-php_8.4', runtime: 'node', executable: 'node', args: ['--version'] },
  { service: 'gg-frontend', runtime: 'node', executable: 'node', args: ['--version'] },
  { service: 'socket-server-nodejs', runtime: 'node', executable: 'node', args: ['--version'] },
  { service: 'gg-nginx', runtime: 'nginx', executable: 'nginx', args: ['-v'] },
  { service: 'roundcube', runtime: 'php', executable: 'php', args: ['-r', phpProbe] },
];

export function checkRuntimes(running = false, execute = spawnSync) {
  for (const probe of probes) {
    const modeArgs = running
      ? ['exec', '-T', probe.service, probe.executable]
      : ['run', '--rm', '--no-deps', '-T', '--entrypoint', probe.executable, probe.service];
    const result = execute('docker', ['compose', ...modeArgs, ...probe.args], {
      cwd: fileURLToPath(new URL('../', import.meta.url)),
      encoding: 'utf8',
    });
    if (result.error || result.status !== 0) {
      throw new Error(`${probe.service}: ${result.error?.message ?? result.stderr}`);
    }

    let version;
    if (probe.runtime === 'php') {
      const info = JSON.parse(result.stdout.trim());
      version = info.version;
      if (info.uploadProgress) {
        throw new Error(`${probe.service}: session.upload_progress.enabled must be Off`);
      }
    } else {
      const output = `${result.stdout}\n${result.stderr}`;
      version = output.match(/(?:^v|nginx\/)(\d+\.\d+\.\d+)(?=\s|$)/m)?.[1];
    }

    if (!isPatched(probe.runtime, version)) {
      throw new Error(`${probe.service}: unapproved ${probe.runtime} version ${version}`);
    }
    console.log(`${probe.service}: ${probe.runtime} ${version} OK`);
  }
}

if (process.argv[1] && fileURLToPath(import.meta.url) === resolve(process.argv[1])) {
  try {
    checkRuntimes(process.argv.includes('--running'));
  } catch (error) {
    console.error(error.message);
    process.exitCode = 1;
  }
}
