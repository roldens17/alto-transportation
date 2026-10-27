# Local-first client setup wizard (prototype)

Open `tools/client-setup.html` in a browser. It runs entirely client-side: no backend, tracking, hosting or secrets. Enter the public business name, a safe client ID, choose supported industry (currently **cleaning** only), and opt in to booking/quote configuration if desired. Payments are always off. Download `site.yaml` or copy its contents.

**Important:** The wizard only prepares a configuration file. It does **not** write to GitHub, create a client directory, install WordPress, enable production modules, provision pages, or deploy. A reviewer can import the downloaded file with `node generator/src/cli.mjs import <client-id> --file /path/to/site.yaml`. Import validates the client ID and config, refuses to overwrite an existing client config, and does not deploy. Then run `node generator/src/cli.mjs validate <client-id>` and `node generator/src/cli.mjs build <client-id>`. Keep private data and credentials out of Git.

Quote activation requires the separate host-specific release checklist `docs/QUOTE-RELEASE-GATE.md`. Booking activation requires its own runtime acceptance review. The wizard is not a substitute for the PR #14 onboarding and client launch checklist.

This is an internal prototype; browser download and clipboard behavior depend on the host/browser. Do not claim that it is publicly hosted or production ready.
