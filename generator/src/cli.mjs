#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const slugPattern = /^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/;
const [command, client, ...flags] = process.argv.slice(2);
const has = flag => flags.includes(flag);
const option = name => { const i = flags.indexOf(name); return i >= 0 ? flags[i + 1] : undefined; };
const clientDir = client ? path.join(root, 'clients', client) : null;

function assertSlug(slug, label) {
  if (!slugPattern.test(slug ?? '')) throw new Error(`Invalid ${label}; use lowercase letters, numbers and hyphens.`);
}
function readYaml(filename) {
  const result = spawnSync('python3', ['-c',
    'import json,sys,yaml; print(json.dumps(yaml.safe_load(open(sys.argv[1],encoding="utf-8"))))', filename
  ], { encoding: 'utf8' });
  if (result.status !== 0) throw new Error(`Unable to parse YAML: ${filename}. Install Python 3 and PyYAML. ${result.stderr.trim()}`);
  return JSON.parse(result.stdout);
}
function merge(base, override) {
  if (!base || typeof base !== 'object' || Array.isArray(base) || !override || typeof override !== 'object' || Array.isArray(override)) return override;
  const out = { ...base };
  for (const [key, value] of Object.entries(override)) out[key] = key in out ? merge(out[key], value) : value;
  return out;
}
function load(slug) {
  assertSlug(slug, 'client ID');
  const filename = path.join(root, 'clients', slug, 'site.yaml');
  if (!fs.existsSync(filename)) throw new Error(`Missing client config: ${filename}`);
  const clientConfig = readYaml(filename);
  if (clientConfig?.client?.id !== slug) throw new Error('Client ID does not match directory.');
  const industry = clientConfig?.client?.industry;
  assertSlug(industry, 'industry');
  const defaultsFile = path.join(root, 'industries', industry, 'defaults.yaml');
  if (!fs.existsSync(defaultsFile)) throw new Error(`Unsupported industry: ${industry}`);
  const config = merge(readYaml(defaultsFile), clientConfig);
  if (!config.client?.name || typeof config.client.name !== 'string') throw new Error('client.name is required.');
  if (typeof config.modules?.booking !== 'boolean') throw new Error('modules.booking must be boolean.');
  if (typeof config.modules?.payments !== 'boolean') throw new Error('modules.payments must be boolean.');
  if (config.modules.payments && !config.modules.booking) throw new Error('Payments require booking in v1.');
  if (config.modules.booking && !['slot', 'route'].includes(config.booking?.mode)) throw new Error('Unsupported booking mode.');
  if (config.modules.payments && config.payments?.provider !== 'woocommerce') throw new Error('Only WooCommerce payments are supported.');
  for (const key of ['primary', 'accent', 'background', 'text']) {
    if (config.branding?.[key] && !/^#[a-fA-F0-9]{6}$/.test(config.branding[key])) throw new Error(`Invalid branding.${key}`);
  }
  return config;
}
function atomicWrite(filename, data) {
  fs.mkdirSync(path.dirname(filename), { recursive: true });
  fs.writeFileSync(filename, data, { flag: 'wx' });
}
function build(slug) {
  const cfg = load(slug);
  const out = path.join(root, 'generated', slug);
  if (fs.existsSync(out)) {
    if (!has('--force')) throw new Error('Build output exists. Remove generated output or pass --force to replace generated files.');
    fs.rmSync(out, { recursive: true, force: true });
  }
  const themeDir = path.join(out, 'wp-content', 'themes', slug);
  fs.mkdirSync(themeDir, { recursive: true });
  const palette = [
    ['base', cfg.branding?.background ?? '#FFFFFF'],
    ['contrast', cfg.branding?.text ?? '#172C2A'],
    ['primary', cfg.branding?.primary ?? '#176B66'],
    ['secondary', cfg.branding?.accent ?? '#D8F0E9']
  ].map(([slug, color]) => ({ slug, name: slug[0].toUpperCase() + slug.slice(1), color }));
  const theme = { $schema: 'https://schemas.wp.org/trunk/theme.json', version: 3,
    settings: { color: { palette }, typography: { fluid: true } },
    styles: { color: { background: 'var(--wp--preset--color--base)', text: 'var(--wp--preset--color--contrast)' } }
  };
  const style = `/*
Theme Name: ${cfg.client.name.replace(/[\r\n*\/]/g, '')}
Template: klickspark-base
Author: KlickSpark Media
Description: Generated client child theme. Client content is managed in WordPress.
Version: 0.1.0
*/
:root { --ks-radius: 10px; }
.wp-block-button__link { border-radius: var(--ks-radius); }
`;
  const bookingConfig = cfg.booking ?? {};
  const phpConfig = {
    modules: {
      booking: cfg.modules.booking, fleet: !!cfg.modules.fleet, places: !!cfg.modules.places,
      activities: !!cfg.modules.activities
    },
    booking: {
      mode: bookingConfig.mode ?? 'slot',
      payment: cfg.modules.payments ? 'woo' : 'none',
      currency: cfg.payments?.currency ?? 'USD',
      min_lead_hours: bookingConfig.min_lead_hours ?? 24,
      max_days_ahead: bookingConfig.max_days_ahead ?? 60,
      time_step_minutes: bookingConfig.time_step_minutes ?? 30,
      require_flight_no: false, allow_stops: false, round_trip: false,
      product_id: 0
    },
    post_types: {
      ks_service: { singular: 'Service', plural: 'Services', icon: 'admin-tools' },
      ks_practitioner: { singular: 'Crew', plural: 'Crews', icon: 'groups', public: false },
      ks_timeoff: { singular: 'Time Off', plural: 'Time Off', icon: 'calendar-alt', public: false }
    }
  };
  const json = JSON.stringify(phpConfig, null, 2);
  const php = `<?php
/** Generated by KlickSpark. Edit the source YAML, not this file. */
defined('ABSPATH') || exit;
return json_decode(<<<'KS_CONFIG_JSON'
${json}
KS_CONFIG_JSON
, true, 512, JSON_THROW_ON_ERROR);
`;
  const safeTitle = cfg.client.name.replace(/[<>&"]/g, '');
  const header = '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->';
  const footer = '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';
  const home = `${header}
<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} --><main class="wp-block-group">
<!-- wp:heading {"level":1} --><h1>${safeTitle}</h1><!-- /wp:heading -->
<!-- wp:paragraph --><p>Professional cleaning services. Update this placeholder copy in WordPress before publishing.</p><!-- /wp:paragraph -->
<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/booking/">Request a Cleaning</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
<!-- wp:post-content /-->
</main><!-- /wp:group -->
${footer}
`;
  const page = `${header}
<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} --><main class="wp-block-group">
<!-- wp:post-title {"level":1} /--><!-- wp:post-content /-->
</main><!-- /wp:group -->
${footer}
`;
  const booking = `${header}
<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} --><main class="wp-block-group">
<!-- wp:post-title {"level":1} /--><!-- wp:post-content /-->
<!-- wp:ks/appointment-picker {"heading":"Book a cleaning appointment"} /-->
</main><!-- /wp:group -->
${footer}
`;
  const files = {
    'style.css': style,
    'theme.json': JSON.stringify(theme, null, 2) + '\n',
    'functions.php': "<?php\n/** Generated child theme: business logic belongs in the shared framework. */\ndefined('ABSPATH') || exit;\n",
    'config.php': php,
    'templates/front-page.html': home,
    'templates/page.html': page,
    'templates/page-booking.html': booking
  };
  // Client-specific presentation overlays; shared framework and booking behavior remain unchanged.
  const visualDir = path.join(root, 'clients', slug, 'theme');
  for (const relative of ['style.css', 'templates/front-page.html']) {
    const source = path.join(visualDir, relative);
    if (fs.existsSync(source)) {
      if (relative === 'style.css') files[relative] += '\n' + fs.readFileSync(source, 'utf8') + '\n';
      else files[relative] = fs.readFileSync(source, 'utf8') + '\n';
    }
  }
  for (const [name, contents] of Object.entries(files)) atomicWrite(path.join(themeDir, name), contents);
  const manifest = {
    client: slug, industry: cfg.client.industry, generator_version: '0.2.0',
    source_config_sha256: crypto.createHash('sha256').update(fs.readFileSync(path.join(root,'clients',slug,'site.yaml'))).digest('hex'),
    files: Object.fromEntries(Object.entries(files).map(([name, contents]) =>
      [`wp-content/themes/${slug}/${name}`, crypto.createHash('sha256').update(contents).digest('hex')]))
  };
  atomicWrite(path.join(out, 'manifest.json'), JSON.stringify(manifest, null, 2) + '\n');
  console.log(`Generated ${themeDir}`);
  console.log('IMPORTANT: booking, payment, page seeding and deployment are not yet production-ready.');
}
function create(slug) {
  assertSlug(slug, 'client ID');
  const industry = option('--industry');
  assertSlug(industry, 'industry');
  if (!fs.existsSync(path.join(root, 'industries', industry, 'defaults.yaml'))) throw new Error('Unsupported industry.');
  const dir = path.join(root, 'clients', slug);
  fs.mkdirSync(dir, { recursive: true });
  const target = path.join(dir, 'site.yaml');
  atomicWrite(target, `client:\n  id: ${slug}\n  name: "REPLACE WITH BUSINESS NAME"\n  industry: ${industry}\nsite:\n  timezone: America/New_York\n  locale: en_US\nmodules:\n  booking: true\n  payments: true\nbooking:\n  mode: slot\npayments:\n  provider: woocommerce\n  currency: USD\n  test_mode: true\n`);
  console.log(`Created ${target}`);
}
try {
  if (command === 'new') create(client);
  else if (command === 'validate') { load(client); console.log(`Valid client configuration: ${client}`); }
  else if (command === 'build') build(client);
  else { console.error('Implemented: ks new, ks validate, ks build. Dev/deploy/verify/rollback are not implemented.'); process.exitCode = 2; }
} catch (error) { console.error(error.message); process.exitCode = 1; }
