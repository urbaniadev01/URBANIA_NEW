---
tipo: feature
proyecto: shared
feature: DIRECTORIO
actualizado: 2026-07-08
---

# DIRECTORIO — Plan de bloques

> Orden de ejecución, dependencias y gates. Este documento es el índice de bloques del feature — el
> estado real de cada uno vive en su propia tarjeta (ver [[../../_system/01_PRINCIPLES#1. Un dato, un dueño]]);
> `_state/BOARD.md` es el rollup global que agrega esto junto con el resto del vault.

## Orden

```
AUTH-B01 (done ✅, contiene el bug de contacts.user_id a corregir) ──┐
PROPIEDADES-B01 (done ✅) ───────────────────────────────────────────┴──> DIRECTORIO-B01 (api, fundacional)
                                                                              ├──> DIRECTORIO-B02 (api, catálogo tipos de ocupante)
                                                                              ├──> DIRECTORIO-B03 (api, CRUD contactos + /me/contact)
                                                                              └──> DIRECTORIO-B04 (api, asignación ocupantes)

WEB_BOOTSTRAP-B01 (done ✅) ──┐
DIRECTORIO-B02 ──lock──> DIRECTORIO-B05 (web, pantalla catálogo tipos de ocupante)
DIRECTORIO-B03 ──lock──> DIRECTORIO-B06 (web, pantalla directorio de contactos)
DIRECTORIO-B04 ──lock──> DIRECTORIO-B07 (web, pantalla asignación de ocupantes + "mi perfil")
```

## Tabla

| ID | Proyecto | Depende de | Estado | Tarjeta |
|---|---|---|---|---|
| DIRECTORIO-B01 | api | AUTH-B01, PROPIEDADES-B01 | ready | [[blocks/DIRECTORIO-B01-fundacion-contactos-ocupantes]] |
| DIRECTORIO-B02 | api | DIRECTORIO-B01 | backlog | [[blocks/DIRECTORIO-B02-crud-tipos-ocupante]] |
| DIRECTORIO-B03 | api | DIRECTORIO-B01 | backlog | [[blocks/DIRECTORIO-B03-crud-contactos]] |
| DIRECTORIO-B04 | api | DIRECTORIO-B01 | backlog | [[blocks/DIRECTORIO-B04-asignacion-ocupantes]] |
| DIRECTORIO-B05 | web | DIRECTORIO-B02 (lock), WEB_BOOTSTRAP-B01 | backlog | [[blocks/DIRECTORIO-B05-pantalla-tipos-ocupante]] |
| DIRECTORIO-B06 | web | DIRECTORIO-B03 (lock), WEB_BOOTSTRAP-B01 | backlog | [[blocks/DIRECTORIO-B06-pantalla-directorio-contactos]] |
| DIRECTORIO-B07 | web | DIRECTORIO-B04 (lock), WEB_BOOTSTRAP-B01 | backlog | [[blocks/DIRECTORIO-B07-pantalla-asignacion-ocupantes]] |

> Los bloques API (B02-B04) **producen** contrato — al llegar a `done`, se crea un lock en
> `_state/contracts/CONTRACT_LOCKS.md`. Los bloques Web (B05-B07) **consumen** contrato — no pueden
> pasar a `ready` sin el lock vigente de su bloque API correspondiente.
>
> Solo `DIRECTORIO-B01` arranca en `ready` — depende de `AUTH-B01` (done) y `PROPIEDADES-B01` (done),
> no de que el resto de bloques de `PROPIEDADES` estén terminados. El resto de bloques de esta
> feature requiere que B01 esté `done` antes de poder pasar a `ready`.
>
> `DIRECTORIO-B01` tiene `verificacion_critica: true` — corrige una tabla ya `SHIPPED` (`contacts`,
> de `AUTH-B01`) con una migración que altera datos existentes (backfill de `organization_id`), no
> solo esquema nuevo. Ver `_system/05_DEFINITION_OF_DONE.md` §6.
