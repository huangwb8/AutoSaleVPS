import { spawnSync } from 'node:child_process';
import { copyFileSync, existsSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const pad = (value) => String(value).padStart(2, '0');
const now = new Date();
const version =
  `v${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}` +
  `${pad(now.getHours())}${pad(now.getMinutes())}`;
const latestName = 'AutoSaleVPS.zip';
const latestPath = resolve(latestName);

const pluginPath = resolve('autosalevps.php');
const originalPlugin = readFileSync(pluginPath, 'utf8');
const updatedPlugin = originalPlugin
  .replace(/(Version:\s*)([^\r\n]+)/, `$1${version}`)
  .replace(/(const\s+VERSION\s*=\s*')[^']+(')/, `$1${version}$2`);

if (updatedPlugin === originalPlugin) {
  console.warn('Warning: 未能写入自动版本号，请检查 autosalevps.php 格式。');
} else {
  writeFileSync(pluginPath, updatedPlugin, 'utf8');
}

if (existsSync(latestPath)) {
  rmSync(latestPath);
}

const excludes = [
  'node_modules/*',
  '.git/*',
  latestName,
  'tests/*',
  '.DS_Store',
  '**/.DS_Store',
  'docs/*',
  'scripts/*'
];
const zipArgs = ['-r', latestName, '.', ...excludes.flatMap((p) => ['-x', p])];

let status = 0;
try {
  const result = spawnSync('zip', zipArgs, { stdio: 'inherit' });
  status = result.status ?? 0;
} finally {
  writeFileSync(pluginPath, originalPlugin, 'utf8');
}

if (status !== 0) {
  process.exit(status || 1);
}

console.log(`Package created: ${latestName} (version ${version})`);
