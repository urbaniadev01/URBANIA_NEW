---
tipo: feature
proyecto: shared
feature: PROPIEDADES
actualizado: 2026-07-06
---

# PROPIEDADES — Plan de bloques

> Orden de ejecución, dependencias y gates. Este documento es el índice de bloques del feature — el
> estado real de cada uno vive en su propia tarjeta (ver [[../../_system/01_PRINCIPLES#1. Un dato, un dueño]]);
> `_state/BOARD.md` es el rollup global que agrega esto junto con el resto del vault.

## Orden

```
API_BOOTSTRAP-B01 (done ✅)
    └──> PROPIEDADES-B01 (api, fundacional)
              ├──> PROPIEDADES-B02 (api, catálogos)
              ├──> PROPIEDADES-B03 (api, condominios + torres)
              ├──> PROPIEDADES-B04 (api, unidades)
              └──> PROPIEDADES-B05 (api, coeficientes + tree)

WEB_BOOTSTRAP-B01 (done ✅) ──┐
PROPIEDADES-B02 ──lock──> PROPIEDADES-B06 (web, pantallas catálogos)
PROPIEDADES-B03 ──lock──> PROPIEDADES-B07 (web, pantallas condominios)
PROPIEDADES-B04 ──lock──> PROPIEDADES-B08 (web, pantallas unidades)
PROPIEDADES-B05 ──lock──> PROPIEDADES-B09 (web, pantallas coeficientes)
```

## Tabla

| ID | Proyecto | Depende de | Estado | Tarjeta |
|---|---|---|---|---|
| PROPIEDADES-B01 | api | API_BOOTSTRAP-B01 | ready | [[blocks/PROPIEDADES-B01-migraciones-modelos-seeders]] |
| PROPIEDADES-B02 | api | PROPIEDADES-B01 | backlog | [[blocks/PROPIEDADES-B02-crud-catalogos]] |
| PROPIEDADES-B03 | api | PROPIEDADES-B01 | backlog | [[blocks/PROPIEDADES-B03-crud-condominios-torres]] |
| PROPIEDADES-B04 | api | PROPIEDADES-B01 | backlog | [[blocks/PROPIEDADES-B04-crud-unidades]] |
| PROPIEDADES-B05 | api | PROPIEDADES-B01 | backlog | [[blocks/PROPIEDADES-B05-coeficientes-tree]] |
| PROPIEDADES-B06 | web | PROPIEDADES-B02 (lock), WEB_BOOTSTRAP-B01 | backlog | [[blocks/PROPIEDADES-B06-pantallas-catalogos]] |
| PROPIEDADES-B07 | web | PROPIEDADES-B03 (lock), WEB_BOOTSTRAP-B01 | backlog | [[blocks/PROPIEDADES-B07-pantallas-condominios]] |
| PROPIEDADES-B08 | web | PROPIEDADES-B04 (lock), WEB_BOOTSTRAP-B01 | backlog | [[blocks/PROPIEDADES-B08-pantalla-unidades]] |
| PROPIEDADES-B09 | web | PROPIEDADES-B05 (lock), WEB_BOOTSTRAP-B01 | backlog | [[blocks/PROPIEDADES-B09-pantalla-coeficientes]] |

> Los bloques API (B02-B05) **producen** contrato — al llegar a `done`, se crea un lock en
> `_state/contracts/CONTRACT_LOCKS.md`. Los bloques Web (B06-B09) **consumen** contrato — no pueden
> pasar a `ready` sin el lock vigente de su bloque API correspondiente.
>
> Solo `PROPIEDADES-B01` arranca en `ready` — depende de `API_BOOTSTRAP-B01` (done). El resto de los
> bloques requiere que B01 esté `done` antes de poder pasar a `ready`.
