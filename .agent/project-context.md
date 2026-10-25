# CI strategy

- **Strategy:** `publication`. Follow [shared CI strategies](https://github.com/Ali1/personal-ops/blob/main/runbooks/ci-strategies.md) and [delivery workflow](https://github.com/Ali1/personal-ops/blob/main/runbooks/delivery-workflow.md).
- Before push/merge, run focused affected regressions, static analysis and changed-file standards as relevant. Auth/limiter changes require meaningful security regressions and consumer checks before publication.
- `.github/workflows/nightly-core.yml` runs unit/integration, PHPStan and Composer advisory checks nightly for new default source; repeated attempts for the same SHA are deduplicated. `.github/workflows/weekly-style.yml` runs full coding standards weekly. Neither workflow gates a merge or publication; the manual `pr-checks.yml` workflow is checks-only.
- `.github/workflows/weekly-style.yml` runs complete coding standards every Sunday, including unchanged source. No browser suite is declared for this library; consumer applications own browser coverage.
- Dependency compatibility recommendations belong to relevant dependency/development changes. Existing security advisory channels and supported PHP/CakePHP constraints remain.

## Dependency security evidence

Dependency advisories are reported by the separate **Dependency security audits** job (`dependency-security`). Its exact-source `dependency-security-<sha>` artifact contains `receipt.json` and audit logs; a failed audit remains failed and cannot skip functional/backend/build/browser checks. Existing schedules, new-source selection, installation failures and release/security approvals remain in force. A functional pass is not a security pass.

## Post-deployment local verification

The delivery owner must follow the current [offer-first certification contract](https://github.com/Ali1/personal-ops/blob/main/runbooks/local-release-certification.md).
Deploy promptly with focused safeguards; freeze the actually deployed SHA, retain
ownership and the uncertified queue, and **offer** local certification for missing
categories afterward. Quiet maintenance does not suppress this offer; consolidate
related deployments in one offer. An offer is not permission to start full suites.
Run only approved complete compatible recipes, then record outcomes or an accepted
handoff. Declined/unavailable coverage remains queued, never green. Use
`bin/certify-local-v2.ps1` for the supported categories in
`.github/local-certification-v2.json`; all other categories remain explicitly missing.
Legacy v1 statuses do not skip nightly work. Linux/database/browser/native results
require their actual scope; portable static analysis is separately declared.
