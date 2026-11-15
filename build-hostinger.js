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
for (const name of ['index.php', 'page.php', 'media.php', 'styles.php', 'robots.php', 'sitemap.php', '.htaccess']) {
  fs.copyFileSync(path.resolve(name), path.join(target, name));
}

const backendTarget = path.join(target, 'php-backend');
fs.mkdirSync(backendTarget, { recursive: true });
for (const name of ['login.php', 'cms-admin.php', 'cms-settings.php', 'logout.php', 'create-admin.php', 'cms-install.php', 'schema.sql', '.htaccess']) {
  fs.copyFileSync(path.join(backend, name), path.join(backendTarget, name));
}

// Copy only runtime PHP files. Never include config.local.php or local DB credentials.
const privateTarget = path.join(backendTarget, 'private');
fs.mkdirSync(privateTarget, { recursive: true });
for (const name of ['config.php', 'database.php', 'auth.php', 'cms.php', '.htaccess']) {
  fs.copyFileSync(path.join(backend, 'private', name), path.join(privateTarget, name));
}
console.log('Packaged the PHP CMS, media endpoint, and rewrite rules for Apache hosting (without local secrets).');
