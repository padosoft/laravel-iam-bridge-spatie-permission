---
title: Troubleshooting
description: Symptoms and fixes — no mismatches ever logged, everything mismatches, false-zero diffs, scanner finds nothing, mode change ignored, and shadow latency.
---

# Troubleshooting

Symptom → likely cause → fix. Most issues come from configuration cache, a missing Spatie trait, or a
mismatch between the `application` prefix and the registered manifest.

## No mismatches are ever logged

::: callout info "Most often: the observer isn't registered, or there genuinely are none"
:::

- **`mode` isn't `shadow`.** `ShadowGate` is registered only when `IAM_SPATIE_MODE=shadow`. Check the
  resolved value (`php artisan tinker` → `config('iam-spatie.mode')`) and `config:clear` if you cache config.
- **The log channel is silent.** Verify `IAM_SPATIE_MISMATCH_CHANNEL` points at a channel defined in
  `config/logging.php` at `level: warning` or lower.
- **No `Gate` checks are happening on the path you're testing.** `ShadowGate` only fires on `Gate`/`can()`
  checks. Code paths that don't authorize produce no records.
- **They genuinely agree.** A clean diff is the goal — confirm with a deliberately divergent test (grant a
  permission in Spatie that IAM lacks) to prove the pipe works.

## Everything mismatches

- **`application` prefix ≠ registered app key.** If `IAM_SPATIE_APP=billing` but the manifest was registered
  under `legacy`, every `full_key` (`billing:...`) is unknown to IAM → all deny → mass
  `spatie_allow_iam_deny`. Align `IAM_SPATIE_APP` with the registered `app.key`.
- **Manifest not registered (or not yet propagated).** Run `iam:app:register` and confirm the app exists on
  the server before reading the diff.
- **Slug drift.** A permission renamed in Spatie after registration slugs to a key IAM doesn't have. Re-run
  `iam:spatie:manifest`, re-register.

## A suspiciously clean diff (possible false-zero)

::: callout danger "Zero mismatches but you expected some?"
If another `Gate::before` short-circuits the gate (e.g. the IAM client already enforcing on a partially
migrated app), the comparison could be measuring IAM against IAM.
:::

- **Confirm the Spatie trait is present.** `spatieAllows()` probes `hasPermissionTo`; if the user model lacks
  Spatie's `HasRoles`/`HasPermissions` traits the probe falls back to the gate result — exactly the
  false-zero risk. Ensure the migrated model uses the Spatie trait.
- **Inject a known divergence** and verify it is logged. If it isn't, the probe is falling back — fix the
  model traits.

## The scanner finds nothing / wrong tables

- **Custom Spatie table names.** `SpatieScanner` reads `permission.table_names`. If your Spatie tables are
  renamed, confirm that config is correct — the scanner honors it via the service provider's `tableNames()`.
- **Wrong database connection.** The scanner uses the default connection
  (`ConnectionResolverInterface::connection()`). If Spatie lives on another connection, point the default
  connection (or the Spatie config) appropriately for the scan.
- **Empty result.** A scan of an empty Spatie install yields empty arrays — `report.md` will show zero roles
  and permissions. That's correct, not a bug.

## Mode change seems ignored

- **Config cache.** After editing `IAM_SPATIE_MODE`, run `php artisan config:clear` (or re-cache). The mode
  is read at boot from cached config if present.
- **Wrong `.env` for the environment.** Per-app deployments each have their own env — make sure you changed
  the one the running app reads.

## Shadow adds latency

- **One `IamClient::can()` per `Gate` check.** Shadow doubles authorization work by design. Keep the IAM
  client's **policy cache** warm (see the
  [client docs](https://doc.laravel-iam-client.padosoft.com)); shadow is a temporary phase, not the steady
  state.
- **Scope the shadow window.** You don't need shadow on forever — run it long enough to cover representative
  traffic, then cut over.

::: callout warning "When in doubt, prove the pipe"
The fastest way to diagnose shadow is a controlled divergence: grant a permission in Spatie that IAM lacks
(or vice versa) and confirm exactly one `iam.shadow.mismatch` with the expected `direction`. If that works,
the machinery is sound and the issue is in your data/mapping.
:::

## Next

- [Configuration](/operations/configuration) — the keys behind these symptoms.
- [Decision diffing](/concepts/decision-diffing) — why the false-zero happens and how the probe avoids it.
