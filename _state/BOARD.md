---
tipo: estado
proyecto: shared
actualizado: 2026-07-07
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
| AUTH | SHIPPED | [[../features/AUTH/PANORAMA]] |
| API_BOOTSTRAP | approved | [[../features/API_BOOTSTRAP/PANORAMA]] |
| WEB_BOOTSTRAP | approved | [[../features/WEB_BOOTSTRAP/PANORAMA]] |
| PROPIEDADES | approved | [[../features/PROPIEDADES/PANORAMA]] |

> `API_BOOTSTRAP` y `WEB_BOOTSTRAP` no son features de negocio — son el setup técnico que crea
> `code/api/` y `code/web/` (ver [[../web/adr/ADR-WEB-001-libreria-componentes]]),
> documentados con el mismo mecanismo por consistencia. Cada feature nueva se agrega aquí al crear
> su `PANORAMA.md` (estado inicial `draft`), siguiendo [[../_system/00_START_HERE]] Paso 4.

## Bloques — API_BOOTSTRAP / WEB_BOOTSTRAP

| ID | Proyecto(s) | Estado | Depende de | Tarjeta |
|---|---|---|---|---|
| API_BOOTSTRAP-B01 | api | **done** | — | [[../features/API_BOOTSTRAP/blocks/API_BOOTSTRAP-B01-crear-esqueleto-laravel]] |
| WEB_BOOTSTRAP-B01 | web | **done** | — | [[../features/WEB_BOOTSTRAP/blocks/WEB_BOOTSTRAP-B01-instalar-shadcn-tailwind]] |

## Bloques — AUTH

| ID | Proyecto(s) | Estado | Depende de | Tarjeta |
|---|---|---|---|---|
| AUTH-B01 | api | **done** | API_BOOTSTRAP-B01 | [[../features/AUTH/blocks/AUTH-B01-registro-por-invitacion]] |
| AUTH-B02 | api | **done** | API_BOOTSTRAP-B01 | [[../features/AUTH/blocks/AUTH-B02-login]] |
| AUTH-B03 | api | **done** | AUTH-B02 | [[../features/AUTH/blocks/AUTH-B03-refresh-token]] |
| AUTH-B04 | api | **done** | AUTH-B02 | [[../features/AUTH/blocks/AUTH-B04-logout]] |
| AUTH-B05 | api | **done** | AUTH-B02 | [[../features/AUTH/blocks/AUTH-B05-rbac-middleware]] |
| AUTH-B06 | web | **done** | AUTH-B02 (lock), WEB_BOOTSTRAP-B01 | [[../features/AUTH/blocks/AUTH-B06-pantalla-login]] |
| AUTH-B07 | web | **done** | AUTH-B01 (lock), WEB_BOOTSTRAP-B01 | [[../features/AUTH/blocks/AUTH-B07-pantalla-registro]] |
| AUTH-B08 | api | **done** | AUTH-B05 | [[../features/AUTH/blocks/AUTH-B08-mfa-enrollment]] |
| AUTH-B09 | api | **done** | AUTH-B02 | [[../features/AUTH/blocks/AUTH-B09-recuperacion-password]] |
| AUTH-B10 | web | **done** | AUTH-B08 (lock) | [[../features/AUTH/blocks/AUTH-B10-mfa-verify-web]] |
| AUTH-B11 | web | **done** | AUTH-B08 (lock) | [[../features/AUTH/blocks/AUTH-B11-mfa-enroll-web]] |
| AUTH-B12 | web | **done** | AUTH-B09 (lock) | [[../features/AUTH/blocks/AUTH-B12-forgot-password-web]] |
| AUTH-B13 | web | **ready** | AUTH-B09 (lock) | [[../features/AUTH/blocks/AUTH-B13-reset-password-web]] |

## Bloques — PROPIEDADES

| ID | Proyecto(s) | Estado | Depende de | Tarjeta |
|---|---|---|---|---|
| PROPIEDADES-B01 | api | **ready** | API_BOOTSTRAP-B01 | [[../features/PROPIEDADES/blocks/PROPIEDADES-B01-migraciones-modelos-seeders]] |
| PROPIEDADES-B02 | api | **backlog** | PROPIEDADES-B01 | [[../features/PROPIEDADES/blocks/PROPIEDADES-B02-crud-catalogos]] |
| PROPIEDADES-B03 | api | **backlog** | PROPIEDADES-B01 | [[../features/PROPIEDADES/blocks/PROPIEDADES-B03-crud-condominios-torres]] |
| PROPIEDADES-B04 | api | **backlog** | PROPIEDADES-B01 | [[../features/PROPIEDADES/blocks/PROPIEDADES-B04-crud-unidades]] |
| PROPIEDADES-B05 | api | **backlog** | PROPIEDADES-B01 | [[../features/PROPIEDADES/blocks/PROPIEDADES-B05-coeficientes-tree]] |
| PROPIEDADES-B06 | web | **backlog** | PROPIEDADES-B02 (lock), WEB_BOOTSTRAP-B01 | [[../features/PROPIEDADES/blocks/PROPIEDADES-B06-pantallas-catalogos]] |
| PROPIEDADES-B07 | web | **backlog** | PROPIEDADES-B03 (lock), WEB_BOOTSTRAP-B01 | [[../features/PROPIEDADES/blocks/PROPIEDADES-B07-pantallas-condominios]] |
| PROPIEDADES-B08 | web | **backlog** | PROPIEDADES-B04 (lock), WEB_BOOTSTRAP-B01 | [[../features/PROPIEDADES/blocks/PROPIEDADES-B08-pantalla-unidades]] |
| PROPIEDADES-B09 | web | **backlog** | PROPIEDADES-B05 (lock), WEB_BOOTSTRAP-B01 | [[../features/PROPIEDADES/blocks/PROPIEDADES-B09-pantalla-coeficientes]] |

> **Nota (2026-07-07):** Diagnóstico de infraestructura: `codebase-memory` está instalado pero su
> índice actual (534 nodos) solo cubre la documentación del vault — no incluye el código fuente en
> `code/api/` ni `code/web/`. `search_graph` no funciona. Pendiente: reindexar en modo `full` para
> que los agentes puedan usar análisis de código real. Ver `_state/RUNBOOK.md#E-002`.

## Cómo se actualiza este tablero

Lo actualiza el rol orquestador (ver [[../_system/06_AGENT_ROLES]]) cada vez que una tarjeta cambia
de estado — es una edición mecánica de una fila, nunca una reinterpretación del progreso.
