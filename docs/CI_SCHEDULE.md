

## Scheduled checks — 17 October 2026

Focused affected checks run locally before push/merge. GitHub workflows are checks-only and do not gate merges, deployments or package publication. UTC lanes:

- `nightly-core.yml`: `19 1 * * *` (core); existing contained/synthetic commands only.
- `weekly-style.yml`: `19 4 * * 0` (style); existing contained/synthetic commands only.
- Local prepush/premerge checks are impact-based: changed-PHP PHPCS; affected PHPStan/dependencies (full project where tooling requires); affected unit/integration/shared callers and browser journeys. Focused evidence is not full regression. Package compatibility remains development/package-change-only.
