---
title: Reference
description: Artisan commands, classes, and configuration keys shipped by laravel-iam-bridge-spatie-permission.
---

# Reference

All classes live under `Padosoft\Iam\Bridge\Spatie\`.

## Artisan commands

### `iam:spatie:scan`
Read-only inventory of `spatie/laravel-permission`. Writes `inventory.json` + `report.md`.

| Option | Default | Purpose |
| --- | --- | --- |
| `--output` | `storage/app/iam/spatie-inventory` | Output directory |

### `iam:spatie:manifest`
Generates a `laravel-iam.manifest.v2` from the inventory.

| Option | Default | Purpose |
| --- | --- | --- |
| `--app` | `legacy` | `app.key` of the manifest |
| `--name` | = `--app` | `app.name` |
| `--output` | `storage/app/iam/iam.manifest.json` | Output file |

## Classes

### `Migration\SpatieScanner`
Reads the Spatie tables (names from `permission.table_names`) and returns a structured map: roles + their
permissions, permissions, direct user grants, guards. **Read-only.**

### `Migration\PermissionMapper`
- `toKey(string $name): string` — deterministic slug to `^[a-z][a-z0-9_.-]*$` (idempotent; prefixes `p_`
  if the name starts with a non-letter).
- `toFullKey(string $application, string $name): string` — `"<app>:<key>"` (passes through names that
  already contain `:`).
- `inferRisk(string $key): string` — `high` for high-impact actions (refund, delete, grant, impersonate,
  export, approve…), otherwise `low`.

### `Migration\ManifestGenerator`
- `generate(array $scan, array $app): array` — maps the inventory to `laravel-iam.manifest.v2`. Dedups
  colliding keys (keeps the first; a collision is a semantic duplicate to review). `risk` is a starting
  heuristic — validate and approve before sync.

### `Shadow\ShadowGate`
- `register(Gate $gate): void` — hooks `Gate::after`; always returns `null` (never alters the live result).
- `compare(Authenticatable $user, string $ability, ?bool $localResult, array $arguments = []): void` —
  evaluates IAM, probes Spatie directly (`hasPermissionTo`), records a mismatch on divergence.

### `Shadow\RecordsMismatch` (interface) / `Shadow\MismatchRecorder`
- `record(string $subjectId, string $ability, bool $spatieAllows, bool $iamAllows): void` — default sink
  logs `iam.shadow.mismatch` (structured). Swap the binding to push to a dashboard or review queue.

## Configuration (`config/iam-spatie.php`)

| Key | Env | Default | Purpose |
| --- | --- | --- | --- |
| `mode` | `IAM_SPATIE_MODE` | `shadow` | `shadow` (observe) or `enforce` (IAM is authority) |
| `application` | `IAM_SPATIE_APP` | `app` | Prefix applied to namespace-less permissions |
| `cache.write_protection` | — | `true` | Spatie becomes read-only cache after cutover |
| `cache.sync_on_webhook` | — | `true` | Re-sync the local cache on IAM webhooks |
| `cache.sync_on_login` | — | `true` | Re-sync the local cache on login |
| `mismatch_log_channel` | `IAM_SPATIE_MISMATCH_CHANNEL` | — | Log channel for shadow mismatches |
