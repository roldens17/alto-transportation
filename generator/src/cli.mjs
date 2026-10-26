#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const slugPattern = /^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/;
const [command, client, ...flags] = process.argv.slice(2);
const industry = flags[flags.indexOf('--industry') + 1];
const clientDir = client ? path.join(root, 'clients', client) : null;

function assertSlug(slug, label) {
  if (!slugPattern.test(slug ?? '')) throw new Error(`Invalid ${label}; use lowercase letters, numbers and hyphens.`);
}
function readConfig(filename) {
  const result = spawnSync('python3', ['-c',
    'import json,sys; import yaml; print(json.dumps(yaml.safe_load(open(sys.argv[1]))))', filename
  ], {encoding:'utf8'});
  if (result.status !== 0) throw new Error('YAML parser unavailable. Install PyYAML, or use the forthcoming bundled parser.');
  return JSON.parse(result.stdout);
}
function configFor(slug) {
  assertSlug(slug, 'client ID');
  const file = path.join(root,'clients',slug,'site.yaml');
  if (!fs.existsSync(file)) throw new Error(`Missing ${file}`);
  const cfg = readConfig(file);
  if (cfg.client?.id !== slug) throw new Error('Client ID does not match directory.');
  if (!slugPattern.test(cfg.client?.industry ?? '')) throw new Error('Invalid industry.');
  if (!fs.existsSync(path.join(root,'industries',cfg.client.industry,'defaults.yaml'))) throw new Error('Unsupported industry.');
  if (typeof cfg.modules?.booking !== 'boolean') throw new Error('modules.booking must be boolean.');
  if (cfg.modules?.payments && !cfg.modules?.booking) throw new Error('Payments require booking in v1.');
  return cfg;
}
function main() {
  if (command === 'validate') {
    configFor(client);
    console.log(`Valid client configuration: ${client}`);
  } else if (command === 'new') {
    assertSlug(client,'client ID');
    assertSlug(industry,'industry');
    if (!fs.existsSync(path.join(root,'industries',industry,'defaults.yaml'))) throw new Error('Unsupported industry.');
    fs.mkdirSync(clientDir,{recursive:true});
    const target=path.join(clientDir,'site.yaml');
    if(fs.existsSync(target)) throw new Error('Client already exists; refusing to overwrite.');
    fs.writeFileSync(target, `client:\n  id: ${client}\n  name: "REPLACE WITH BUSINESS NAME"\n  industry: ${industry}\nsite:\n  timezone: America/New_York\n  locale: en_US\nmodules:\n  booking: true\n  payments: true\nbooking:\n  mode: slot\npayments:\n  provider: woocommerce\n  currency: USD\n  test_mode: true\n`);
    console.log(`Created ${target}`);
  } else {
    console.error('Implemented: ks new <client> --industry cleaning | ks validate <client>');
    console.error('Build, dev, deploy, verify and rollback are not implemented yet.');
    process.exitCode=2;
  }
}
try { main(); } catch (error) { console.error(error.message); process.exitCode=1; }
