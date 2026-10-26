#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const client = process.argv[2];
if (!/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/.test(client ?? '')) {
  console.error('Supply a valid client ID.');
  process.exit(1);
}
const output = path.join(root, 'generated', client);
const manifest = JSON.parse(fs.readFileSync(path.join(output, 'manifest.json'), 'utf8'));
if (manifest.client !== client) throw new Error('Manifest client mismatch');
const expected = [
  'style.css', 'theme.json', 'functions.php', 'config.php',
  'templates/front-page.html', 'templates/page.html', 'templates/page-booking.html'
].map(name => `wp-content/themes/${client}/${name}`);
for (const relative of expected) {
  const expectedHash = manifest.files[relative];
  if (!/^[a-f0-9]{64}$/.test(expectedHash ?? '')) throw new Error(`Missing manifest hash: ${relative}`);
  const absolute = path.join(output, relative);
  const actual = crypto.createHash('sha256').update(fs.readFileSync(absolute)).digest('hex');
  if (actual !== expectedHash) throw new Error(`Artifact checksum mismatch: ${relative}`);
}
const theme = JSON.parse(fs.readFileSync(path.join(output, `wp-content/themes/${client}/theme.json`), 'utf8'));
if (theme.version !== 3 || !Array.isArray(theme.settings?.color?.palette)) throw new Error('Invalid theme.json');
const css = fs.readFileSync(path.join(output, `wp-content/themes/${client}/style.css`), 'utf8');
if (!/^Template: klickspark-base$/m.test(css)) throw new Error('Missing required parent theme');
console.log(`Verified ${expected.length} generated theme files and their SHA-256 checksums for ${client}`);
