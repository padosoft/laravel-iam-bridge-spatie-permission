# CLAUDE.md — laravel-iam-bridge-spatie-permission

Guida per agenti AI che lavorano in questo repo (package dell'ecosistema **Laravel IAM**). Prima di
qualsiasi lavoro leggi `LESSON.md`, `RULES.md` e questa pagina. Skill: `laravel-iam-package-workflow`.

## Cos'è questo package

Bridge di migrazione da `spatie/laravel-permission` a Laravel IAM — scan, manifest generation, shadow
mode, decision diffing, cutover, rollback. Permette un passaggio graduale e reversibile: prima si OSSERVA
(shadow), poi si ENFORCE.

- **Composer:** `padosoft/laravel-iam-bridge-spatie-permission`
- **Namespace:** `Padosoft\Iam\Bridge\Spatie\`
- **Ruolo nell'ecosistema:** il percorso di migrazione zero-downtime DA `spatie/laravel-permission` A
  Laravel IAM. In shadow Spatie decide e IAM valuta in parallelo (diffing dei mismatch); al cutover IAM
  diventa l'autorità e Spatie resta cache read-only. Reversibile via env (`IAM_SPATIE_MODE`).
- **Dipende da:** `padosoft/laravel-iam-contracts`, `padosoft/laravel-iam-server`,
  `padosoft/laravel-iam-client`, `spatie/laravel-permission`.

## Architettura del package

```
src/
  Console/
    SpatieScanCommand.php       # `iam:spatie:scan` — inventory read-only → inventory.json + report.md
    SpatieManifestCommand.php   # `iam:spatie:manifest` — genera laravel-iam.manifest.v2 dall'inventory
  Migration/
    SpatieScanner.php           # legge le tabelle Spatie (DB, solo lettura): ruoli/permessi/diretti/guard
    PermissionMapper.php        # slug deterministico Spatie → key IAM (^[a-z][a-z0-9_.-]*$) + risk heuristic
    ManifestGenerator.php       # inventory → manifest v2 (dedup chiavi, risk di partenza da rivedere)
  Shadow/
    ShadowGate.php              # Gate::after: confronta IAM vs Spatie, ritorna null (non altera l'esito)
    RecordsMismatch.php         # interfaccia sink dei mismatch (default log; sostituibile)
    MismatchRecorder.php        # sink di default → log strutturato `iam.shadow.mismatch`
  IamSpatieBridgeServiceProvider.php  # registra comandi, bindings, e in mode=shadow aggancia ShadowGate
config/iam-spatie.php           # mode (shadow|enforce), application prefix, cache, mismatch_log_channel
```

Config chiave: `IAM_SPATIE_MODE` (shadow|enforce), `IAM_SPATIE_APP` (prefisso full_key), `cache`
(write_protection / sync_on_webhook / sync_on_login), `IAM_SPATIE_MISMATCH_CHANNEL`.

## Invarianti (NON violare)
1. **Mai bypassare il PDP.** L'AI propone draft/spiegazioni; il PDP deterministico decide allow/deny.
2. **Fail-closed** sull'autorizzazione; mai fail-open su operazioni critiche.
3. **Niente segreti/OTP/PII nei log.** Segreti cifrati via envelope encryption.
4. **Audit per ogni mutazione** (hash-chain).
5. **Slug permessi/ruoli immutabili** (`app_key:permission`).
6. **Scope/condition dichiarati dalle app** nel manifest, mai hardcoded nel core.
7. **Nessuna UI legge il DB**: solo Admin API.
8. **OIDC layer**: base MIT (steverhoades). **Vietato** codice AGPL (limosa-io). OAuth = league/oauth2-server.

### Invarianti specifiche del bridge
- **Shadow non altera MAI l'esito locale.** `ShadowGate` usa `Gate::after` e ritorna `null`: osserva e
  registra, non blocca né concede. Nessun utente è impattato finché non si passa a `enforce`.
- **Diffing in deny-overrides.** Nel dubbio si registra il mismatch lato deny; un permesso ignoto a Spatie
  → deny.
- **Probe diretta di Spatie** (`hasPermissionTo`), non il `$result` di `Gate::after`: quel risultato può
  essere stato cortocircuitato da un `Gate::before` (es. enforce del client), facendo confrontare IAM con
  IAM → zero mismatch falsi → cutover su dati invalidi.
- **Scanner read-only.** Non muta mai le tabelle Spatie.
- **Il manifest è una PROPOSTA.** `risk` e mapping sono euristiche: vanno validati e approvati
  (`iam:manifest:validate`) prima del sync.
- **Cutover reversibile.** `shadow ⇄ enforce` è una singola env var; rollback immediato.

## Convenzioni codice
- `declare(strict_types=1)`, classi `final` di default.
- Namespace radice **`Padosoft\Iam\`** (PSR-4) — qui `Padosoft\Iam\Bridge\Spatie\`.
- **PHPStan max**, **Pest**, **Pint**. Test negativi obbligatori (mismatch deny, shadow non-bloccante).

## Gate (in locale, con PHP 8.5 Herd)
```bash
php vendor/bin/pint
php vendor/bin/phpstan analyse --memory-limit=1G
php vendor/bin/pest
```
> Nota: i test e il tooling QA sono stati sviluppati nel monorepo originale; vedi `LESSON.md` per il
> setup standalone. La suite di test completa di questo package è in fase di migrazione per-repo.

## Loop di lavoro
Branch per task → gate locale (test + advisory `copilot -p`, **mai `--yolo`**) → PR → CI + Copilot review
→ merge → tag. Aggiorna `LESSON.md` ad ogni fix. Dettaglio: la skill `laravel-iam-package-workflow`.
