---
tipo: feature
proyecto: shared
feature: AUTH
actualizado: 2026-07-05
---

# AUTH — Plan de bloques

> Orden de ejecución, dependencias y gates. Este documento es el índice de bloques del feature — el
> estado real de cada uno vive en su propia tarjeta (ver [[../../_system/01_PRINCIPLES#1. Un dato, un dueño]]);
> `_state/BOARD.md` es el rollup global que agrega esto junto con el resto del vault.

## Orden

```
API_BOOTSTRAP-B01 (api, crea code/api/)   WEB_BOOTSTRAP-B01 (web, crea code/web/)
        │                                          │
        ├──> AUTH-B01 (api, registro) ─┐           │
        └──> AUTH-B02 (api, login)   ──┼───────────┤
                    │                  │            │
                    ├──> AUTH-B03 (api, refresh)          depende de B02
                    ├──> AUTH-B04 (api, logout)            depende de B02
                    └──> AUTH-B05 (api, RBAC middleware)   depende de B02

AUTH-B01 ──lock──┬──────────────┴──> AUTH-B07 (web, pantalla registro)
AUTH-B02 ──lock──┴──────────────┬──> AUTH-B06 (web, pantalla login)
                     WEB_BOOTSTRAP-B01 ┘

AUTH-B05 ──> AUTH-B08 (api, MFA — sin detallar todavía)
AUTH-B02 ──> AUTH-B09 (api, recuperación de contraseña — sin detallar todavía)
```

## Tabla

| ID | Proyecto | Depende de | Estado | Tarjeta |
|---|---|---|---|---|
| AUTH-B01 | api | API_BOOTSTRAP-B01 | done | [[blocks/AUTH-B01-registro-por-invitacion]] |
| AUTH-B02 | api | API_BOOTSTRAP-B01 | done | [[blocks/AUTH-B02-login]] |
| AUTH-B03 | api | AUTH-B02 | done | [[blocks/AUTH-B03-refresh-token]] |
| AUTH-B04 | api | AUTH-B02 | done | [[blocks/AUTH-B04-logout]] |
| AUTH-B05 | api | AUTH-B02 | done | [[blocks/AUTH-B05-rbac-middleware]] |
| AUTH-B06 | web | AUTH-B02 (lock), WEB_BOOTSTRAP-B01 | done | [[blocks/AUTH-B06-pantalla-login]] |
| AUTH-B07 | web | AUTH-B01 (lock), WEB_BOOTSTRAP-B01 | done | [[blocks/AUTH-B07-pantalla-registro]] |
| AUTH-B08 | api | AUTH-B05 | backlog | [[blocks/AUTH-B08-mfa-enrollment]] |
| AUTH-B09 | api | AUTH-B02 | backlog | [[blocks/AUTH-B09-recuperacion-password]] |

> Los bloques `ready` para arrancar hoy son `API_BOOTSTRAP-B01` y `WEB_BOOTSTRAP-B01` (ver
> [[../API_BOOTSTRAP/BLOCKS]] y [[../WEB_BOOTSTRAP/BLOCKS]]) — son los que crean `code/api/` y
> `code/web/`. Ningún bloque de `AUTH` tiene dónde ejecutarse antes de que esos dos existan.

## Nota sobre el estado actual

B01 a B07 ya están `done`. B08 y B09 están en `backlog` sin detallar — se completan cuando les
toque el turno.
