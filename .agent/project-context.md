# CI strategy

- **Strategy:** `publication`. Follow [shared CI strategies](https://github.com/Ali1/personal-ops/blob/main/runbooks/ci-strategies.md) and [delivery workflow](https://github.com/Ali1/personal-ops/blob/main/runbooks/delivery-workflow.md).
- Before push/merge, run focused affected regressions, static analysis and changed-file standards as relevant. Auth/limiter changes require meaningful security regressions and consumer checks before publication.
- `.github/workflows/nightly-core.yml` runs unit/integration and PHPStan nightly for new default source; repeated attempts for the same SHA are deduplicated. `.github/workflows/weekly-style.yml` runs full coding standards weekly. Neither workflow gates a merge or publication; the manual `pr-checks.yml` workflow is checks-only.
- `.github/workflows/weekly-style.yml` runs complete coding standards every Sunday, including unchanged source. No browser suite is declared for this library; consumer applications own browser coverage.
- Dependency compatibility recommendations belong to relevant dependency/development changes. Existing security advisory channels and supported PHP/CakePHP constraints remain.

## Dependency security evidence

Dependency advisories are reported by the independent **Dependency security evidence collection** workflow (`dependency-security.yml`). It follows each actual nightly/manual functional attempt once, preserving its exact source and resolved graph where required; deduplicated functional attempts do not repeat audits. Raw security failures cannot turn the functional workflow tick red. Its exact-source `dependency-security-<sha>` artifact contains `receipt.json` and audit logs; a failed raw audit remains failed. Complete valid report collection can succeed without making findings fail the combined GitHub tick; unavailable tools/indexes and malformed/incomplete reports fail collection. This is not a clean-security certificate and cannot skip functional/backend/build/browser checks. Existing schedules, new-source selection, installation failures and release/security approvals remain in force. A functional pass is not a security pass.

## Post-deployment local verification

Follow the current [offer-first certification contract](https://github.com/Ali1/personal-ops/blob/main/runbooks/local-release-certification.md).
Use judgment during rapid iterations: merges and deployments do not each require
an offer or certification run. Keep deployment receipts and the uncertified queue,
then at a natural stopping point ask whether Ali is done and offer one consolidated
local verification pass. After his okay, freeze the latest intended committed SHA
and run only missing approved complete compatible categories. Silence or elapsed
time is not approval; do not keep reasking while an offer is pending or declined.
Retain ownership until outcome or accepted handoff. Superseded intermediate commits
need no repeated certification unless still independently deployed or relevant.
Quiet maintenance must not lose this follow-up; changed code needs new exact-SHA
evidence. Declined/unavailable coverage remains queued, never green. Use
`bin/certify-local-v2.ps1` for the supported categories in
`.github/local-certification-v2.json`; all other categories remain explicitly missing.
Legacy v1 statuses do not skip nightly work. Linux/database/browser/native results
require their actual scope; portable static analysis is separately declared.
