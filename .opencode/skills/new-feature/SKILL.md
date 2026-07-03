---
name: new-feature
description: Crear el panorama de una feature nueva y, una vez aprobado, su plan de bloques y tarjetas.
---

## Paso 1 — Panorama (siempre)

1. Copiar `_system/templates/FEATURE_PANORAMA.md` a `features/<NOMBRE>/PANORAMA.md`.
2. Completar §1–§6 con el usuario. §4 (modelo de datos) debe declarar Valor/Referencia para cada
   campo nuevo — nunca dejarlo implícito.
3. Frontmatter `estado_diseño: draft`.
4. Marcar §8 (checklist de aprobación) a medida que se cumple.
5. Detenerse aquí y reportar: "panorama listo para revisión".

**No avanzar al paso 2 sin que un humano cambie `estado_diseño` a `approved`.**

## Paso 2 — Plan de bloques (solo si `estado_diseño: approved`)

1. Crear `features/<NOMBRE>/BLOCKS.md` — usar `features/AUTH/BLOCKS.md` como referencia de formato
   (diagrama de orden + tabla con dependencias).
2. Para cada bloque, copiar `_system/templates/BLOCK.md` a
   `features/<NOMBRE>/blocks/<NOMBRE>-B<NN>-<slug>.md`.
3. Completar cada tarjeta: Objetivo, Alcance (con "no incluye" explícito), Criterios de aceptación
   (con al menos un caso negativo por cada acción de escritura/autorización), Definition of Done
   copiado de `_system/05_DEFINITION_OF_DONE.md` para el/los proyecto(s) del bloque.
4. Marcar `estado: ready` solo en los bloques sin dependencias pendientes; el resto queda `backlog`.
5. Agregar las filas en `_state/BOARD.md`.

## Regla de tamaño de bloque

Si un bloque candidato requeriría más de una sesión de agente para cumplir su Definition of Done,
está mal cortado — dividirlo antes de crear la tarjeta, no después.
