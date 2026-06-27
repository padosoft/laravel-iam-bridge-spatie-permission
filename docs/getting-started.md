---
title: Getting started
description: Install the bridge, publish config, and run your first read-only inventory of spatie/laravel-permission.
---

# Getting started

## Requirements

- PHP **8.3+**, Laravel **13+**
- An existing `spatie/laravel-permission` install (v6)
- A reachable Laravel IAM server, wired via [`padosoft/laravel-iam-client`](https://github.com/padosoft/laravel-iam-client)

## Install

```bash
composer require padosoft/laravel-iam-bridge-spatie-permission
php artisan vendor:publish --tag=iam-spatie-config
```

This publishes `config/iam-spatie.php`. The default mode is **shadow** — installing the bridge changes no
authorization behavior.

::: callout info "Shadow by default"
With `IAM_SPATIE_MODE=shadow` (the default), the `ShadowGate` only observes. Spatie remains the sole
authority until you explicitly switch to `enforce`.
:::

## Configure

```dotenv
# .env
IAM_SPATIE_MODE=shadow                 # shadow | enforce
IAM_SPATIE_APP=billing                 # prefix applied to namespace-less permissions
IAM_SPATIE_MISMATCH_CHANNEL=iam-shadow # log channel for shadow mismatches
```

## First run: inventory (read-only)

```bash
php artisan iam:spatie:scan --output=storage/app/iam/spatie-inventory
```

This writes `inventory.json` and a human-readable `report.md` summarizing roles, permissions, empty roles,
direct user grants, and distinct guards. It **touches nothing** in your database.

## Next

Continue with the [Migration runbook](migration-runbook.md) to generate the manifest, observe in shadow,
and cut over safely.
