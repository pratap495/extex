const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');

execFileSync(process.execPath, ['build.js'], { stdio: 'inherit' });
const target = path.resolve('hostinger-dist');
const workspace = path.resolve('.');
if (!target.startsWith(workspace + path.sep) || path.basename(target) !== 'hostinger-dist') {
  throw new Error('Refusing to write outside the hostinger-dist output directory.');
}
fs.rmSync(target, { recursive: true, force: true });
fs.cpSync(path.resolve('dist'), target, { recursive: true });
const backend = path.resolve('php-backend');
for (const name of ['login.php', 'admin.php', 'logout.php', 'create-admin.php']) {
  fs.copyFileSync(path.join(backend, name), path.join(target, name));
}
fs.copyFileSync(path.join(backend, '.htaccess'), path.join(target, '.htaccess'));
fs.cpSync(path.join(backend, 'private'), path.join(target, 'private'), { recursive: true });
console.log('Added the PHP admin login backend to dist/ for Apache/PHP hosting.');
