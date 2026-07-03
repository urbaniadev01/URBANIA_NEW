---
tipo: bloque
proyecto: <api | web | app>
feature: <FEATURE>
id: <FEATURE>-B<NN>
proyectos: [<api> y/o <web> y/o <app>]
estado: backlog
depende_de: []
contrato: null
actualizado: YYYY-MM-DD
---

> Plantilla — copiar a `features/<FEATURE>/blocks/<FEATURE>-B<NN>-<slug>.md` y completar. No editar
> este archivo directamente. Vocabulario de `estado` y reglas de numeración en [[../../_system/02_CONVENTIONS]].

# <FEATURE>-B<NN> — <título corto, verbo + objeto>

## Objetivo

1–3 líneas: qué entrega este bloque concretamente y por qué es el siguiente paso lógico del feature
(referencia a `BLOCKS.md` del feature para el orden completo).

## Alcance

- **Incluye:** ...
- **No incluye (explícitamente fuera de este bloque):** ...

> La lista de "no incluye" existe para que el agente no extienda el bloque a mitad de sesión. Si algo
> que no está en el alcance resulta necesario, se crea un bloque nuevo (ver `03_LIFECYCLE.md` §2).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | <estado inicial / datos> | <qué hace el actor> | <resultado — caso feliz> |
| 2 | <estado inicial / datos> | <qué hace el actor> | <resultado — caso negativo o de seguridad> |

> Obligatorio incluir al menos un caso negativo/de seguridad por cada acción de escritura o de
> autorización que el bloque introduzca. No basta con el camino feliz.

## Contrato (si `proyectos` incluye más de uno)

- Si este bloque **produce** el contrato: se congela aquí y se registra en
  `_state/contracts/CONTRACT_LOCKS.md` como parte del DoD.
- Si este bloque **consume** un contrato: enlazar el `LOCK-...` correspondiente. Este bloque no
  puede pasar a `ready` sin ese lock vigente (ver `_system/04_CROSS_PROJECT.md` §3).

## Definition of Done

> Copiar del checklist correspondiente en `_system/05_DEFINITION_OF_DONE.md` según el/los proyecto(s)
> de este bloque, y dejarlo como checklist ejecutable aquí.

- [ ] ...

## Evidencia

> Vacío hasta que el bloque se ejecute. El agente pega aquí salidas reales de comandos, capturas de
> verificación funcional, y cualquier cosa que el DoD exija — no un resumen.

## Notas

> Bloqueos, decisiones tomadas durante la ejecución, o el registro de en qué bloques nuevos se
> partió si este resultó más grande de lo esperado.
