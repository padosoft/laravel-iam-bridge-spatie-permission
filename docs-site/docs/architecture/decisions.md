---
title: Architecture decisions (ADR)
description: The key decisions behind the bridge as records — shadow before cutover, Gate::after + null, the direct Spatie probe, deny-overrides, deterministic slugging, the manifest as a proposal, and the env-var cutover.
---

# Architecture decisions

The reasoning behind the bridge, captured as Architecture Decision Records: each is *Context → Decision →
Consequences*. The headline decision is the first one; the rest support it.

## ADR-001 — Shadow before cutover

::: collapsible open "ADR-001 — Observe-then-enforce is the package's strategy"
**Context.** Authorization cannot be migrated by a blind switch: a single wrong mapping locks out legitimate
users or admits illegitimate ones, and over a realistic estate the probability that *every* mapping is right
at once is near zero. Teams therefore either avoid migrating or get burned by big-bang cutovers.

**Decision.** Encode "shadow before cutover" as the core strategy. Run both authorities in parallel
(**shadow**), measure the decision diff on real traffic, drive it to clean, and only then flip enforcement —
with rollback one env var away. Make `shadow` the **default** so nothing enforces by accident, and make
enforcement the client's job so "enforce" is a deliberate, separate step.

**Consequences.** Migration becomes measured, evidence-gated, and reversible. The cost is running both
systems in parallel for a representative window and the patience to wait for a clean diff. See
[shadow before cutover](/concepts/shadow-before-cutover).
:::

## ADR-002 — `Gate::after` returning `null`

::: collapsible "ADR-002 — Observe via Gate::after, never alter the outcome"
**Context.** To measure parity the bridge must see Spatie's *real* decision and must not change what users
experience during observation.

**Decision.** Hook `Gate::after` (which fires after the real evaluation) and **always return `null`**, so the
local decision stands. Do not use `Gate::before`, which would run before the policy and could short-circuit
the gate.

**Consequences.** Observation is faithful and structurally non-intrusive — the observer cannot enforce. See
[ShadowGate internals](/architecture/shadow-gate).
:::

## ADR-003 — Probe Spatie directly

::: collapsible "ADR-003 — Compute Spatie's answer from hasPermissionTo, not the gate result"
**Context.** The `?bool $result` handed to `Gate::after` may have been short-circuited by another
`Gate::before` (e.g. the IAM client's own enforcement in a partially-migrated app). Trusting it would compare
**IAM with IAM** and produce a *false-zero* diff — a clean log on invalid data.

**Decision.** Compute Spatie's decision from a **direct** `hasPermissionTo` probe, independent of the gate
result. Fall back to the gate result only when the model lacks the Spatie trait.

**Consequences.** The diff faithfully measures the two systems. The trade-off is a dependency on the Spatie
trait being present on the migrated user model. See [decision diffing](/concepts/decision-diffing).
:::

## ADR-004 — Deny-overrides on uncertainty

::: collapsible "ADR-004 — Fail closed when Spatie cannot affirm a grant"
**Context.** A permission unknown to Spatie has no clean answer. Defaulting it to "allow" would manufacture
spurious agreement and hide real divergences.

**Decision.** When `hasPermissionTo` throws (unknown permission), the probe returns `false` — **deny**.
Uncertainty always resolves toward deny.

**Consequences.** An unknown permission can never read as an accidental allow, so the diff cannot hide an
escalation behind a false "they agree".
:::

## ADR-005 — Deterministic, idempotent, total slugging

::: collapsible "ADR-005 — PermissionMapper is a pure, total function"
**Context.** IAM keys must match `^[a-z][a-z0-9_.-]*$`. If the same Spatie name slugged differently across
runs, manifests would churn and the shadow comparison would target a different key than the one registered.

**Decision.** Make `toKey` deterministic, idempotent (`toKey(toKey(x)) == toKey(x)`), and **total** (every
input yields a valid key: empty → `perm`, non-letter start → `p_…`). Surface collisions (non-injectivity) as
a reviewable semantic-duplicate smell rather than hiding them.

**Consequences.** Keys are stable across runs and re-registrations. The cost is that two different names can
collide; "keep first" + the inventory report make that visible for a human to resolve. See
[permission slugging](/concepts/permission-slugging).
:::

## ADR-006 — The manifest is a proposal

::: collapsible "ADR-006 — Generate a consistent proposal; validate and approve on the server"
**Context.** Risk inference and slugging are heuristics; treating them as authoritative would ship wrong risk
levels and silently merge look-alike permissions.

**Decision.** Emit a manifest that is explicitly a **proposal** — internally consistent (dedup, no dangling
references, valid keys) but not authoritative. Validation (`iam:manifest:validate`) and human approval gate
it before `iam:app:register`. The bridge never registers a manifest itself.

**Consequences.** A strong first draft for free; the review and the schema authority stay on the server, so
the two cannot drift. See [manifest contract](/architecture/manifest-contract).
:::

## ADR-007 — Cutover as a single reversible env var

::: collapsible "ADR-007 — IAM_SPATIE_MODE is the whole switch"
**Context.** A cutover that needs code or schema changes to revert is not safely reversible on a live system.

**Decision.** Model cutover as one flag, `IAM_SPATIE_MODE` (`shadow ⇄ enforce`). Shadow changes no data;
enforce only stops registering the observer and lets the client enforce; Spatie stays a read-only cache
(`write_protection` + `sync_*`). Rollback is the same flag flipped back.

**Consequences.** Going back is as cheap as going forward, which makes the forward move safe to attempt. The
trade-off is keeping both systems installed and the cache consistent while the rollback option is wanted. See
[cutover & rollback](/guides/cutover-and-rollback).
:::

## ADR-008 — Read-only scanner

::: collapsible "ADR-008 — The scanner only reads"
**Context.** A migration tool that writes to the source system can corrupt the data being migrated and makes
the operation irreversible mid-flight.

**Decision.** `SpatieScanner` issues only `SELECT`s and materializes the inventory into files, never back
into the Spatie tables. Any schema cleanup is done by you, deliberately, in Spatie.

**Consequences.** The scan is safe to run on production, repeatedly. The cost is that the bridge will not
"fix" your Spatie schema for you. See [inventory & scan](/guides/inventory-and-scan).
:::

## Next

- [Migration pipeline](/architecture/migration-pipeline) — how these decisions compose into a flow.
- [Best practices → Safe migration](/best-practices/safe-migration) — applying them in practice.
