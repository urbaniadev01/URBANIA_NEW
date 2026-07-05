---
tipo: estado
proyecto: shared
actualizado: 2026-07-05
---

# BOARD â€” Tablero Ãºnico de estado

> **Este documento es un Ã­ndice/rollup, no una fuente.** Cada fila refleja lo que dice el
> frontmatter de la tarjeta enlazada â€” si alguna vez no coinciden, la tarjeta tiene la razÃ³n y este
> archivo estÃ¡ desactualizado (corregir aquÃ­, nunca al revÃ©s). Ver [[../_system/01_PRINCIPLES#1. Un dato, un dueÃ±o]].
>
> **CÃ³mo usar esto como agente:** tomar el primer bloque en `ready` de arriba hacia abajo â€” el orden
> ya refleja dependencias. Si no hay ninguno en `ready`, detenerse y reportarlo (ver
> [[../_system/00_START_HERE]] Paso 2).

## Features

| Feature | Estado de diseÃ±o | Panorama |
|---|---|---|
| AUTH | approved | [[../features/AUTH/PANORAMA]] |
| API_BOOTSTRAP | approved | [[../features/API_BOOTSTRAP/PANORAMA]] |
| WEB_BOOTSTRAP | approved | [[../features/WEB_BOOTSTRAP/PANORAMA]] |

> `API_BOOTSTRAP` y `WEB_BOOTSTRAP` no son features de negocio â€” son el setup tÃ©cnico que crea
> `code/api/` y `code/web/` (ver `.gitignore` y [[../web/adr/ADR-WEB-001-libreria-componentes]]),
> documentados con el mismo mecanismo por consistencia. Cada feature nueva se agrega aquÃ­ al crear
> su `PANORAMA.md` (estado inicial `draft`), siguiendo [[../_system/00_START_HERE]] Paso 4.

## Bloques â€” API_BOOTSTRAP / WEB_BOOTSTRAP

| ID | Proyecto(s) | Estado | Depende de | Tarjeta |
|---|---|---|---|---|
| API_BOOTSTRAP-B01 | api | **verifying** | â€” | [[../features/API_BOOTSTRAP/blocks/API_BOOTSTRAP-B01-crear-esqueleto-laravel]] |
| WEB_BOOTSTRAP-B01 | web | **done** | â€” | [[../features/WEB_BOOTSTRAP/blocks/WEB_BOOTSTRAP-B01-instalar-shadcn-tailwind]] |

> En este momento no hay bloques `ready`. Los bloques AUTH-B01 a B07 estÃ¡n `done`. AUTH-B08
> y AUTH-B09 estÃ¡n en `backlog` (sin detallar). API_BOOTSTRAP-B01 estÃ¡ en `verifying`.

## Bloques â€” AUTH

| ID | Proyecto(s) | Estado | Depende de | Tarjeta |
|---|---|---|---|---|
| AUTH-B01 | api | **done** | API_BOOTSTRAP-B01 | [[../features/AUTH/blocks/AUTH-B01-registro-por-invitacion]] |
| AUTH-B02 | api | **done** | API_BOOTSTRAP-B01 | [[../features/AUTH/blocks/AUTH-B02-login]] |
| AUTH-B03 | api | **done** | AUTH-B02 | [[../features/AUTH/blocks/AUTH-B03-refresh-token]] |
| AUTH-B04 | api | **done** | AUTH-B02 | [[../features/AUTH/blocks/AUTH-B04-logout]] |
| AUTH-B05 | api | **done** | AUTH-B02 | [[../features/AUTH/blocks/AUTH-B05-rbac-middleware]] |
| AUTH-B06 | web | **done** | AUTH-B02 (lock), WEB_BOOTSTRAP-B01 | [[../features/AUTH/blocks/AUTH-B06-pantalla-login]] |
| AUTH-B07 | web | **done** | AUTH-B01 (lock), WEB_BOOTSTRAP-B01 | [[../features/AUTH/blocks/AUTH-B07-pantalla-registro]] |
| AUTH-B08 | api | backlog | AUTH-B05 | [[../features/AUTH/blocks/AUTH-B08-mfa-enrollment]] |
| AUTH-B09 | api | backlog | AUTH-B02 | [[../features/AUTH/blocks/AUTH-B09-recuperacion-password]] |

## CÃ³mo se actualiza este tablero

Lo actualiza el rol orquestador (ver [[../_system/06_AGENT_ROLES]]) cada vez que una tarjeta cambia
de estado â€” es una ediciÃ³n mecÃ¡nica de una fila, nunca una reinterpretaciÃ³n del progreso.
