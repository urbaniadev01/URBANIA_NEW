# Urbania — vault de documentación (rediseño)

Este es el vault de documentación del sistema Urbania, rediseñado desde cero para que un agente de
IA (vía OpenCode) tenga el mínimo margen de error al interpretar y ejecutar trabajo. No es una copia
del vault anterior (`D:\Programacion\URBANIA`) — es un sistema nuevo, construido en punto 0, listo
para lanzar desarrollo limpio de API + Web (App queda diferido, ver `app/APP_DEFERRED.md`).

## Si eres un agente

Empieza en [`AGENTS.md`](AGENTS.md) — no en este README.

## Si eres una persona

- **Cómo trabajar día a día, paso a paso:** [`GUIA_DESARROLLO.md`](GUIA_DESARROLLO.md) — el manual
  del desarrollador. Empieza acá si sos nuevo en el proyecto; explica exactamente cómo interactuar
  con OpenCode en cada situación (consultar estado, diseñar y aprobar una feature, ejecutar un
  bloque, revisar evidencia, manejar un bloqueo, etc.).
- **Qué cambió y por qué:** `_system/01_PRINCIPLES.md` explica, principio por principio, qué
  problema del vault anterior resuelve cada regla nueva.
- **Cómo se organiza el trabajo ahora:** un feature se diseña una vez (`features/<F>/PANORAMA.md`) y
  se ejecuta en bloques pequeños (`features/<F>/blocks/*.md`) — nunca de corrido. El estado de todo
  vive en un solo tablero: `_state/BOARD.md`.
- **El ejemplo de referencia** es el feature `AUTH` (`features/AUTH/`) — léelo si quieres ver cómo se
  ve un panorama, un plan de bloques y una tarjeta de bloque completos en la práctica.

## Estructura

```
_system/    ← metodología: cómo trabajamos (estable, se lee poco pero se sigue siempre)
_state/     ← estado vivo: el tablero único, el changelog, los contratos congelados
shared/     ← verdad cross-project: glosario, ADRs, contrato entre API y Web
features/   ← diseño de cada feature + sus bloques de ejecución
api/        ← documentación técnica del backend (Laravel + DDD)
web/        ← documentación técnica del frontend (Vite + React)
app/        ← diferido — un solo marcador hasta que arranque
```

## Código fuente

Este vault documenta, no aloja código. El código de API y Web vive en carpetas de proyecto
separadas (mismo patrón que el vault anterior: `API/`, `WEB/` como repos independientes) — ver
`AGENTS.md` §1 para la separación exacta una vez que esas carpetas existan.
