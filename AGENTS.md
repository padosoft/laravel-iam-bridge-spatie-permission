# AGENTS.md — laravel-iam-bridge-spatie-permission

Guida rapida per agenti. Questo è un package dell'ecosistema **Laravel IAM** (migrazione da
`spatie/laravel-permission`). Per i dettagli, in ordine:

1. **`LESSON.md`** — trappole già risolte (toolchain, PHPStan max, sicurezza, e specifiche del bridge).
2. **`RULES.md`** — ambiente, processo single-repo, commit/PR, invarianti di prodotto.
3. **`CLAUDE.md`** — invarianti + architettura reale di questo package.
4. **Skill `laravel-iam-package-workflow`** (`.claude/skills/...`) — il workflow operativo completo.

## Loop di lavoro (sintesi)
- Branch per task (`task/<nome>`); PR verso `main`; mai commit diretti su `main`.
- Gate locale: Pest + PHPStan max + Pint verdi, poi **advisory** con
  `copilot -p "/review <diff vs origin/main> — focus: sicurezza, fail-closed, invarianti IAM"`.
- ⚠️ **MAI `copilot --autopilot --yolo`**: edita/commita/pusha in autonomia e in passato ha pushato codice
  regredito. L'advisory è solo consultivo — i fix li applichi tu, mantenendo il controllo.
- Test in locale con **PHP 8.5 (Herd)**, non XAMPP.

## Specifico del bridge
- Shadow mode non altera MAI l'esito locale; il diffing è in deny-overrides; il manifest generato è una
  proposta da validare. Vedi `CLAUDE.md` → "Invarianti specifiche del bridge".

## Commit & PR
- Commit terminano con: `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`
- Corpo PR termina con: `🤖 Generated with [Claude Code](https://claude.com/claude-code)`
