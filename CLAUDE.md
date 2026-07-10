# CLAUDE.md

This file guides Claude Code when working in this repository. / Este archivo guía a Claude Code al
trabajar en este repositorio.

## What this repository is / Qué es este repositorio

**EN:** This is the Urbania documentation vault (redesigned), an Obsidian vault of markdown
documentation describing the methodology (`_system/`), live state (`_state/`), and feature design
(`features/`). Source code lives alongside it under `code/` (see below). Development can be driven by
either **Claude Code** (this session, following this file) or **OpenCode** (via the agent pipeline in
`.opencode/agents/` and `opencode.json`) — both follow the same methodology defined in `_system/`,
they are interchangeable drivers, not competing processes. `.opencode/` is left untouched and fully
usable; switching to Claude Code as the driver does not retire it.

**ES:** Este es el vault de documentación de Urbania (rediseñado), un vault de Obsidian de markdown
que describe la metodología (`_system/`), el estado vivo (`_state/`) y el diseño de features
(`features/`). El código fuente vive junto a él bajo `code/` (ver abajo). El desarrollo puede ser
conducido tanto por **Claude Code** (esta sesión, siguiendo este archivo) como por **OpenCode** (vía
el pipeline de agentes en `.opencode/agents/` y `opencode.json`) — ambos siguen la misma metodología
definida en `_system/`, son drivers intercambiables, no procesos que compitan. `.opencode/` queda
intacto y totalmente usable; usar Claude Code como driver no lo retira.

## Claude Code's role here / El rol de Claude Code aquí

**EN:** Claude Code drives the full pipeline end to end — design **and** execution — following
`_system/00_START_HERE.md` literally as the decision tree (not the `.opencode/agents/` instructions,
which are OpenCode-specific and not read by Claude Code):

- No explicit block requested → Step 2: read `_state/BOARD.md`, take the first `ready` block.
- A specific block ID → Step 3: open its card, confirm `ready` + `depende_de` satisfied, read only
  the card's declared read-set, implement exactly its scope, meet the DoD
  (`_system/05_DEFINITION_OF_DONE.md`), paste real evidence, move the card to `estado: verifying`.
  **Claude never moves a card to `done` itself** — that transition is the user's call, mirroring how
  `PANORAMA.md` approval already works (Claude proposes, the human approves). This preserves the
  vault's rule that no agent marks its own work done.
  This applies to real source code under `code/api/` and `code/web/`, not just documentation.
- A brand-new feature → Step 4: draft `features/<name>/PANORAMA.md` interactively with the user,
  leave it in `estado_diseño: draft`, stop for review. Blocks are only created once the user sets it
  to `approved`.
- If anything is unclear or a document the decision tree references is missing: stop and report —
  never assume (same rule `00_START_HERE.md` states for every agent).

**ES:** Claude Code conduce el pipeline completo de punta a punta — diseño **y** ejecución —
siguiendo `_system/00_START_HERE.md` literalmente como árbol de decisión (no las instrucciones de
`.opencode/agents/`, que son específicas de OpenCode y Claude Code no las lee):

- Sin bloque específico → Paso 2: leer `_state/BOARD.md`, tomar el primer bloque `ready`.
- Un ID de bloque específico → Paso 3: abrir su tarjeta, confirmar `ready` + `depende_de` satisfecho,
  leer solo el read-set declarado por la tarjeta, implementar exactamente su alcance, cumplir el DoD
  (`_system/05_DEFINITION_OF_DONE.md`), pegar evidencia real, pasar la tarjeta a `estado: verifying`.
  **Claude nunca mueve una tarjeta a `done` por su cuenta** — esa transición es decisión del usuario,
  igual que ya funciona la aprobación de `PANORAMA.md` (Claude propone, el humano aprueba). Esto
  preserva la regla del vault de que ningún agente marca su propio trabajo como terminado.
  Esto aplica a código fuente real bajo `code/api/` y `code/web/`, no solo a documentación.
- Una feature nueva → Paso 4: completar `features/<nombre>/PANORAMA.md` interactivamente con el
  usuario, dejarlo en `estado_diseño: draft`, detenerse para revisión. Los bloques solo se crean una
  vez que el usuario lo pase a `approved`.
- Si algo no está claro o falta un documento que el árbol de decisión referencia: detenerse y
  reportar, nunca asumir (misma regla que `00_START_HERE.md` exige a cualquier agente).

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

**EN:** Source code lives under `code/` (`code/api/`, `code/web/`) as part of this same repository —
a monorepo, not separate git repos. The `code/` prefix avoids collision with the `api/`/`web/`
documentation folders on case-insensitive filesystems (Windows).

**ES:** El código fuente vive bajo `code/` (`code/api/`, `code/web/`) como parte de este mismo
repositorio — un monorepo, no repos git separados. El prefijo `code/` evita la colisión con las
carpetas de documentación `api/`/`web/` en sistemas de archivos case-insensitive (Windows).
