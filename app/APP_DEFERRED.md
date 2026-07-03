---
tipo: referencia
proyecto: app
actualizado: 2026-07-03
---

# APP — Track diferido

> El proyecto App (Flutter) no arranca en esta fase. Este archivo es el único marcador del proyecto
> hasta que se decida iniciarlo — no crear `APP_ARCHITECTURE.md` ni ningún otro documento técnico de
> App antes de eso.

## Por qué está diferido

El usuario definió el orden de trabajo: API + Web primero; App se retoma cuando haya avance
suficiente. Diseñar la documentación técnica de App ahora, sin contrato de API estable ni patrones
de Web probados, produciría documentación especulativa — exactamente lo que este vault existe para
evitar.

## Criterio de arranque

App se activa cuando se cumplan **todas** estas condiciones:

1. El feature AUTH está `done` en API (los bloques `AUTH-B01`–`AUTH-B05` como mínimo).
2. Existe al menos un feature de negocio adicional completo en API + Web, de forma que App tenga
   patrones reales (no solo autenticación) contra los cuales diseñar su propia arquitectura.
3. El usuario lo decide explícitamente — no es una fecha, es una decisión de scope.

## Qué pasa al activarlo

1. Se crea `app/APP_AGENTS.md`, `app/APP_ARCHITECTURE.md`, etc., siguiendo el mismo patrón que
   `api/` y `web/` (documentación técnica completa, no placeholders — ver
   `_system/02_CONVENTIONS.md`).
2. Cada feature ya `done` en API que App vaya a consumir se re-evalúa: sus bloques de cliente para
   App se agregan a `BLOCKS.md` de esa feature como bloques nuevos (no se reabren los bloques de API
   ya cerrados) con `proyectos: [app]`, dependiendo de los locks de contrato ya existentes en
   `_state/contracts/CONTRACT_LOCKS.md` — el mismo mecanismo que hoy usan los bloques de Web.
3. `_system/04_CROSS_PROJECT.md` no necesita cambios — su máquina de estados ya contempla `app` como
   uno de los proyectos posibles en `proyectos: [...]`.
