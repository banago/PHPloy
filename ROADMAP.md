# PHPloy Roadmap

Path from `5.0.0-rc` → `5.0.0` stable → `5.1.0`. Last updated 2026-10-26.

## 5.0.0 stable — release blockers

- [ ] **Bump version** `5.0.0-rc` → `5.0.0` in the 5 spots (composer.json, `src/PHPloy.php` `$version`, `@version` docblock, readme header + "What's New" heading).
- [ ] **Add `LICENSE` file** — MIT is declared in `composer.json` but no `LICENSE` exists in the repo.
- [x] **Fix the false "Code Quality Tools" claim** — added `squizlabs/php_codesniffer` (^4.0) + `phpstan/phpstan` (^2.3) to `require-dev`; `composer lint` (PSR-12) and `composer analyze` (level 5) both pass. Claim is now true.
- [ ] **Reconcile PHP version** — composer `^8.0`, contributing "8.2+", readme "8.0+ / 8.4 dev", memory-bank "8.2 dev". Pick one truth (runtime `^8.0`, dev/test `8.4`).
- [ ] Rebuild `dist/phploy.phar`, assert `--version` = `5.0.0`, tag `v5.0.0`.

## CI/CD — right after stable

- [ ] **Add a CI test workflow** (biggest gap). Run the Docker test suite (`docker compose up` + `pest`) on push/PR.
- [ ] **Release workflow: create the GitHub Release.** Today `release.yml` only publishes to Pages; add a step to create the release with generated notes and attach `dist/phploy.phar`.
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

- [ ] **Consolidate three sample configs** (root `phploy.ini`, readme sample, `Config::createSampleConfig()`) into one source of truth.
- [ ] **Refresh or retire `memory-bank/`** (stale: claims 5.0.0, PHP 8.2, no submodule-deprecation warning).
- [ ] **Fix `contributing.md`** — wrong PHP version, tells users to run `composer lint` (broken) and `php build` (not executable).
- [ ] **Fix Docker fresh-clone bootstrap** — `.:/app` bind-mount shadows the image's `vendor/`; a clean clone can't `composer test`.
- [ ] Replace `sleep(2)` in tests with polling.
