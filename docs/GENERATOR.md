# KlickSpark Internal Website Generator

The generator builds child-theme artifacts for separate WordPress client installations. It is not a SaaS, a theme marketplace or a replacement for client-specific content editing.

## Implemented

- `ks new <client> --industry cleaning` scaffolds a new YAML client config (does not overwrite existing config).
- `ks validate <client>` loads industry defaults, merges client settings and performs initial validation.
- `ks build <client>` creates a client child theme and SHA-256 manifest under `generated/<client>/`.
- `ks build <client> --force` replaces **generated build output only**, never client source config or WordPress database content.
- Generated themes include `style.css`, `theme.json`, `functions.php`, `config.php` and basic block templates.
- Generator test workflow runs on pull requests.

## Requirements

Node.js 22, Python 3, and PyYAML 6.0.3. Install with `python3 -m pip install PyYAML==6.0.3`.

## Commands

```bash
node generator/src/cli.mjs validate fabulous-cleaning
node generator/src/cli.mjs build fabulous-cleaning
node generator/src/cli.mjs build fabulous-cleaning --force
node --test generator/test/*.test.mjs
```

Build output: `generated/fabulous-cleaning/wp-content/themes/fabulous-cleaning/`. The existing parent theme remains `wordpress/klickspark-base/`.

## Important restrictions

**Not yet production ready.** The generated booking template uses the existing `ks/appointment-picker` block, which does not yet support cleaning-specific property details, address validation, extras or dynamically calculated prices. The source slot booking engine still needs concurrency and buffer fixes. Booking and payment must not be advertised as live until those features are implemented and tested.

The generator does not install WordPress, create pages or WooCommerce products, seed crews/services, configure payment gateways, or deploy to staging/production. `ks dev`, `ks deploy`, `ks verify` and `ks rollback` deliberately return an error. A future milestone should replace the Python YAML bootstrap with a locked Node parser and full JSON Schema.

Do not store secrets in client YAML or overwrite customer-edited WordPress content. Production releases require explicit approval.
