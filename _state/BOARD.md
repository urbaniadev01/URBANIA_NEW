---
tipo: estado
proyecto: shared
actualizado: 2026-07-09
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
| DIRECTORIO | approved | [[../features/DIRECTORIO/PANORAMA]] |
| DASHBOARD | approved | [[../features/DASHBOARD/PANORAMA]] |
| COBRANZA | approved | [[../features/COBRANZA/PANORAMA]] |

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
| AUTH-B13 | web | **done** | AUTH-B09 (lock) | [[../features/AUTH/blocks/AUTH-B13-reset-password-web]] |
| AUTH-B14 | api | **done** | AUTH-B02, AUTH-B08 | [[../features/AUTH/blocks/AUTH-B14-fix-entorno-local-navegador]] |
| AUTH-B15 | api, web | **done** | AUTH-B02 | [[../features/AUTH/blocks/AUTH-B15-endpoint-me-dashboard]] |
| AUTH-B16 | web | **done** | — | [[../features/AUTH/blocks/AUTH-B16-route-guard-rutas-privadas]] |

## Bloques — PROPIEDADES

| ID              | Proyecto(s) | Estado    | Depende de                                | Tarjeta                                                                        |
| --------------- | ----------- | --------- | ----------------------------------------- | ------------------------------------------------------------------------------ |
| PROPIEDADES-B01 | api         | **done**  | API_BOOTSTRAP-B01                         | [[../features/PROPIEDADES/blocks/PROPIEDADES-B01-migraciones-modelos-seeders]] |
| PROPIEDADES-B02 | api         | **done**  | PROPIEDADES-B01                           | [[../features/PROPIEDADES/blocks/PROPIEDADES-B02-crud-catalogos]]              |
| PROPIEDADES-B03 | api         | **done**  | PROPIEDADES-B01                           | [[../features/PROPIEDADES/blocks/PROPIEDADES-B03-crud-condominios-torres]]     |
| PROPIEDADES-B04 | api         | **done**  | PROPIEDADES-B01                           | [[../features/PROPIEDADES/blocks/PROPIEDADES-B04-crud-unidades]]               |
| PROPIEDADES-B05 | api         | **done**  | PROPIEDADES-B01                           | [[../features/PROPIEDADES/blocks/PROPIEDADES-B05-coeficientes-tree]]           |
| PROPIEDADES-B06 | web         | **in_progress** | PROPIEDADES-B02 (lock), WEB_BOOTSTRAP-B01 | [[../features/PROPIEDADES/blocks/PROPIEDADES-B06-pantallas-catalogos]]         |
| PROPIEDADES-B07 | web         | **in_progress** | PROPIEDADES-B03 (lock), WEB_BOOTSTRAP-B01 | [[../features/PROPIEDADES/blocks/PROPIEDADES-B07-pantallas-condominios]]       |
| PROPIEDADES-B08 | web         | **in_progress** | PROPIEDADES-B04 (lock), WEB_BOOTSTRAP-B01 | [[../features/PROPIEDADES/blocks/PROPIEDADES-B08-pantalla-unidades]]           |
| PROPIEDADES-B09 | web         | **in_progress** | PROPIEDADES-B05 (lock), WEB_BOOTSTRAP-B01 | [[../features/PROPIEDADES/blocks/PROPIEDADES-B09-pantalla-coeficientes]]       |

## Bloques — DIRECTORIO

| ID             | Proyecto(s) | Estado      | Depende de                               | Tarjeta                                                                        |
| -------------- | ----------- | ----------- | ---------------------------------------- | ------------------------------------------------------------------------------ |
| DIRECTORIO-B01 | api         | **ready**   | AUTH-B01, PROPIEDADES-B01                | [[../features/DIRECTORIO/blocks/DIRECTORIO-B01-fundacion-contactos-ocupantes]] |
| DIRECTORIO-B02 | api         | **backlog** | DIRECTORIO-B01                           | [[../features/DIRECTORIO/blocks/DIRECTORIO-B02-crud-tipos-ocupante]]           |
| DIRECTORIO-B03 | api         | **backlog** | DIRECTORIO-B01                           | [[../features/DIRECTORIO/blocks/DIRECTORIO-B03-crud-contactos]]                |
| DIRECTORIO-B04 | api         | **backlog** | DIRECTORIO-B01                           | [[../features/DIRECTORIO/blocks/DIRECTORIO-B04-asignacion-ocupantes]]          |
| DIRECTORIO-B05 | web         | **backlog** | DIRECTORIO-B02 (lock), WEB_BOOTSTRAP-B01 | [[../features/DIRECTORIO/blocks/DIRECTORIO-B05-pantalla-tipos-ocupante]]       |
| DIRECTORIO-B06 | web         | **backlog** | DIRECTORIO-B03 (lock), WEB_BOOTSTRAP-B01 | [[../features/DIRECTORIO/blocks/DIRECTORIO-B06-pantalla-directorio-contactos]] |
| DIRECTORIO-B07 | web         | **backlog** | DIRECTORIO-B04 (lock), WEB_BOOTSTRAP-B01 | [[../features/DIRECTORIO/blocks/DIRECTORIO-B07-pantalla-asignacion-ocupantes]] |

## Bloques — DASHBOARD

| ID | Proyecto(s) | Estado | Depende de | Tarjeta |
|---|---|---|---|---|
| DASHBOARD-B01 | web | **done** | WEB_BOOTSTRAP-B01 | [[../features/DASHBOARD/blocks/DASHBOARD-B01-widget-registry-core]] |
| DASHBOARD-B02 | web | **done** | DASHBOARD-B01, PROPIEDADES-B03 (lock), PROPIEDADES-B04 (lock), PROPIEDADES-B05 (lock) | [[../features/DASHBOARD/blocks/DASHBOARD-B02-propiedades-widgets]] |
| DASHBOARD-B03 | web | **done** | DASHBOARD-B01 | [[../features/DASHBOARD/blocks/DASHBOARD-B03-core-widgets-placeholders]] |

