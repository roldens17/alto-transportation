import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const cli = path.join(root, 'generator/src/cli.mjs');
const output = path.join(root, 'generated/fabulous-cleaning');
const run = (...args) => spawnSync(process.execPath, [cli, ...args], {cwd: root, encoding:'utf8'});
test('validates Fabulous Cleaning', () => {
  const r = run('validate', 'fabulous-cleaning');
  assert.equal(r.status, 0, r.stderr);
});
test('rejects unsafe client paths', () => {
  const r = run('build', '../outside', '--force');
  assert.notEqual(r.status, 0);
});
test('builds deterministic child theme and preserves output by default', () => {
  const first = run('build','fabulous-cleaning','--force');
  assert.equal(first.status, 0, first.stderr);
  const manifest = fs.readFileSync(path.join(output,'manifest.json'),'utf8');
  const css = fs.readFileSync(path.join(output,'wp-content/themes/fabulous-cleaning/style.css'),'utf8');
  assert.match(css,/Template: klickspark-base/);
  const clientConfig = fs.readFileSync(path.join(output,'wp-content/themes/fabulous-cleaning/config.php'),'utf8');
  assert.match(clientConfig,/"payment": "none"/);
  assert.match(clientConfig,/"booking": true/);
  const quoteTemplate = fs.readFileSync(path.join(output,'wp-content/themes/fabulous-cleaning/templates/page-quote-request.html'),'utf8');
  assert.match(quoteTemplate,/\[klickspark_quote_form services=/);
  assert.match(quoteTemplate,/Standard Cleaning\|Deep Cleaning\|Move-In \/ Move-Out/);
  assert.match(quoteTemplate,/does not book or charge you/);
  assert.match(css,/\.fc-quote-page/);
  const builtManifest = JSON.parse(manifest);
  assert.ok(builtManifest.files['wp-content/themes/fabulous-cleaning/templates/page-quote-request.html']);
  const second = run('build','fabulous-cleaning');
  assert.notEqual(second.status, 0);
  const third = run('build','fabulous-cleaning','--force');
  assert.equal(third.status, 0, third.stderr);
  assert.equal(fs.readFileSync(path.join(output,'manifest.json'),'utf8'), manifest);
  fs.rmSync(output,{recursive:true,force:true});
});

test('imports wizard YAML safely and rejects overwrite or mismatched IDs', () => {
  const slug = 'ci-wizard-import-test';
  const dir = path.join(root, 'clients', slug);
  const source = path.join(root, 'generator', 'test', 'ci-wizard-import.yaml');
  const yaml = 'client:\\n  id: ' + slug + '\\n  name: "Example Cleaning"\\n  industry: cleaning\\nmodules:\\n  booking: false\\n  payments: false\\n  quotes: false\\nbooking:\\n  mode: slot\\n';
  try {
    assert.equal(fs.existsSync(dir), false);
    fs.writeFileSync(source, yaml);
    const imported = run('import', slug, '--file', source);
    assert.equal(imported.status, 0, imported.stderr);
    assert.equal(fs.readFileSync(path.join(dir, 'site.yaml'), 'utf8'), yaml);
    assert.equal(run('validate', slug).status, 0);
    assert.notEqual(run('import', slug, '--file', source).status, 0);
    assert.notEqual(run('import', '../unsafe', '--file', source).status, 0);
  } finally {
    fs.rmSync(source, {force:true});
    fs.rmSync(dir, {recursive:true,force:true});
  }
});
