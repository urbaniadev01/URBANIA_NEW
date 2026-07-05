---
tipo: estado
proyecto: shared
actualizado: 2026-07-04
---

# BOARD — Tablero único de estado

> **Este documento es un índice/rollup, no una fuente.** Cada fila refleja lo que dice el
> frontmatter de la tarjeta enlazada — si alguna vez no coinciden, la tarjeta tiene la razón y este
> archivo está desactualizado (corregir aquí, nunca al revés). Ver [[../_system/01_PRINCIPLES#1. Un dato, un dueño]].
>
> **Cómo usar esto como agente:** tomar el primer bloque en `ready` de arriba hacia abajo — el orden
> ya refleja dependencias. Si no hay ninguno en `ready`, detenerse y reportarlo (ver
> [[../_system/00_START_HERE]] Paso 2).

## Features

| Feature | Estado de diseño | Panorama |
|---|---|---|
| AUTH | approved | [[../features/AUTH/PANORAMA]] |
| API_BOOTSTRAP | approved | [[../features/API_BOOTSTRAP/PANORAMA]] |
| WEB_BOOTSTRAP | approved | [[../features/WEB_BOOTSTRAP/PANORAMA]] |

> `API_BOOTSTRAP` y `WEB_BOOTSTRAP` no son features de negocio — son el setup técnico que crea
> `code/api/` y `code/web/` (ver `.gitignore` y [[../web/adr/ADR-WEB-001-libreria-componentes]]),
> documentados con el mismo mecanismo por consistencia. Cada feature nueva se agrega aquí al crear
> su `PANORAMA.md` (estado inicial `draft`), siguiendo [[../_system/00_START_HERE]] Paso 4.

## Bloques — API_BOOTSTRAP / WEB_BOOTSTRAP

| ID | Proyecto(s) | Estado | Depende de | Tarjeta |
|---|---|---|---|---|
| API_BOOTSTRAP-B01 | api | **done** | — | [[../features/API_BOOTSTRAP/blocks/API_BOOTSTRAP-B01-crear-esqueleto-laravel]] |
| WEB_BOOTSTRAP-B01 | web | **done** | — | [[../features/WEB_BOOTSTRAP/blocks/WEB_BOOTSTRAP-B01-instalar-shadcn-tailwind]] |

> Los 4 bloques iniciales ya están `done`. En este momento no hay bloques `ready` — todos los
> bloques restantes (AUTH-B03 al AUTH-B09) están en `backlog`.

## Bloques — AUTH

| ID | Proyecto(s) | Estado | Depende de | Tarjeta |
|---|---|---|---|---|
| AUTH-B01 | api | **done** | API_BOOTSTRAP-B01 | [[../features/AUTH/blocks/AUTH-B01-registro-por-invitacion]] |
| AUTH-B02 | api | **done** | API_BOOTSTRAP-B01 | [[../features/AUTH/blocks/AUTH-B02-login]] |
| AUTH-B03 | api | backlog | AUTH-B02 | [[../features/AUTH/blocks/AUTH-B03-refresh-token]] |
| AUTH-B04 | api | backlog | AUTH-B02 | [[../features/AUTH/blocks/AUTH-B04-logout]] |
| AUTH-B05 | api | backlog | AUTH-B02 | [[../features/AUTH/blocks/AUTH-B05-rbac-middleware]] |
| AUTH-B06 | web | backlog | AUTH-B02 (lock), WEB_BOOTSTRAP-B01 | [[../features/AUTH/blocks/AUTH-B06-pantalla-login]] |
| AUTH-B07 | web | backlog | AUTH-B01 (lock), WEB_BOOTSTRAP-B01 | [[../features/AUTH/blocks/AUTH-B07-pantalla-registro]] |
| AUTH-B08 | api | backlog (sin detallar) | AUTH-B05 | [[../features/AUTH/blocks/AUTH-B08-mfa-enrollment]] |
| AUTH-B09 | api | backlog (sin detallar) | AUTH-B02 | [[../features/AUTH/blocks/AUTH-B09-recuperacion-password]] |

> `AUTH-B03`/`AUTH-B04`/`AUTH-B05` dependen de `AUTH-B02` porque necesitan la sesión ya emitida para
> tener algo que refrescar/revocar/autorizar — pero sus tarjetas ya están completas (`backlog` aquí
> significa "esperando su turno", no "sin diseñar"; ver nota en cada tarjeta). Al terminar
> `AUTH-B02`, pasan a `ready` manualmente por el orquestador tras confirmar la dependencia.

## Cómo se actualiza este tablero

Lo actualiza el rol orquestador (ver [[../_system/06_AGENT_ROLES]]) cada vez que una tarjeta cambia
de estado — es una edición mecánica de una fila, nunca una reinterpretación del progreso.
