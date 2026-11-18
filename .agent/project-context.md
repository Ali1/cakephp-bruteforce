# CI strategy

- **Strategy:** `publication`. Follow [shared CI strategies](https://github.com/Ali1/personal-ops/blob/main/runbooks/ci-strategies.md) and [delivery workflow](https://github.com/Ali1/personal-ops/blob/main/runbooks/delivery-workflow.md).
- Before push/merge, run focused affected regressions, static analysis and changed-file standards as relevant. Auth/limiter changes require meaningful security regressions and consumer checks before publication.
- Dependency compatibility recommendations belong to relevant dependency/development changes. Existing security advisory channels and supported PHP/CakePHP constraints remain.

## Dependency security evidence

Dependency security evidence is collected independently by scheduled/manual `dependency-security.yml` for new default source, with the existing UTC cadence. It resolves a fresh Composer graph without executing package scripts/plugins, retains exact-source raw audit reports, and fails on incomplete collection. A successful collection is not a clean-security or application-test claim.

## Local application testing

Application tests, static analysis and style checks run in the developing agent's workspace, not GitHub Actions. Follow the [shared delivery workflow](https://github.com/Ali1/personal-ops/blob/main/runbooks/delivery-workflow.md) for affected checks and the [local certification policy](https://github.com/Ali1/personal-ops/blob/main/runbooks/local-release-certification.md) for an explicitly approved comprehensive pass. Keep exact-source and scope receipts honest; no automatic comprehensive follow-up is implied by deployment.

The existing local runners and `.github/local-certification-v2.json` remain available. Build, release, security and operational controls are separate from application testing.

## Native local certification — 17 November 2026

Run `bin/certify-local.ps1 -Check phpunit` for the complete existing native unit recipe from a fresh committed archive. `bin/certify-local-v2.ps1` publishes approved exact-source categories under the shared Personal Ops contract. These database-free fixtures require no Docker, MariaDB or separate environment. Package compatibility fixtures remain available locally; publication controls remain separate from application testing.
The local library recipe uses `.github/local-test-composer.lock` to pin the existing reviewed development graph; it does not change the package dependency constraints or publish a root package lock.
