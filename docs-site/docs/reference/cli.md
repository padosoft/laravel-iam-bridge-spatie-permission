---
title: CLI reference
description: The artisan commands shipped by the bridge — iam:spatie:scan and iam:spatie:manifest — with their signatures, options, outputs, and the server-side commands they hand off to.
---

# CLI reference

The bridge ships **two** artisan commands, both under `Padosoft\Iam\Bridge\Spatie\Console\`. The
validate/register commands referenced below are provided by
[`laravel-iam-server`](https://doc.laravel-iam-server.padosoft.com), not this package.

## `iam:spatie:scan`

Read-only inventory of `spatie/laravel-permission`. Writes `inventory.json` + `report.md`.

```bash
php artisan iam:spatie:scan --output=storage/app/iam/spatie-inventory
```

**Signature**

```text
iam:spatie:scan {--output=storage/app/iam/spatie-inventory : Cartella di output}
```

| Option | Default | Purpose |
|---|---|---|
| `--output` | `storage/app/iam/spatie-inventory` | Output directory (created if missing) |

**Outputs**

| File | Contents |
|---|---|
| `inventory.json` | Full structured scan (`permissions`, `roles`, `direct_user_permissions`, `model_has_roles_count`, `guards`) |
| `report.md` | Counts + smells: roles, permissions, empty roles, direct grants, distinct guards |

**Exit / console**

```text
Inventory Spatie: 12 ruoli, 87 permessi → storage/app/iam/spatie-inventory
```

Returns `SUCCESS` (`0`). The scan is **read-only** — it never mutates a Spatie table. Details:
[Inventory & scan](/guides/inventory-and-scan).

## `iam:spatie:manifest`

Generates a `laravel-iam.manifest.v2` from a fresh scan.

```bash
php artisan iam:spatie:manifest --app=billing --name="Billing" \
  --output=storage/app/iam/iam.manifest.json
```

**Signature**

```text
iam:spatie:manifest
    {--app=legacy : app.key del manifest}
    {--name= : app.name (default = app.key)}
    {--output=storage/app/iam/iam.manifest.json : File di output}
```

| Option | Default | Purpose |
|---|---|---|
| `--app` | `legacy` | `app.key` of the manifest |
| `--name` | = `--app` | `app.name` (human label) |
| `--output` | `storage/app/iam/iam.manifest.json` | Output file (parent dir created if missing) |

**Console**

```text
Manifest generato (2 permessi, 2 ruoli) → storage/app/iam/iam.manifest.json
Prossimo: php artisan iam:manifest:validate storage/app/iam/iam.manifest.json
```

Returns `SUCCESS` (`0`). The manifest is a **proposal** — review it before registering. Details:
[Manifest generation](/guides/manifest-generation).

## Hand-off: server commands (not in this package)

After generating the manifest, validate and register it with the server's commands:

```bash
php artisan iam:manifest:validate storage/app/iam/iam.manifest.json
php artisan iam:app:register      storage/app/iam/iam.manifest.json
```

| Command | Package | Purpose |
|---|---|---|
| `iam:manifest:validate` | `laravel-iam-server` | Validate the manifest against the `laravel-iam.manifest.v2` schema |
| `iam:app:register` | `laravel-iam-server` | Register/apply the application on the IAM server |

## Discover them

```bash
php artisan list iam:spatie
```

::: callout info "Cutover is not a command"
There is no `iam:spatie:cutover`. Cutover and rollback are a **config switch** — `IAM_SPATIE_MODE` between
`shadow` and `enforce`. See [cutover & rollback](/guides/cutover-and-rollback).
:::

## Next

- [PHP API](/reference/php-api) — the classes behind the commands.
- [Manifest schema](/reference/manifest-schema) — the document `iam:spatie:manifest` produces.
