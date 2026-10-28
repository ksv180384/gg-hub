import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { checkRuntimes, isPatched } from './check-runtime-security.mjs';

const read = (path) => readFileSync(new URL(`../${path}`, import.meta.url), 'utf8');

test('rejects vulnerable releases, unsupported branches and prereleases', () => {
  const floors = {
    php: ['8.2.35', '8.3.36', '8.4.27', '8.5.12'],
    node: ['22.23.4', '24.22.1', '26.12.1'],
    nginx: ['1.30.6', '1.31.7'],
  };
  for (const [runtime, versions] of Object.entries(floors)) {
    for (const version of versions) {
      const parts = version.split('.').map(Number);
      assert.equal(isPatched(runtime, version), true, version);
      parts[2] -= 1;
      assert.equal(isPatched(runtime, parts.join('.')), false, parts.join('.'));
      assert.equal(isPatched(runtime, `${version}-RC1`), false);
    }
  }
  for (const version of ['20.20.0', '23.0.0', '25.8.0', '22.22.99']) {
    assert.equal(isPatched('node', version), false, version);
  }
  assert.equal(isPatched('php', '8.1.99'), false);
  assert.equal(isPatched('nginx', '1.28.99'), false);
  assert.equal(isPatched('node', '22.24.0'), true);
  assert.equal(isPatched('php', '8.4.28'), true);
  assert.equal(isPatched('php', undefined), false);
});

test('Docker probes check image or running container versions and fail closed', () => {
  for (const running of [false, true]) {
    const calls = [];
    const execute = (command, args) => {
      calls.push(args);
      assert.equal(command, 'docker');
      const executable = running ? args[4] : args[6];
      if (executable === 'php') {
        return { status: 0, stdout: '{"version":"8.4.27","uploadProgress":false}', stderr: '' };
      }
      return executable === 'nginx'
        ? { status: 0, stdout: '', stderr: 'nginx version: nginx/1.30.6\n' }
        : { status: 0, stdout: 'v22.23.4\n', stderr: '' };
    };
    checkRuntimes(running, execute);
    assert.equal(calls.length, 6);
    assert.ok(calls.every((args) => args.includes(running ? 'exec' : '--no-deps')));
  }
  assert.throws(() => checkRuntimes(false, () => ({ status: 1, stderr: 'Docker unavailable' })), /Docker unavailable/);
  assert.throws(() => checkRuntimes(false, () => ({ status: 0, stdout: '{"version":"8.4.26","uploadProgress":false}' })), /unapproved/);
  assert.throws(() => checkRuntimes(false, () => ({ status: 0, stdout: '{"version":"8.4.27","uploadProgress":true}' })), /must be Off/);
});

test('Docker runtime pins and PHP mitigation meet security floors', () => {
  for (const path of [
    '_docker/php/php.dockerfile',
    '_docker/frontend/node.dockerfile',
    '_docker/socket_server/socket_server.dockerfile',
  ]) {
    const source = read(path);
    const stages = [...source.matchAll(/^FROM (php|node):([^\s]+)(?: AS \w+)?\r?$/gm)];
    assert.ok(stages.length > 0, path);
    for (const match of stages) {
      assert.equal(isPatched(match[1], match[2].split('-')[0]), true, match[0]);
    }
    assert.doesNotMatch(source, /setup_current|npm@latest|install -y nodejs/);
  }
  assert.match(read('_docker/php/php.dockerfile'), /COPY --from=node_runtime \/usr\/local\/bin\/node/);
  assert.equal(isPatched('node', read('frontend/.nvmrc').trim()), true);
  const compose = read('docker-compose.yml');
  const nginx = compose.match(/image: nginx:([^\s]+)/);
  assert.ok(nginx);
  assert.equal(isPatched('nginx', nginx[1].split('-')[0]), true);
  for (const path of ['_docker/php/php.ini', '_docker/php/security.ini']) {
    assert.match(read(path), /^session\.upload_progress\.enabled\s*=\s*Off\s*$/m);
  }
  assert.match(compose, /\.\/_docker\/php\/security\.ini:\/usr\/local\/etc\/php\/conf\.d\/zz-security\.ini:ro/);
});
