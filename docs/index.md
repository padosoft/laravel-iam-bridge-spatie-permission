---
title: Home
description: Zero-downtime migration from spatie/laravel-permission to Laravel IAM — scan, shadow, diff, cutover, rollback.
---

# Laravel IAM — Spatie Permission Bridge

`padosoft/laravel-iam-bridge-spatie-permission` is the **migration path** off
[`spatie/laravel-permission`](https://spatie.be/docs/laravel-permission) onto
[Laravel IAM](https://github.com/padosoft/laravel-iam-server) — an Identity & Authorization Control Plane.

::: callout tip "Migrate on evidence, not hope"
You never flip authorization blindly. The bridge runs **both** systems in parallel (shadow mode), records
only the decisions where they disagree, and lets you cut over only once that diff is clean — with a rollback
that is a single env var away.
:::

## The three phases

1. **Inventory** — `iam:spatie:scan` reads your Spatie roles/permissions (read-only) and
   `iam:spatie:manifest` turns them into a `laravel-iam.manifest.v2`.
2. **Shadow** — Spatie keeps deciding for real; `ShadowGate` evaluates IAM in parallel on every `Gate`
   check and logs the mismatches. Nobody is blocked or let in by IAM yet.
3. **Enforce** — flip `IAM_SPATIE_MODE=enforce`. IAM becomes the authority; Spatie stays a read-only
   cache. Rollback = flip it back.

## Install

```bash
composer require padosoft/laravel-iam-bridge-spatie-permission
php artisan vendor:publish --tag=iam-spatie-config
```

## Next

- [Getting started](getting-started.md) — install, configure, and run your first scan.
- [Concepts](concepts.md) — the mental model: inventory, shadow, diffing, cutover.
- [Migration runbook](migration-runbook.md) — the full step-by-step flow with rollback.
- [Reference](reference.md) — commands, classes, and config keys.
