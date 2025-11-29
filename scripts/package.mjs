import { spawnSync } from 'node:child_process';
import { copyFileSync, existsSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const pad = (value) => String(value).padStart(2, '0');
const now = new Date();
const yearFull = now.getFullYear();
const yearShort = pad(yearFull % 100);
const month = pad(now.getMonth() + 1);
const day = pad(now.getDate());
const hours = pad(now.getHours());
const minutes = pad(now.getMinutes());
const version = `v${yearFull}${month}${day}${hours}${minutes}`;
const versionShort = `v${yearShort}${month}${day}${hours}${minutes}`;
const latestName = 'AutoSaleVPS.zip';
const latestPath = resolve(latestName);
const versionFilePath = resolve('version.md');

const versionDocument =
  `# AutoSaleVPS Version\n\n` +
  `当前版本（vYYYYMMDDHHmm）：${version}\n` +
  `短格式（vyymmddhhmm）：${versionShort}\n` +
  `生成时间：${now.toISOString()}\n` +
  `\n该文件会在执行 npm run package 时同步为最新打包版本。\n`;
writeFileSync(versionFilePath, versionDocument, 'utf8');

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
