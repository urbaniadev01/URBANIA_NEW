---
tipo: sistema
proyecto: shared
actualizado: 2026-07-05
---

# AGENTS — Punto de entrada del vault

> Leer esto primero, siempre. Es intencionalmente corto — el árbol de decisión completo vive en `_system/00_START_HERE.md`; este archivo solo confirma que estás en el lugar correcto y te manda ahí.

## Qué es este vault

Documentación del sistema Urbania (API + Web; App diferido). Un feature se diseña una vez
(`features/<F>/PANORAMA.md`) y se ejecuta en bloques pequeños, uno por sesión de agente — nunca un
feature completo de corrido. El estado de todo vive en `_state/BOARD.md`, no en ningún otro
documento.

## Siguiente paso

→ **[`_system/00_START_HERE.md`](_system/00_START_HERE.md)** — el árbol de decisión que sigue
cualquier agente al recibir una tarea.

No hay más que leer aquí. Si llegaste a este archivo buscando reglas de negocio, convenciones
técnicas, o el estado de una feature específica, no están aquí — siguen el mapa desde
`00_START_HERE.md`.

> [!note] `GUIA_DESARROLLO.md`
> Existe un manual del desarrollador en `GUIA_DESARROLLO.md` — es lectura humana, no de agente. Si
> un agente llega ahí buscando su propio flujo, el read-set real sigue siendo `_system/`.

## Mapa rápido (por si ya sabes qué buscas)

| Necesito... | Voy a |
|---|---|
| Entender la metodología completa | `_system/` (documentos numerados, orden de lectura) |
| Ver qué hay que hacer ahora mismo | `_state/BOARD.md` |
| Ver el vocabulario de dominio o una decisión de arquitectura | `shared/` |
| Ver el diseño de una feature y sus bloques | `features/<NOMBRE>/` |
| Convenciones técnicas de API | `api/API_AGENTS.md` |
| Convenciones técnicas de Web | `web/WEB_AGENTS.md` |
| Auditar la integridad del vault | `@auditor` o pedile a `urbania` que lo invoque |
| Diseñar una feature nueva compleja | `@design-council` o pedile a `urbania` que lo invoque |
