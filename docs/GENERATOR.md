# KlickSpark Internal Generator

The generator is an internal tool for building separate WordPress client sites, **not** a SaaS or theme for sale.

## Current implementation

- `generator/src/cli.mjs`: first CLI scaffold and basic client validation.
- `clients/fabulous-cleaning/site.yaml`: example cleaning client configuration.
- `industries/cleaning/defaults.yaml`: industry defaults.

## Usage

From repository root:

```sh
node generator/src/cli.mjs new example-cleaning --industry cleaning
node generator/src/cli.mjs validate fabulous-cleaning
```

Validation currently requires Python 3 with PyYAML installed. This is a temporary bootstrap dependency; replace with a locked Node YAML dependency and JSON Schema validation in the next milestone.

## Not yet implemented

`ks build`, `ks dev`, `ks deploy`, `ks verify`, `ks rollback`, complete schema validation, theme scaffolding, WordPress provisioning, booking and payment integrations, tests and CI/CD. These commands intentionally fail rather than pretending to deploy.

## Safety

Never put credentials in client YAML. Never overwrite client-edited WordPress content during normal deployments. Production release requires explicit approval. See `docs/ARCHITECTURE_AUDIT.md` for blockers before accepting live payments.
