import { spawnSync } from 'node:child_process';
import { copyFileSync, mkdirSync, mkdtempSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../', import.meta.url));
const version = JSON.parse(readFileSync(resolve(root, 'package.json'), 'utf8')).version;
const plugin = readFileSync(resolve(root, 'autosalevps.php'), 'utf8');
const header = plugin.match(/^ \* Version: (\S+)$/m)?.[1];
const constant = plugin.match(/const VERSION = '([^']+)'/)?.[1];
if (version !== header || version !== constant) {
  throw new Error('The PHP header and VERSION must match package.json before packaging.');
}

const files = [
  'autosalevps.php',
  'LICENSE',
  'assets/css/main.css',
  'assets/js/main.js',
  'assets/js/main.js.map',
  'config/config.toml',
  'config/model.toml',
  ...readdirSync(resolve(root, 'src/php'), { withFileTypes: true })
    .filter((entry) => entry.isFile() && entry.name.endsWith('.php'))
    .map((entry) => `src/php/${entry.name}`)
    .sort()
];

const stage = mkdtempSync(join(tmpdir(), 'autosalevps-package-'));
const archive = 'AutoSaleVPS.zip';
try {
  const packagedFiles = files.map((file) => {
    const name = `AutoSaleVPS/${file}`;
    const target = resolve(stage, name);
    mkdirSync(dirname(target), { recursive: true });
    copyFileSync(resolve(root, file), target);
    return name;
  });
  const result = spawnSync('zip', ['-X', archive, ...packagedFiles], { cwd: stage, stdio: 'inherit' });
  if (result.error || result.status !== 0) {
    throw result.error ?? new Error(`zip exited with status ${result.status}`);
  }
  copyFileSync(resolve(stage, archive), resolve(root, archive));
  writeFileSync(resolve(root, 'version.md'), `# AutoSaleVPS Version\n\nWordPress / npm: ${version}\n\nGit tag: v${version}\n`);
  console.log(`Package created: ${archive} (version ${version})`);
} finally {
  rmSync(stage, { recursive: true, force: true });
}
