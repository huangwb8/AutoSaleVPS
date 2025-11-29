import { spawnSync } from 'node:child_process';
import { existsSync, rmSync } from 'node:fs';
import { resolve } from 'node:path';

const zipPath = resolve('AutoSaleVPS.zip');

if (existsSync(zipPath)) {
  rmSync(zipPath);
}

const excludes = [
  'node_modules/*',
  '.git/*',
  'AutoSaleVPS.zip',
  'tests/*',
  '.DS_Store',
  '**/.DS_Store',
  'docs/*',
  'scripts/*'
];
const zipArgs = ['-r', 'AutoSaleVPS.zip', '.', ...excludes.flatMap((p) => ['-x', p])];

const result = spawnSync('zip', zipArgs, { stdio: 'inherit' });

if (result.status !== 0) {
  process.exit(result.status ?? 1);
}
