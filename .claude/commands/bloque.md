---
description: Paso 3 de _system/00_START_HERE.md — ejecutar un bloque específico por su ID
argument-hint: <ID-del-bloque, ej. AUTH-B03>
---

Ejecuta el bloque `$ARGUMENTS` siguiendo `_system/00_START_HERE.md` Paso 3:

1. Abre `features/<FEATURE>/blocks/<FEATURE>-B<NN>-*.md` correspondiente a `$ARGUMENTS`.
2. Confirma `estado: ready` y que `depende_de` está satisfecho. Si no, detente y reporta.
3. Si el bloque es cross-project, confirma el gate de `_system/04_CROSS_PROJECT.md` §3 antes de
   tocar código.
4. Lee solo los documentos que la tarjeta enlaza — ese es el read-set completo
   (`_system/06_AGENT_ROLES.md`); no leas el panorama completo del feature salvo que la tarjeta lo
   pida.
5. Implementa exactamente el alcance de la tarjeta — ni más ni menos (ver `_system/03_LIFECYCLE.md`
   §2 si resulta ser más grande de lo esperado).
6. Cumple el DoD de la tarjeta (`_system/05_DEFINITION_OF_DONE.md`), pega evidencia real (output de
   comandos, requests/responses reales), y pasa el `estado` a `verifying`.

No muevas la tarjeta a `done` — esa transición la hace el usuario tras revisar la evidencia.
