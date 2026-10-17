# CI strategy

- **Strategy:** `publication`. Follow [shared CI strategies](https://github.com/Ali1/personal-ops/blob/main/runbooks/ci-strategies.md) and [delivery workflow](https://github.com/Ali1/personal-ops/blob/main/runbooks/delivery-workflow.md).
- Before push/merge, run focused affected regressions, static analysis and changed-file standards as relevant. Auth/limiter changes require meaningful security regressions and consumer checks before publication.
- `.github/workflows/nightly-core.yml` runs unit/integration, PHPStan and Composer advisory checks nightly for new default source; repeated attempts for the same SHA are deduplicated. `.github/workflows/weekly-style.yml` runs full coding standards weekly. Neither workflow gates a merge or publication; the manual `pr-checks.yml` workflow is checks-only.
- `.github/workflows/weekly-style.yml` runs complete coding standards every Sunday, including unchanged source. No browser suite is declared for this library; consumer applications own browser coverage.
- Dependency compatibility recommendations belong to relevant dependency/development changes. Existing security advisory channels and supported PHP/CakePHP constraints remain.
