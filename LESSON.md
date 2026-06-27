# LESSON.md — lezioni dell'ecosistema Laravel IAM

> Lezioni **generali** valide per ogni package, accumulate costruendo Laravel IAM v1.0 (16 milestone,
> TDD + loop advisory). Sotto, la sezione **specifica di questo package**. Aggiorna ad ogni scoperta.

## Generali — toolchain & PHPStan max

- **Test con PHP 8.5 (Herd)**: `~/.config/herd/bin/php85/php.exe`. Su Windows, PHPStan vuole
  `--memory-limit=1G` e, prima di Pest/testbench, `attrib -R` sulla dir
  `vendor/orchestra/testbench-core/laravel/bootstrap/cache` (bug `is_writable()`). `.gitattributes eol=lf`.
- **PHPStan crash transitorio** ("Result is incomplete because of severe errors"): ri-eseguire risolve.
- **Mai cast su `mixed`**: usare guardie `is_int`/`is_string`/`is_numeric`, non `(string)`/`(int)`.
- **`@property` sui Model invece di castare nel chiamante**: una colonna castata letta da un servizio
  esterno al model fa fallire PHPStan (`property.notFound` → `Cannot cast mixed`). Dichiarare
  `@property Carbon|null` sul model; poi un `?->` su valore ora non-null diventa `nullsafe.neverNull` → `->`.
- **Mai `*/` dentro un docblock**: `decided_*/granted_id` in `/** */` CHIUDE il commento → ParseError.
- **`@phpstan-impure`** per i metodi con side-effect osservabili (mutano una proprietà pubblica e vengono
  chiamati due volte): senza, PHPStan crede il secondo valore immutato (`booleanOr.leftAlwaysFalse`).
- **Config da `mixed` → `array<string,mixed>` provabile**: `is_array($x) ? $x : []` resta `array<mixed>`;
  ricostruire con un `foreach` che casta le chiavi a stringa per soddisfare la firma.
- **larastan + generics Eloquent + closure**: `Builder<User>` non è assegnabile a `Builder<Model>`
  (invariante) e `get()` perde `TModel`. Per un paginator generico: `@param Builder<covariant Model>` +
  `callable(Model): array` con narrowing `instanceof` al call-site.

## Generali — sicurezza & processo

- **Fail-closed sempre**: default-deny, deny-overrides; un errore (transport, PDP, parsing) → deny, mai un
  allow né un 500 opaco. Vale per PDP, client, directory, AI.
- **Il loop advisory trova bug reali ad ogni slice**: TOCTOU, fail-open, takeover, info-disclosure,
  escalation. `copilot -p` (advisory), **mai** `--autopilot --yolo`. Ogni fix → qui.
- **TOCTOU sulle transizioni di stato**: leggere-poi-scrivere uno stato senza `DB::transaction` +
  `lockForUpdate` + re-check sotto lock = last-write-wins (grant orfano, doppia approvazione).
- **Snapshot vs dato vivo**: la governance congela i segnali/policy al momento giusto; l'esito non deve
  dipendere da una modifica successiva (un ruolo tolto dal catalogo non deve creare grant permanenti).
- **Tenant isolation = 404, non 403**: il cross-tenant deve essere indistinguibile da "non esiste",
  altrimenti il 403 conferma l'esistenza dell'UUID (enumerazione).
- **Deps pesanti in `suggest`, non `require`**: `aws-sdk-php`, `ldaprecord` (ext-ldap), `laravel/ai`
  rallentano/ rompono install e CI. Il core resta usabile senza; l'adapter reale è opzionale e, se non
  installabile in dev, va isolato (sottospazio + `excludePaths` PHPStan).
- **Commit message via file** se l'here-string fallisce su Windows: scrivere su file e `git commit -F`.

## Specifiche di questo package

Lezioni dalla migrazione `spatie/laravel-permission` → IAM (doc 07):

- **Shadow = osservazione, non enforcement.** `ShadowGate` aggancia `Gate::after` e ritorna SEMPRE `null`:
  vede l'esito reale della valutazione locale ma non lo cambia. È il passo che precede il cutover; nessun
  utente viene mai bloccato o sbloccato da IAM in shadow.
- **Confronta con Spatie DIRETTO, non col `$result` di `Gate::after`.** Quel risultato può essere stato
  cortocircuitato da un altro `Gate::before` (es. l'enforce del client IAM): si finirebbe a confrontare IAM
  con IAM → zero mismatch falsi → cutover su dati invalidi. Si interroga `hasPermissionTo` quando
  disponibile; in assenza del trait si ricade sul risultato del Gate.
- **Diffing in deny-overrides.** Un'azione ignota a Spatie → `deny`; nel dubbio si registra il mismatch
  lato negazione. Il cutover si fa solo con mismatch log pulito (o divergenze attese e spiegate).
- **Lo scanner è SOLO lettura.** `SpatieScanner` legge le tabelle Spatie (nomi da `permission.table_names`)
  e non muta nulla: l'inventory è sicuro da rieseguire in produzione.
- **Slug deterministico e idempotente.** `PermissionMapper` normalizza i nomi Spatie sullo slug IAM
  `^[a-z][a-z0-9_.-]*$`; due nomi che collidono sulla stessa chiave sono un **duplicato semantico** da
  rivedere a mano (il `ManifestGenerator` tiene il primo). Un nome che inizia per cifra/simbolo va
  prefissato (`p_`).
- **Il manifest è una proposta, non una verità.** `risk` è un'euristica (azioni high-risk: refund, delete,
  grant, impersonate…); va sempre validato (`iam:manifest:validate`) e approvato prima del sync.
- **Cutover reversibile via env.** `IAM_SPATIE_MODE` (`shadow` ⇄ `enforce`) è l'unico interruttore: dopo il
  cutover Spatie resta cache read-only (write_protection), e il rollback è immediato.
