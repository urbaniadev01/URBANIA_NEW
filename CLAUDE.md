# CLAUDE.md

This file guides Claude Code when working in this repository. / Este archivo guía a Claude Code al
trabajar en este repositorio.

## What this repository is / Qué es este repositorio

**EN:** This is the Urbania documentation vault (redesigned), an Obsidian vault of markdown
documentation only — not a code repository. Actual development happens via **OpenCode**, driven by
the agent pipeline configured in `.opencode/agents/` and `opencode.json` at the vault root.

**ES:** Este es el vault de documentación de Urbania (rediseñado), un vault de Obsidian de solo
markdown — no un repositorio de código. El desarrollo real ocurre vía **OpenCode**, guiado por el
pipeline de agentes configurado en `.opencode/agents/` y `opencode.json` en la raíz del vault.

## Claude Code's role here / El rol de Claude Code aquí

**EN:** Claude Code operates in **audit/advisory mode** on this vault — reading, reviewing
coherence, and (when explicitly asked) helping design or refine the documentation system itself. It
does not drive day-to-day feature execution; that is OpenCode's job, following
`_system/00_START_HERE.md`. If asked to "implement a block," treat it as a documentation/design task
unless the user is explicit that they want actual source code written outside this vault.

**ES:** Claude Code opera en **modo auditoría/asesoría** sobre este vault — lee, revisa coherencia y
(cuando se le pide explícitamente) ayuda a diseñar o refinar el sistema de documentación en sí. No
conduce la ejecución diaria de features — eso es trabajo de OpenCode, siguiendo
`_system/00_START_HERE.md`. Si se pide "implementar un bloque", tratarlo como tarea de
documentación/diseño salvo que el usuario pida explícitamente escribir código fuente real fuera de
este vault.

## The core design (read `_system/` for the full version)

- **One data point, one owner** — state lives in exactly one file (a block's own frontmatter); every
  other document links to it, never copies it. `_state/BOARD.md` is a rollup, never a source.
- **Design → Blocks → Execution** — a feature is designed once (`PANORAMA.md`), split into small
  blocks (`blocks/*.md`), and an agent executes **one block per session** — never a whole feature at
  once.
- **Contract-first with gates** — a cross-project block (API + Web) freezes its API contract in
  `_state/contracts/CONTRACT_LOCKS.md` before the Web block can even start; this is mechanical, not
  a convention.
- **"Done" = proven** — no block reaches `done` without pasted evidence (real command output, real
  request/response) and an independent verification pass, not the implementing agent's own
  self-report.

Full detail: `_system/01_PRINCIPLES.md` through `_system/06_AGENT_ROLES.md`.

## Structure

```
_system/    ← methodology — how we work (stable)
_state/     ← live state — the single board, changelog, frozen contracts
shared/     ← cross-project truth — glossary, ADRs, the API↔Web contract
features/   ← one folder per feature: design (PANORAMA.md) + execution units (blocks/)
api/        ← API technical reference (Laravel + DDD)
web/        ← Web technical reference (Vite + React)
app/        ← deferred — single marker file until this track starts
```

## Source code / Código fuente

**EN:** When the source folders exist, they live under `code/` (`code/api/`, `code/web/`) — not as
sibling folders named `API/`/`WEB/` at the vault root. On a case-insensitive filesystem (Windows),
`API/` and `api/` (this vault's docs) are the same path — they cannot coexist. Source folders are
independent git repos (gitignored, see `.gitignore`) — never commit source code changes from the
vault root.

**ES:** Cuando las carpetas de código existan, viven bajo `code/` (`code/api/`, `code/web/`) — no
como carpetas hermanas `API/`/`WEB/` en la raíz del vault. En un sistema de archivos
case-insensitive (Windows), `API/` y `api/` (la documentación de este vault) son la misma ruta — no
pueden coexistir. Las carpetas de código son repos git independientes (ignoradas por `.gitignore`)
— nunca se commitea código fuente desde la raíz del vault.
