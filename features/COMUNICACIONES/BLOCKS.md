---
tipo: feature
proyecto: shared
feature: COMUNICACIONES
actualizado: 2026-07-11
---

# COMUNICACIONES — Plan de bloques

> Orden de ejecución, dependencias y gates. Este documento es el índice de bloques del feature — el
> estado real de cada uno vive en su propia tarjeta (ver [[../../_system/01_PRINCIPLES#1. Un dato, un dueño]]);
> `_state/BOARD.md` es el rollup global que agrega esto junto con el resto del vault.

## Orden

```
AUTH-B01 (done ✅) ──┐
PROPIEDADES-B03 (done ✅) ──┴──> COMUNICACIONES-B01 (api, migración + modelo + CRUD)
                                          │
                                          └──lock──> COMUNICACIONES-B02 (web, pantalla anuncios)

WEB_BOOTSTRAP-B01 (done ✅) ──> COMUNICACIONES-B02
```

Feature chica (1 tabla, 4 endpoints, 1 pantalla) — no se corta en más bloques de los necesarios:
un solo bloque API cubre migración+modelo+CRUD completo (mismo criterio que `AUTH-B01`), y un solo
bloque Web cubre la única pantalla.

## Tabla

| ID | Proyecto | Depende de | Estado | Tarjeta |
|---|---|---|---|---|
| COMUNICACIONES-B01 | api | AUTH-B01, PROPIEDADES-B03 | ready | [[blocks/COMUNICACIONES-B01-migracion-modelo-crud-anuncios]] |
| COMUNICACIONES-B02 | web | COMUNICACIONES-B01 (lock), WEB_BOOTSTRAP-B01 | backlog | [[blocks/COMUNICACIONES-B02-pantalla-anuncios]] |

> `COMUNICACIONES-B01` arranca en `ready` — depende de `AUTH-B01` (done, para `users`/RBAC) y
> `PROPIEDADES-B03` (done, para `condominiums`), ambos ya cumplidos. No depende de `DIRECTORIO` ni de
> `COBRANZA` (esta feature es independiente de ambas, ver PANORAMA §3).
>
> `COMUNICACIONES-B01` **produce** contrato — al llegar a `done`, se crea el lock
> `LOCK-COMUNICACIONES-01` en `_state/contracts/CONTRACT_LOCKS.md`. `COMUNICACIONES-B02` **consume**
> ese contrato — no puede pasar a `ready` sin el lock vigente.
>
> `COMUNICACIONES-B02` lleva `verificacion_critica: true` **no** por riesgo técnico (es un CRUD
> estándar sobre auth ya establecida) sino porque es el último bloque de esta feature — mismo
> criterio aplicado a `COBRANZA-B11` (ver nota en `_state/BOARD.md` y
> `_system/05_DEFINITION_OF_DONE.md` §6, "Features nuevos completos (su último bloque)").
