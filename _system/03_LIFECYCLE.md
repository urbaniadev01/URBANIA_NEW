---
tipo: sistema
proyecto: shared
actualizado: 2026-07-05
---

# 03 — Ciclo de vida: Feature → Bloque → Sesión

> El núcleo operativo del vault. Todo agente que va a escribir código debe poder ubicar en qué punto
> de este ciclo está la tarea que le dieron antes de tocar un archivo.

## 1. Ciclo de vida de un Feature

```
1. IDEA           → se nombra el feature, se agrega fila en BOARD (estado agregado: sin diseñar)
2. DRAFT           → PANORAMA.md se escribe. Para features de alta complejidad, `urbania` delega en
                     `design-council` ([[06_AGENT_ROLES#11]]) que genera el panorama por consenso
                     multi-perspectiva. Para features simples, `@doc-agent` usa la plantilla
                     FEATURE_PANORAMA.md directamente.
3. APPROVED         → humano revisa y aprueba el panorama (gate — ver §3)
4. BLOQUEADO EN BLOQUES → BLOCKS.md lista los bloques ordenados, con dependencias y gates marcados
5. EN EJECUCIÓN     → los bloques se ejecutan uno a uno (§2)
6. DONE             → todos los bloques obligatorios del feature están `done` (§6 añade gate de release)
```

Un feature **nunca** pasa directo de IDEA a código. El paso 3 (APPROVED) es el mismo gate que ya
regía en el vault anterior ("no code without approved design") — se mantiene porque es correcto, se
endurece porque ahora tiene una tarjeta y un checklist verificable en vez de un checklist en texto
libre al final de un documento de 700 líneas.

## 2. Ciclo de vida de un Bloque (la unidad de ejecución)

Vocabulario completo en [[02_CONVENTIONS]] §4. La forma en que se recorre en la práctica:

```
backlog ──(tarjeta completa + dependencias satisfechas)──> ready
ready ──(un agente lo toma)──> in_progress
in_progress ──(agente reporta DoD cumplido, evidencia pegada)──> verifying
verifying ──(verificación independiente confirma)──> done
verifying ──(verificación independiente rechaza)──> in_progress   (vuelve, no se descarta el trabajo)
cualquier estado activo ──(impedimento real)──> blocked
blocked ──(impedimento resuelto)──> ready
```

**Regla dura: una sesión de agente ejecuta un único bloque.** Si a mitad de sesión se descubre que
el bloque es más grande de lo que su tarjeta describía, el agente:
1. Detiene la implementación de lo que excede el alcance declarado en la tarjeta.
2. Dentro de la misma sesión, escribe una o más tarjetas de bloque nuevas para lo que sobra
   (siguiente número libre en la secuencia del feature).
3. Cierra el bloque original solo con lo que sí cumple su alcance original, o lo deja `blocked` con
   la nota "se partió en <IDs nuevos>" si nada del alcance original quedó resuelto todavía.

Esto es lo que reemplaza al feature "de corrido": el tamaño de la unidad de trabajo se corrige
partiéndola, nunca extendiendo la sesión más allá de lo planeado.

### Verificación crítica

Para bloques con `verificacion_critica: true` en su frontmatter, la transición
`verifying → done` requiere que el verificador independiente invoque al `verify-council`
([[06_AGENT_ROLES#12]]) antes de decidir. El veredicto del council es un input calificado,
no un reemplazo del verifier — la decisión final sigue siendo del verifier. Si el flag está
ausente o es `false`, el verifier opera solo (comportamiento actual).

## 3. Gate de aprobación de diseño (Feature → Bloques)

Antes de que `BLOCKS.md` pueda existir, `PANORAMA.md` debe tener `estado_diseño: approved` en su
frontmatter. Ningún agente se auto-aprueba: el cambio de `draft` a `approved` lo hace el humano (o,
si el usuario delega explícitamente esa autoridad a un agente para una sesión puntual, se registra
esa delegación en la propia tarjeta del panorama).

Si un agente llega a una tarea y el panorama del feature está en `draft`, **se detiene** y lo
reporta — no interpreta el panorama en borrador como autorización para escribir código. Esto es la
regla "no code without approved design" del vault anterior, ahora con un campo binario verificable
en vez de un checklist de prosa.

## 4. Gate cross-project dentro del ciclo de bloques

Cuando un bloque tiene `proyectos: [api, web]`, no se ejecuta como una sola tarjeta simultánea — se
parte en un bloque de API y un bloque de cliente **enlazados por dependencia**, y el de cliente no
entra en `ready` hasta que el de API está `done` y su contrato está en
`_state/contracts/CONTRACT_LOCKS.md`. El detalle completo del protocolo vive en
[[04_CROSS_PROJECT]] — esta sección solo ubica dónde encaja dentro del ciclo de vida.

## 5. Qué reemplaza esto del vault anterior

| Documento anterior | Reemplazado por |
|---|---|
| `*_SESSION_MANIFEST.md` (estado "guardado" entre sesiones) | El estado vive en cada tarjeta de bloque; "¿dónde vamos?" = leer `_state/BOARD.md` |
| `*_IMPLEMENTATION_PLAN.md` (cola de sesiones futuras) | `features/<F>/BLOCKS.md` (la cola ya es la lista de bloques con su estado) |
| Checklist §14/§15 de 700 líneas al final del panorama | DoD por bloque, pequeño y verificable, dentro de cada tarjeta |
| "Sesión N" como eje temporal | El bloque como eje; la fecha real queda en el frontmatter y en `CHANGELOG.md` |

## 6. Gate de release (Feature → SHIPPED)

Cuando el último bloque de un feature (según `BLOCKS.md`) llega a `estado: done`, el feature no
pasa automáticamente a completado. En su lugar:

1. `urbania` invoca al `release-council` ([[06_AGENT_ROLES#14]]).
2. El council emite un veredicto multi-perspectiva: 🟢 GO, 🟡 GO CON CONDICIONES, o 🔴 NO-GO.
3. Si es **GO**, se agrega la entrada de cierre en `_state/CHANGELOG.md` y el feature se marca
   `DONE` en `_state/BOARD.md`.
4. Si es **GO CON CONDICIONES**, se documentan las condiciones y el feature avanza con deuda
   registrada. Las condiciones se agregan como nota en `_state/BOARD.md`.
5. Si es **NO-GO**, el feature vuelve a estado activo — los bloques que requieren corrección se
   identifican, se crean tarjetas nuevas (`<FEATURE>-B<NN+1>`) y el ciclo continúa.

El release-council también puede invocarse manualmente en cualquier momento ("¿estamos listos para
producción?").
