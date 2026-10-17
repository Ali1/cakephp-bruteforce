# CI strategy

- **Strategy:** `publication`. Follow [shared CI strategies](https://github.com/Ali1/personal-ops/blob/main/runbooks/ci-strategies.md) and [delivery workflow](https://github.com/Ali1/personal-ops/blob/main/runbooks/delivery-workflow.md).
- Before push/merge, run focused affected regressions, static analysis and changed-file standards as relevant. Auth/limiter changes require meaningful security regressions and consumer checks before publication.
- `.github/workflows/nightly.yml` runs unit/integration tests and PHPStan nightly for new default source; matching attempted source is deduplicated within this workflow/lane. Manual dispatch retries tests and runs full standards.
- `.github/workflows/weekly.yml` runs complete coding standards every Monday, including unchanged source. No browser suite is declared for this library; consumer applications own browser coverage.
- Dependency compatibility recommendations belong to relevant dependency/development changes. Existing security advisory channels and supported PHP/CakePHP constraints remain.
