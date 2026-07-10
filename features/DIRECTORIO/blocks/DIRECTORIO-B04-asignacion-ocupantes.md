---
tipo: bloque
proyecto: api
feature: DIRECTORIO
id: DIRECTORIO-B04
proyectos: [api]
estado: backlog
depende_de: [DIRECTORIO-B01]
contrato: produce
verificacion_critica: false
actualizado: 2026-07-08
---

# DIRECTORIO-B04 — Asignación de ocupantes (persona↔unidad)

## Objetivo

Implementar los endpoints REST que vinculan un `contact` a una `property` con un `occupant_type`
(`property_occupants`). Es el vínculo que `PROPIEDADES-B04` (regla "no eliminar unidad con ocupantes
activos") y, más adelante, `COBRANZA`/`PORTERIA` necesitan para saber quién ocupa cada unidad.

## Alcance

- **Incluye:**
  - `PropertyOccupantController` — `index` (`GET /properties/{id}/occupants`), `store` (`POST
    /properties/{id}/occupants`), `update` (`PATCH /property-occupants/{id}`), `destroy` (`DELETE
    /property-occupants/{id}`).
  - FormRequests con validación: `contact_id` y `property_id` deben pertenecer a la misma
    organización que el actor autenticado; `occupant_type_id` debe existir (catálogo sistema o de la
    organización).
  - Validación de unicidad (R-DIR-11): mismo `(contact_id, property_id, occupant_type_id)` activo →
    `409 OCCUPANT_ASSIGNMENT_DUPLICATE`.
  - Gestión de `es_principal` (R-DIR-07): al marcar `es_principal: true` en `store`/`update`, se
    desmarca automáticamente cualquier otro registro `es_principal: true` para el mismo `property_id`
    + `occupant_type_id` (dentro de una transacción — mismo espíritu que el cierre automático de
    coeficientes en `PROPIEDADES-B05`).
  - Scope por tenant (R-DIR-01) y por asignación de staff (R-DIR-03): un `condominium`/`tower`-scoped
    solo asigna/edita ocupantes dentro de su scope.
  - Anti-enumeración (R-DIR-03/R-DIR-06): 403/404 unificados para accesos fuera de scope.
  - `created_by`/`updated_by` seteados en `store`/`update` (R-DIR-10).
  - Tests de feature para todos los endpoints — al menos 1 caso negativo por acción de escritura.

- **No incluye (explícitamente fuera de este bloque):**
  - CRUD de `contacts` (`DIRECTORIO-B03`) o `occupant-types` (`DIRECTORIO-B02`) — este bloque solo
    gestiona el vínculo entre entidades que ya existen.
  - Temporalidad de ocupación (`vigente_desde`/`vigente_hasta`) — deuda técnica explícita, ver
    PANORAMA R-DIR-05.
  - Actualizar el guard clause de `PROPIEDADES-B04` — eso ya lo resuelve `DIRECTORIO-B01` (criterios
    16-17 de esa tarjeta), no este bloque.
  - UI web (`DIRECTORIO-B07`).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Admin, unidad con ocupantes | `GET /properties/{id}/occupants` | 200 + lista de ocupantes con su `occupant_type` |
| 2 | Admin autenticado, `contact_id`/`property_id` válidos en su org | `POST /properties/{id}/occupants` con `{contact_id, occupant_type_id, es_principal: false}` | 201 + ocupante asignado, `created_by` seteado |
| 3 | Asignación existente para mismo `(contact_id, property_id, occupant_type_id)` | `POST .../occupants` con los mismos 3 valores | 409 `OCCUPANT_ASSIGNMENT_DUPLICATE` |
| 4 | Mismo `contact_id`/`property_id`, `occupant_type_id` distinto | `POST .../occupants` | 201 — un contacto puede tener varios tipos en la misma unidad (R-DIR-11) |
| 5 | `contact_id` de otra organización | `POST /properties/{id}/occupants` | 422 — la referencia no pertenece a la organización del actor |
| 6 | Unidad con un ocupante `es_principal: true` de tipo `propietario` | `POST .../occupants` con otro contacto, mismo `occupant_type_id`, `es_principal: true` | 201 + el ocupante anterior queda con `es_principal: false` automáticamente (R-DIR-07) |
| 7 | Admin autenticado | `PATCH /property-occupants/{id}` cambiando `occupant_type_id` | 200 + asignación actualizada, `updated_by` seteado |
| 8 | Admin autenticado | `DELETE /property-occupants/{id}` | 204 — soft-delete exitoso (des-asignación) |
| 9 | Usuario no autenticado | Cualquier endpoint | 401 |
| 10 | Usuario de otra org | `GET /properties/{id}/occupants` (unidad ajena) | 404 — unificado con 403 (R-DIR-03) |
| 11 | Staff con `role_assignment.scope_type=condominium` en condominio A | `POST /properties/{id}/occupants` (unidad de condominio B, misma org) | 404 — unificado con 403, fuera de su scope (R-DIR-03) |
| 12 | Usuario con rol `residente` | `POST /properties/{id}/occupants` | 403 — solo admin/staff con permiso gestiona asignaciones |
| 13 | Usuario con rol `residente` | `GET /properties/{id}/occupants` (su propia unidad) | 200 — puede ver quién más ocupa su unidad (nombres, sin `email`/`telefono` por R-DIR-06) |

## Contrato

Este bloque **produce** el contrato de los endpoints `/properties/{id}/occupants` y
`/property-occupants/{id}`. Al completar el DoD, se congela en
`_state/contracts/CONTRACT_LOCKS.md` como `LOCK-DIRECTORIO-03`.

## Definition of Done

- [ ] `composer ci` ejecutado — salida completa pegada.
- [ ] Verificación funcional real con request/response para **todos** los casos de la tabla de
      criterios (13 casos) — incluidos los negativos (3, 5, 9, 10, 11, 12), con énfasis en el
      desmarcado automático de `es_principal` (caso 6).
- [ ] `_state/contracts/CONTRACT_LOCKS.md` — entrada `LOCK-DIRECTORIO-03` creada.
- [ ] `api/API_CONTRACT.md` §3 — agregar `OCCUPANT_ASSIGNMENT_DUPLICATE` (409) a la tabla maestra de
      códigos.
- [ ] `api/endpoints/DIRECTORIO.md` actualizado con el detalle de request/response de los endpoints
      de asignación de ocupantes.

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> Este bloque es el que efectivamente hace que la regla R-03 de `PROPIEDADES` ("no eliminar unidad
> con ocupantes activos") tenga datos reales contra los que verificar — pero la implementación de esa
> verificación vive en la tarjeta de `PROPIEDADES-B04`/`DIRECTORIO-B01`, no aquí.
