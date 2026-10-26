# KlickSpark GitHub-only CI and Build Artifacts

This workflow lets KlickSpark review and download **generated WordPress child-theme files** directly from GitHub Actions without using a local computer.

## GitHub Actions

The `KlickSpark Generator` workflow runs on relevant pull requests, main-branch changes, and manual dispatch. It:

1. Installs Node.js and a pinned PyYAML dependency.
2. Validates the Fabulous Cleaning YAML and runs CLI tests.
3. Generates a client child theme.
4. PHP-lints generated PHP and verifies each generated file's SHA-256 against the manifest.
5. Validates the Docker Compose syntax using placeholder **local-only** credentials without starting containers.
6. Uploads `fabulous-cleaning-wordpress-theme` as a GitHub Actions artifact (14-day retention).

To download on iPhone: open the GitHub repository → Actions → KlickSpark Generator → a successful run → Artifacts. GitHub may require a signed-in browser to download.

## Safety and limits

- An uploaded artifact is **not a deployed website**. It is a child theme and manifest only.
- CI validates Compose configuration but does not start WordPress, MariaDB, or a payment gateway.
- No real credentials or payment keys are used. Production deployment is not configured.
- Existing Alto Transportation code is not modified by this workflow.
- The generator currently relies on Python/PyYAML and lacks JSON Schema validation.
- Do not merge an unsuccessful CI run or treat artifact generation as proof that booking works.
