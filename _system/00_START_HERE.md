---
tipo: sistema
proyecto: shared
actualizado: 2026-07-03
---

# 00 — Empezar aquí

> El único árbol de decisión que sigue un agente al recibir una tarea. Si estás leyendo esto como
> agente: sigue los pasos en orden, no te saltes a los documentos técnicos sin pasar por aquí
> primero.

## Paso 1 — ¿Qué te están pidiendo?

| Pedido | Ir a |
|---|---|
| "Sigue con el trabajo" / "avanza" (sin más detalle) | Paso 2 |
| Un bloque específico por su ID (`AUTH-B03`, etc.) | Paso 3 |
| Una feature nueva que aún no existe en `features/` | Paso 4 |
| Un cambio que crees que toca más de un proyecto | [[04_CROSS_PROJECT]] |
| Una duda sobre vocabulario de dominio | [[../shared/GLOSSARY]] |

## Paso 2 — Sin bloque específico: leer el tablero

1. Abrir `_state/BOARD.md`.
2. Tomar el primer bloque en `estado: ready` (el orden del tablero ya refleja dependencias).
3. Ir al Paso 3 con ese ID.

Si no hay ningún bloque en `ready`, reportarlo — no se improvisa trabajo fuera del tablero.

## Paso 3 — Ejecutar un bloque

1. Abrir `features/<FEATURE>/blocks/<FEATURE>-B<NN>-*.md`.
2. Confirmar `estado: ready` y que `depende_de` está satisfecho (si no, detenerse y reportar).
3. Si el bloque es cross-project, confirmar el gate de [[04_CROSS_PROJECT]] §3 antes de tocar código.
4. Leer solo los documentos que la propia tarjeta enlaza — ese es el read-set completo
   (ver [[06_AGENT_ROLES]], no se lee el panorama completo del feature salvo que la tarjeta lo pida).
5. Implementar exactamente el alcance de la tarjeta — ni más ni menos (ver [[03_LIFECYCLE]] §2 si
   resulta ser más grande de lo esperado).
6. Cumplir el DoD de la tarjeta ([[05_DEFINITION_OF_DONE]]), pegar evidencia, pasar a `verifying`.

## Paso 4 — Feature nueva

`urbania` evalúa la complejidad y enruta:

| Complejidad | Agente | Método |
|---|---|---|
| **Simple** (1 endpoint, 1 pantalla) | `@doc-agent` | Interactivo — completa PANORAMA.md con el humano |
| **Compleja** (múltiples endpoints/pantallas, reglas intrincadas) | `design-council` | Autónomo — protocolo LLM Council de 3 fases |

Ambos agentes son `hidden: true` — el usuario no los invoca directamente.

1. El agente asignado crea `features/<NOMBRE>/PANORAMA.md`:
   - **Simple:** completando §1–§7 con el usuario.
   - **Complejo:** mediante el protocolo council (divergencia → peer review → síntesis), agregando una sección "Veredicto del Design Council".

   > UI/UX (§7 del panorama) ya no es criterio de enrutamiento: toda feature que marque Web en §2
   > completa §7, sin importar el camino. El council se reserva para complejidad real de negocio
   > (endpoints/reglas múltiples) — cuando corre, su subagente `design-ux` alimenta §7 durante la
   > síntesis en vez de quedar en texto libre (ver [[06_AGENT_ROLES]]).
2. Dejarlo en `estado_diseño: draft` — **no se crean bloques todavía** (gate de [[03_LIFECYCLE]] §3).
3. Reportar al humano que el panorama está listo para revisión. Detenerse ahí.
4. Una vez `estado_diseño: approved`, `@doc-agent` crea `BLOCKS.md` y las tarjetas de bloque.

## Regla general

Si en cualquier paso algo no está claro o falta un documento que este árbol dice que debería existir,
**detenerse y reportarlo** — no se rellena el vacío con una suposición. Esa es la regla que hace que
este sistema, a diferencia del anterior, falle de forma ruidosa en vez de silenciosa.

## Mapa de la metodología completa (por si necesitas más detalle)

1. [[01_PRINCIPLES]] — por qué el vault está diseñado así
2. [[02_CONVENTIONS]] — frontmatter, nombres, numeración, vocabulario de estado
3. [[03_LIFECYCLE]] — el ciclo Feature → Bloque → Sesión
4. [[04_CROSS_PROJECT]] — protocolo cuando un bloque cruza API y Web
5. [[05_DEFINITION_OF_DONE]] — qué evidencia cierra un bloque, por proyecto
6. [[06_AGENT_ROLES]] — qué lee y qué escribe cada rol de agente
