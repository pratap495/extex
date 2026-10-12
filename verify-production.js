const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { spawn } = require('node:child_process');
const { once } = require('node:events');

(async () => {
  const config = JSON.parse(fs.readFileSync('vercel.json', 'utf8'));
  assert.equal(config.framework, null, 'Deploy this site with the Other/static preset');
  assert.equal(config.outputDirectory, 'dist');
  assert.equal(fs.existsSync('app.js'), false, 'Browser code must not occupy a server entrypoint');
  assert.equal(fs.existsSync('dist/app.js'), false, 'Remove obsolete build artifacts');
  assert.equal(fs.existsSync('dist/server.js'), false);

  // Start the actual production output without DOM globals or a shared dev port.
  const child = spawn(process.execPath, ['server.js'], {
    cwd: __dirname,
    env: { ...process.env, PORT: '0' },
    stdio: ['ignore', 'pipe', 'pipe']
  });
  let stderr = '';
  child.stderr.on('data', data => { stderr += data; });
  try {
    const url = await new Promise((resolve, reject) => {
      const timeout = setTimeout(() => reject(new Error('Preview failed to start: ' + stderr)), 10000);
      let output = '';
      child.stdout.on('data', data => {
        output += data;
        const match = output.match(/Local: (http:\/\/localhost:\d+)/);
        if (match) { clearTimeout(timeout); resolve(match[1].replace('localhost', '127.0.0.1')); }
      });
      child.once('error', error => { clearTimeout(timeout); reject(error); });
      child.once('exit', code => { clearTimeout(timeout); reject(new Error(`Preview exited ${code}: ${stderr}`)); });
    });
    for (const [route, mime, file] of [
      ['/', 'text/html', 'index.html'],
      ['/favicon.ico', 'image/x-icon', 'favicon.ico'],
      ['/favicon.png', 'image/png', 'favicon.png'],
      ['/assets/homepage.js', 'text/javascript', 'assets/homepage.js'],
      ['/styles.css', 'text/css', 'styles.css'],
      ['/fonts.css', 'text/css', 'fonts.css']
    ]) {
      const response = await fetch(url + route);
      assert.equal(response.status, 200, route);
      assert.ok(response.headers.get('content-type').startsWith(mime), route);
      assert.deepEqual(Buffer.from(await response.arrayBuffer()), fs.readFileSync(path.join('dist', file)), route);
      console.log(`GET ${route}: 200 ${mime}`);
    }
    assert.equal((await fetch(url + '/missing-file')).status, 404);
    assert.equal((await fetch(url + '/app.js')).status, 404);
    assert.equal(stderr, '');
    console.log('Production output starts in Node without browser globals; routes serve static bytes.');
  } finally {
    if (child.exitCode === null) {
      const exited = once(child, 'exit');
      child.kill();
      await exited;
    }
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
