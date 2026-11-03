# Contributing

Thank you for helping improve `maatify/mailer`.

## Package identity

`maatify/mailer` is a PHP library that renders Twig e-mail templates and sends them through SMTP with PHPMailer. Its public entry point is `Maatify\Mailer\Service\Mailer`. The canonical contract is [MAILER_PACKAGE_REFERENCE.md](MAILER_PACKAGE_REFERENCE.md); a change to the public API or the documented behavior updates it in the same pull request (a regression test checks that it lists every public member, type, enum case and setting).

## Package boundaries

- In scope: rendering the library's e-mail templates, building the message, sending it through SMTP, reporting whether the server accepted it, and logging a failure without recipient data, message content or exception text.
- Out of scope: the application's own templates, user management, queues and retries, and storing your SMTP credentials. Keep contributions inside these boundaries.
- The public API and namespace of the library are a compatibility contract. Do not change them as a side effect of another change.

## Ways to contribute

- Report a bug or propose an improvement through a GitHub issue.
- Send a pull request that fixes a bug, adds tests, or improves documentation.
- Report a security issue **privately**, never through a public issue or pull request: follow [SECURITY.md](SECURITY.md).

## Requirements

- PHP `>=8.4.27 <8.5.0 || >=8.5.12 <8.6.0` (the declared `require.php` constraint) with `ext-openssl`.
- Composer 2.10 or newer.
- `composer.lock` is **not** committed; it is ignored by `.gitignore`. Resolve dependencies with `composer update`.

## Local verification

Install the dependencies and run the checks that CI runs:

```bash
composer update
composer validate --strict
composer analyse
composer test
```

CI runs these required jobs, and the `CI gate` check aggregates them: the `Verify` matrix, the logger compatibility matrix, the release artifact verification and `Workflow lint`.

`scripts/ci/verify.sh` runs everything the `Verify` matrix runs, in latest or lowest dependency mode: Composer validation and resolution, platform requirements, the strict PSR-4 autoload check, the PHP patch floor and `require.php` boundary checks, syntax, whitespace, PHPStan, the test suites, smoke execution of the examples against a local SMTP stand-in, an inspection of the distribution archive (required files, excluded files and document links), the Consumer Verification Harness (a clean Composer consumer that installs the package from the committed tree and sends mail through a local SMTP stand-in) and `composer audit`:

```bash
scripts/ci/verify.sh latest
scripts/ci/verify.sh lowest
```

The harness can also be run on its own with `harness/run.sh latest` (or `lowest`); it packages the committed tree (`git archive HEAD`), so commit your changes before running it. The logger compatibility check runs the tests against selected `maatify/logger` releases (CI uses 5.0.1, 5.1.0, 5.1.2 and 7.0.0; the local script also covers 5.1.1):

```bash
composer test:logger-matrix
```

Before a release, `scripts/release/verify-artifact.sh <ref>` verifies the candidate artifact of an exact commit (archive content and links, `composer validate --strict`, the harness in both modes) and prints a record of the target, commit, tree and results. CI runs it on the checked-out commit and records the result in the job summary. A qualification run requires a supported PHP patch and refuses Composer platform overrides; `RELEASE_VERIFY_DRY_RUN=1` allows a local rehearsal whose record is labelled `DRY RUN` and never reports `PASS`. After a release is published, the `Verify published release` workflow (manual run, inputs: version and expected commit SHA) installs it from Packagist on supported PHP versions with no platform override and runs `scripts/release/verify-published.sh`, which runs in an isolated Composer environment (temporary home and cache, no inherited `COMPOSER_*` variables, Packagist as the only repository) and checks the install source Composer actually used (`installation-source` must be `dist`), the dist URL and references, the installed files and the public types (`VERIFY_PUBLISHED_DRY_RUN=1` allows a local rehearsal that never reports `PASS`).

The gate checks the PHP patch floor of the interpreter it runs on, so use a PHP version within the declared constraint. `Workflow lint` is not part of `verify.sh`; run it locally with the same pinned `actionlint` version as CI (`ACTIONLINT_VERSION` in `.github/workflows/ci.yml` is the source of truth):

```bash
actionlint .github/workflows/*.yml
```

## Tests

- Every bug fix needs a regression test that fails without the fix, in the suite that fits it: `tests/Regression` for guards and unit-level behavior, `tests/System` when the defect shows through the public API and the SMTP traffic.
- New observable behavior needs a system-level test through the public API (`tests/System`).
- Tests never send real mail. They use the local SMTP stand-in in `tests/Support` (implicit TLS, a per-run self-signed certificate); each SMTP test scenario runs its send in its own PHP process. Tests that check logging must assert that a log entry was actually written, and use canary values to prove nothing sensitive is logged.
- This library has no database or other repository-controlled service that CI could provision, so there are no Integration suites. The SMTP server is externally controlled in production; it is verified deterministically with the local stand-in, and any live check against a real server is a separate, controlled verification that is never part of baseline CI.
- PHPStan runs at level `max` with no baseline and no suppressions; fix the underlying type issue instead.

## Pull requests

- Open one pull request per change, targeting the default branch (or the release branch the maintainers name), with a clear description of what changed and why.
- Keep the change focused: do not mix unrelated fixes, refactors, or formatting.
- Discuss any change to the public API, the supported versions or the package boundaries in an issue first.
- CI must pass; the `CI gate` check is the one that decides.
- Do not include secrets, tokens, `.env` files, or logs.
