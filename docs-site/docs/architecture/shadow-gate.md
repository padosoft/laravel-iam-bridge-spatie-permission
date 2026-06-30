---
title: ShadowGate internals
description: A line-by-line walk through ShadowGate — registration on Gate::after, ability resolution, context building, the IamClient call, the direct Spatie probe, and the recorder hand-off.
---

# ShadowGate internals

`ShadowGate` (in `src/Shadow/ShadowGate.php`) is the runtime that makes shadow mode work. It is a single
`final` class with two public methods — `register()` and `compare()` — plus a private `spatieAllows()` probe.

## Construction

It is built as a singleton by the service provider with four collaborators:

```php
public function __construct(
    private readonly IamClient $client,        // the parallel IAM decision (laravel-iam-client)
    private readonly RecordsMismatch $recorder, // where divergences go
    private readonly PermissionMapper $mapper,  // Spatie name → IAM key
    private readonly string $application,        // IAM_SPATIE_APP prefix
) {}
```

```php
// IamSpatieBridgeServiceProvider::packageRegistered()
$this->app->singleton(ShadowGate::class, fn (Application $app): ShadowGate => new ShadowGate(
    $app->make(IamClient::class),
    $app->make(RecordsMismatch::class),
    $app->make(PermissionMapper::class),
    $this->stringConfig('application') ?? 'app',
));
```

## `register()` — the Gate::after hook

```php
public function register(Gate $gate): void
{
    $gate->after(function (Authenticatable $user, string $ability, ?bool $result, array $arguments = []): ?bool {
        $this->compare($user, $ability, $result, $arguments);

        return null; // shadow: never change the local outcome
    });
}
```

`packageBooted()` calls this **only** when `mode === 'shadow'`. The callback returns `null` so Spatie's
decision stands — observation cannot alter authorization.

## `compare()` — the four steps

```php
public function compare(Authenticatable $user, string $ability, ?bool $localResult, array $arguments = []): void
{
    $iamAbility = str_contains($ability, ':')
        ? $ability
        : $this->application.':'.$this->mapper->toKey($ability);

    $context = ['application' => $this->application];
    $first = $arguments[0] ?? null;
    if (is_string($first) && $first !== '') {
        $context['resource'] = $first;
    }

    $iamAllows = $this->client->can($user, $iamAbility, $context);
    $spatieAllows = $this->spatieAllows($user, $ability, $localResult);

    if ($iamAllows !== $spatieAllows) {
        $this->recorder->record($this->client->resolveSubjectId($user), $ability, $spatieAllows, $iamAllows);
    }
}
```

```mermaid
flowchart TB
    A["ability"] --> B{contains ':'?}
    B -->|yes| C["use as-is (already full_key)"]
    B -->|no| D["application + ':' + mapper.toKey(ability)"]
    C --> E["context = { application }"]
    D --> E
    E --> F{arguments[0] is non-empty string?}
    F -->|yes| G["context.resource = arguments[0]"]
    F -->|no| H["no resource"]
    G --> I["iamAllows = client.can(user, iamAbility, context)"]
    H --> I
    I --> J["spatieAllows = spatieAllows(user, ability, localResult)"]
    J --> K{iamAllows != spatieAllows?}
    K -->|yes| L["recorder.record(subjectId, ability, spatie, iam)"]
    K -->|no| M["nothing"]
```

1. **Ability resolution.** An ability already containing `:` is treated as a fully-qualified `full_key`;
   otherwise it is namespaced as `application:toKey(ability)`. Same rule as
   [`PermissionMapper::toFullKey`](/concepts/permission-slugging#full_key-resolution).
2. **Context.** Always `{ application }`. If the first `Gate` argument is a non-empty string it is attached as
   `resource` — a convention for resource-scoped checks (`can('view', $documentId)`).
3. **IAM decision.** `IamClient::can($user, $iamAbility, $context)` returns IAM's parallel verdict.
4. **Compare & record.** A mismatch is recorded only on disagreement, keyed by the IAM subject id.

## `spatieAllows()` — the direct probe

```php
private function spatieAllows(Authenticatable $user, string $ability, ?bool $gateResult): bool
{
    $probe = [$user, 'hasPermissionTo'];
    if (is_callable($probe)) {
        try {
            return (bool) $probe($ability);
        } catch (\Throwable) {
            return false; // permission unknown to Spatie → deny
        }
    }

    return $gateResult === true; // no Spatie trait → fall back to the Gate result
}
```

This is the correctness core: Spatie is asked **directly** via `hasPermissionTo`, not via the `$gateResult`
which may have been short-circuited upstream. Unknown permission → `false` (deny-overrides). Only when the
model lacks the Spatie trait does it fall back to the gate result. Full rationale in
[decision diffing](/concepts/decision-diffing).

## Why these choices

::: collapsible "ADR — a stateless, return-null observer"
**Problem.** An observer that holds state or can influence the gate result risks both memory/race issues and
accidental enforcement.

**Decision.** Make `ShadowGate` stateless: each `compare()` is self-contained, depends only on its
injected collaborators, and the `Gate::after` callback always returns `null`. Recording is delegated to the
`RecordsMismatch` abstraction so the sink is swappable without touching the gate.

**Consequences.** The class is trivial to test (`compare()` is public and pure-ish), safe under concurrency,
and structurally incapable of changing an outcome. The trade-off is one `IamClient::can()` call per `Gate`
check in shadow — acceptable for a temporary migration phase, and mitigated by the client's policy cache.
:::

::: callout warning "Gotchas"
- `compare()` is intentionally **public** so it can be unit-tested directly without a real `Gate`.
- Only `arguments[0]` becomes `resource`, and only if it is a **non-empty string**. Object/array arguments
  are ignored for context.
- The recorder is called with the **original** ability string, not the resolved `full_key` — keep that in
  mind when correlating logs with the manifest.
:::

## Next

- [Decision diffing & deny-overrides](/concepts/decision-diffing) — the theory behind `spatieAllows()`.
- [Observability & mismatch logs](/operations/observability) — swapping the recorder.
- [PHP API](/reference/php-api) — the full method signatures.