> **Nota (2026-07-07):** Diagnóstico de infraestructura: `codebase-memory` está instalado pero su
> índice actual (534 nodos) solo cubre la documentación del vault — no incluye el código fuente en
> `code/api/` ni `code/web/`. `search_graph` no funciona. Pendiente: reindexar en modo `full` para
> que los agentes puedan usar análisis de código real. Ver `_state/RUNBOOK.md#E-002`.
>
> **Nota (2026-07-08, actualizada):** `PROPIEDADES-B04` (CRUD de unidades) implementa un guard clause
> temporal para la regla "no eliminar unidad con ocupantes activos" (R-03), porque `property_occupants`
> pertenecía a una feature que todavía no existía. Ya no es una brecha abierta: **`DIRECTORIO-B01`**
> (ver más abajo, `ready`) crea `property_occupants` y su propia tarjeta (criterios 16-17) resuelve
> explícitamente reemplazar el guard clause si `PROPIEDADES-B04` ya está `done` para esa fecha, o
> dejar la tabla lista para que `PROPIEDADES-B04` la consulte directamente si todavía no se ha
> implementado. Ver [[../features/PROPIEDADES/blocks/PROPIEDADES-B04-crud-unidades#Notas]] y
> [[../features/DIRECTORIO/blocks/DIRECTORIO-B01-fundacion-contactos-ocupantes]].
>
> **Nota (2026-07-08):** `DIRECTORIO-B01` también corrige `contacts` (de `AUTH-B01`, ya `SHIPPED`):
> `user_id` pasa a nullable y se agrega `organization_id` propio, porque el diseño aprobado de AUTH
> ya prometía "un contact puede existir sin user" (ADR-001) pero la migración real lo implementó
> como NOT NULL. Incluye un parche de una línea en `RegisterUserUseCase` con test de regresión — ver
> esa tarjeta antes de ejecutarla, toca código ya en producción.

## Bloques — COBRANZA

| ID | Proyecto(s) | Estado | Depende de | Tarjeta |
|---|---|---|---|---|
| COBRANZA-B01 | api | **backlog** | PROPIEDADES-B01, DIRECTORIO-B01 | [[../features/COBRANZA/blocks/COBRANZA-B01-migraciones-modelos-seeders]] |
| COBRANZA-B02 | api | **backlog** | COBRANZA-B01 | [[../features/COBRANZA/blocks/COBRANZA-B02-crud-conceptos-cobro]] |
| COBRANZA-B03 | api | **backlog** | COBRANZA-B02 | [[../features/COBRANZA/blocks/COBRANZA-B03-periodos-facturacion]] |
| COBRANZA-B04 | api | **backlog** | COBRANZA-B03 | [[../features/COBRANZA/blocks/COBRANZA-B04-cuentas-cobro]] |
| COBRANZA-B05 | api | **backlog** | COBRANZA-B04 | [[../features/COBRANZA/blocks/COBRANZA-B05-pagos]] |
| COBRANZA-B06 | api | **backlog** | COBRANZA-B05 | [[../features/COBRANZA/blocks/COBRANZA-B06-paz-y-salvo]] |
| COBRANZA-B07 | web | **backlog** | COBRANZA-B02 (lock), WEB_BOOTSTRAP-B01 | [[../features/COBRANZA/blocks/COBRANZA-B07-pantallas-conceptos-cobro]] |
| COBRANZA-B08 | web | **backlog** | COBRANZA-B03 (lock), WEB_BOOTSTRAP-B01 | [[../features/COBRANZA/blocks/COBRANZA-B08-pantallas-periodos-facturacion]] |
| COBRANZA-B09 | web | **backlog** | COBRANZA-B04 (lock), WEB_BOOTSTRAP-B01 | [[../features/COBRANZA/blocks/COBRANZA-B09-pantalla-cuentas-cobro]] |
| COBRANZA-B10 | web | **backlog** | COBRANZA-B05 (lock), WEB_BOOTSTRAP-B01 | [[../features/COBRANZA/blocks/COBRANZA-B10-pantalla-pagos]] |
| COBRANZA-B11 | web | **backlog** | COBRANZA-B06 (lock), WEB_BOOTSTRAP-B01 | [[../features/COBRANZA/blocks/COBRANZA-B11-pantalla-paz-y-salvo]] |

> Ningún bloque de `COBRANZA` está en `ready` todavía: `COBRANZA-B01` depende de `DIRECTORIO-B01`
> (hoy `ready`, no `done`). Prerrequisito de diseño ya resuelto:
> [[../shared/adr/ADR-002-lectura-cross-context-modulo-monolito]] (`Aceptada`) — `COBRANZA-B01` puede
> ejecutarse en cuanto `DIRECTORIO-B01` llegue a `done`. `COBRANZA-B03` y `COBRANZA-B05` llevan
> `verificacion_critica: true` (cálculo financiero de facturación y locking de pagos,
> respectivamente), igual que `COBRANZA-B11` (último bloque del feature). Ver
> [[../features/COBRANZA/BLOCKS]] para el detalle de la cadena y la acción pendiente cross-feature
> con `DASHBOARD`.

## Cómo se actualiza este tablero

Lo actualiza el rol orquestador (ver [[../_system/06_AGENT_ROLES]]) cada vez que una tarjeta cambia
de estado — es una edición mecánica de una fila, nunca una reinterpretación del progreso.
