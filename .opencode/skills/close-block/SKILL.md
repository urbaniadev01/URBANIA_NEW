---
name: close-block
description: Actualizar _state/BOARD.md (y CHANGELOG/CONTRACT_LOCKS si aplica) cuando un bloque cambia de estado. Uso exclusivo de @verifier y @cross-project — nunca de un build agent.
---

## Cuándo se usa

Después de que `@verifier` confirma un bloque como `done`, o después de que `@cross-project`
registra un lock o una entrada de cierre.

## Pasos

1. Confirmar que el frontmatter de la tarjeta ya refleja el estado real (la tarjeta es la fuente —
   nunca se edita el `BOARD` primero).
2. Actualizar la fila correspondiente en `_state/BOARD.md` para que coincida exactamente.
3. Si el bloque produce contrato y acaba de llegar a `done`: crear/actualizar la entrada en
   `_state/contracts/CONTRACT_LOCKS.md` (formato en `_system/04_CROSS_PROJECT.md` §4).
4. Si el bloque cierra un cross-project completo (ambos lados `done`): agregar la entrada de cierre
   en `_state/CHANGELOG.md` (formato en ese archivo) — **nunca editar una entrada previa**, solo
   agregar.
5. Si el bloque desbloquea otro (su dependiente estaba en `backlog` esperando esto): mover el
   dependiente a `ready` en su propia tarjeta y reflejarlo en `BOARD.md`.

## Regla dura

Este skill nunca mueve una tarjeta a `estado: done` por sí mismo — esa decisión ya la tomó
`@verifier` antes de invocar este skill. El skill solo propaga esa decisión al resto del vault.
