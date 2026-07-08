---
name: new-feature
description: Crear el panorama de una feature nueva y, una vez aprobado, su plan de bloques y tarjetas. La ruta depende de la complejidad: @doc-agent para features simples, design-council para features complejos.
---

## Gate de complejidad — siempre antes de empezar

`urbania` evalúa la feature request y decide la ruta:

| Complejidad | Agente | Método |
|---|---|---|
| **Simple** (1 endpoint, 1 pantalla, reglas directas) | `@doc-agent` | Interactivo — completa PANORAMA.md con el usuario |
| **Compleja** (múltiples endpoints/pantallas, reglas intrincadas) | `design-council` | Autónomo — protocolo LLM Council de 3 fases con subagentes especializados |

Ambos agentes son `hidden: true` — el usuario no los invoca directamente, siempre pasa por `urbania`.

---

## Ruta A — Feature simple (`@doc-agent`)

### Paso 1 — Panorama (interactivo)

1. Copiar `_system/templates/FEATURE_PANORAMA.md` a `features/<NOMBRE>/PANORAMA.md`.
2. Completar §1–§6 **con el usuario**. §4 (modelo de datos) debe declarar Valor/Referencia para cada campo nuevo — nunca dejarlo implícito.
3. Frontmatter `estado_diseño: draft`.
4. Marcar §8 (checklist de aprobación) a medida que se cumple.
5. Detenerse aquí y reportar: "panorama listo para revisión".

**No avanzar al paso 2 sin que un humano cambie `estado_diseño` a `approved`.**

### Paso 2 — Plan de bloques (solo si `estado_diseño: approved`)

1. Crear `features/<NOMBRE>/BLOCKS.md` — usar `features/AUTH/BLOCKS.md` como referencia de formato (diagrama de orden + tabla con dependencias).
2. Para cada bloque, copiar `_system/templates/BLOCK.md` a `features/<NOMBRE>/blocks/<NOMBRE>-B<NN>-<slug>.md`.
3. Completar cada tarjeta: Objetivo, Alcance (con "no incluye" explícito), Criterios de aceptación (con al menos un caso negativo por cada acción de escritura/autorización), Definition of Done copiado de `_system/05_DEFINITION_OF_DONE.md` para el/los proyecto(s) del bloque.
4. Marcar `estado: ready` solo en los bloques sin dependencias pendientes; el resto queda `backlog`.
5. Agregar las filas en `_state/BOARD.md`.

---

## Ruta B — Feature complejo (`design-council`)

### Paso 1 — Panorama (autónomo, protocolo LLM Council)

`design-council` **no interactúa con el usuario** durante el diseño — opera en 3 fases:

1. **Fase 1 — Divergencia:** invoca 3 subagentes en paralelo:
   - `design-architect` — arquitectura, modelo de datos, endpoints, escalabilidad
   - `design-ux` — flujos de usuario, pantallas, experiencia
   - `design-security` — superficie de ataque, permisos, datos sensibles
2. **Fase 2 — Peer Review anonimizado:** los 3 diseños se anonimizan (A/B/C) y cada subagente revisa los 3, produciendo ranking, fortalezas, debilidades y puntos ciegos.
3. **Fase 3 — Síntesis:** el primario produce un `PANORAMA.md` unificado que incluye:
   - §1–§6 de la plantilla estándar
   - **§X "Veredicto del Design Council"** — documenta puntos de acuerdo, divergencias y cómo se resolvieron, y recomendación final del council

El panorama queda en `estado_diseño: draft`. **No se crean bloques** — `design-council` solo produce el PANORAMA.md.

**No avanzar al paso 2 sin que un humano cambie `estado_diseño` a `approved`.**

### Paso 2 — Plan de bloques (vuelve a `@doc-agent`)

Una vez `approved`, `urbania` delega la creación de `BLOCKS.md` y las tarjetas a `@doc-agent` (mismo procedimiento que Ruta A, Paso 2). `design-council` **nunca** crea bloques ni `BLOCKS.md`.

---

## Regla de tamaño de bloque

Si un bloque candidato requeriría más de una sesión de agente para cumplir su Definition of Done, está mal cortado — dividirlo antes de crear la tarjeta, no después.
