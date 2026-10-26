# PHPloy Roadmap

Path from `5.0.0-rc` → `5.0.0` stable → `5.1.0`. Last updated 2026-10-26.

## 5.0.0 stable — release blockers

- [ ] **Bump version** `5.0.0-rc` → `5.0.0` in the 5 spots (composer.json, `src/PHPloy.php` `$version`, `@version` docblock, readme header + "What's New" heading).
- [x] **Add `LICENSE` file** — decided not to (noise; `composer.json` already declares MIT). GitHub's repo page shows "No license" (it only reads the LICENSE file), but Packagist still shows MIT from `composer.json`.
- [x] **Fix the false "Code Quality Tools" claim** — added `squizlabs/php_codesniffer` (^4.0) + `phpstan/phpstan` (^2.3) to `require-dev`; `composer lint` (PSR-12) and `composer analyze` (level 5) both pass. Claim is now true.
- [x] **Reconcile PHP version** — runtime `^8.0`, dev/test `8.4`. Fixed `contributing.md` (said 8.2+) and `memory-bank/projectbrief.md` (said 8.2 dev); `composer.json`, `readme.md`, `Dockerfile.test`, and workflows were already correct.
- [ ] Rebuild `dist/phploy.phar`, assert `--version` = `5.0.0`, tag `v5.0.0`.

## CI/CD — right after stable

- [x] **Add a CI test workflow** (biggest gap). Run the Docker test suite (`docker compose up` + `pest`) on push/PR.
- [x] **Release workflow: create the GitHub Release.** Today `release.yml` only publishes to Pages; add a step to create the release with generated notes and attach `dist/phploy.phar`.
- [ ] **Dependabot** for composer deps (security updates).
- [ ] **Issue + PR templates** to keep the backlog tidy going forward.

## 5.1.0 — headline: atomic-ish uploads

- [ ] **#395 — upload to `*.tmp` then rename** so the live server never serves a half-uploaded file. Revive the closed PR #396 approach.
- [ ] **Unit tests for Config/Git parsing** — the quickmode reserved-char bug (`.phploy`, env-var precedence) has zero regression coverage.
- [ ] *(optional)* Extract `FileFilter` (filter/remove-base/included) and `RemoteOps` (purge/copy/hooks) out of the 1060-line `Deployment.php`.

## Backlog triage

- [ ] Close **#376** (auto-closes on push — commit says `fixes #376`).
- [ ] Close **#381** (PowerShell env-var docs) — trivial doc fix.
- [ ] Close as `wontfix`: **#369** WebDAV, **#340** web UI, **#259** tarball.
- [ ] Label **#353** (tag support) `help wanted` + defer.

## Docs / DX hygiene

- [x] **Consolidate three sample configs** — root `phploy.ini` is the single source of truth; `--init` reads it (`Config::createSampleConfig()` no longer hardcodes), the readme links to it, and `build` bundles it into the phar.
- [x] **Retire `memory-bank/`** — deleted (gitignored, Cline-era, stale: claimed 5.0.0 / PHP 8.2). The `phploy` skill + `ROADMAP.md` are the agent sources of truth.
- [x] **Fix `contributing.md`** — PHP version fixed (8.0+ runtime / 8.4 dev). Verified `composer lint` passes and `php build`/`php -d phar.readonly=0 build` are the correct documented invocations (the `build` file is non-executable, so `php build` is right) — the "lint broken / build not executable" sub-claims were stale.
- [x] **Fix Docker fresh-clone bootstrap** — `composer test`/`test:exec` now install deps in-container when `vendor/` is missing; added `.dockerignore` so the image build stops copying the host `vendor/` into the build context.
- [ ] Replace `sleep(2)` in tests with polling.
