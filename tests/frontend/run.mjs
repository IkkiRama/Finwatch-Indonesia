import { mkdirSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { resolve } from 'node:path';
import { fileURLToPath, URL } from 'node:url';
import process from 'node:process';

const projectRoot = fileURLToPath(new URL('../..', import.meta.url));
const tempDirectory = resolve(projectRoot, 'storage/framework/frontend-temp');
mkdirSync(tempDirectory, { recursive: true });
const result = spawnSync(process.execPath, [resolve(projectRoot, 'node_modules/vitest/vitest.mjs'), 'run', ...process.argv.slice(2)], {
  cwd: projectRoot,
  env: { ...process.env, TEMP: tempDirectory, TMP: tempDirectory, TMPDIR: tempDirectory },
  stdio: 'inherit',
});
if (result.error) process.stderr.write(`${result.error.message}\n`);
process.exit(result.status ?? 1);

