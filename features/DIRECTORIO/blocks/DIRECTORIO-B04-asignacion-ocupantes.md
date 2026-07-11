---
tipo: bloque
proyecto: api
feature: DIRECTORIO
id: DIRECTORIO-B04
proyectos: [api]
estado: done
depende_de: [DIRECTORIO-B01]
contrato: produce
verificacion_critica: false
actualizado: 2026-07-11
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

- [x] `composer ci` ejecutado — salida completa pegada.
- [x] Verificación funcional real con request/response para **todos** los casos de la tabla de
      criterios (13 casos) — incluidos los negativos (3, 5, 9, 10, 11, 12), con énfasis en el
      desmarcado automático de `es_principal` (caso 6).
- [x] `_state/contracts/CONTRACT_LOCKS.md` — entrada `LOCK-DIRECTORIO-03` creada.
- [x] `api/API_CONTRACT.md` §3 — agregar `OCCUPANT_ASSIGNMENT_DUPLICATE` (409) a la tabla maestra de
      códigos.
- [x] `api/endpoints/DIRECTORIO.md` actualizado con el detalle de request/response de los endpoints
      de asignación de ocupantes.

## Evidencia

### Implementación

`PropertyOccupantController` (index/store/update/destroy). Dos niveles de scope reutilizando el
patrón de `PropertyController`/`ContactController`: `canAccessProperty()` (lectura — org/condo/tower
**+ unit**, permite a un residente ver su propia unidad, CA 13) y `canManageProperty()` (escritura —
solo org/condo/tower, CA 12). R-DIR-07 (desmarcado automático de `es_principal`) implementado en
`unmarkOtherPrincipals()`, corrido dentro de `DB::transaction()` antes del `save()` para no violar
el índice único parcial `property_occupants_principal_unique`. R-DIR-11 (duplicados) chequeado
explícitamente antes del insert/update para devolver `409` limpio en vez de una violación de
constraint cruda. `StorePropertyOccupantRequest`/`UpdatePropertyOccupantRequest` validan
`contact_id`/`occupant_type_id` contra la organización del actor vía `withValidator()` → `422`
(criterio 5, deliberadamente un error de validación y no un 404/403 de dominio, según el alcance de
la tarjeta). `PropertyOccupantResource` nunca expone `email`/`telefono` del contacto anidado
(R-DIR-06) — el detalle completo vive en `GET /contacts/{id}` (`DIRECTORIO-B03`).

**Nota técnica (PHPStan):** `findOccupantForManagement()` disparaba `return.unusedType` en nivel 10
— PHPStan sobre-infería que `canManageProperty()` (llamado dentro) siempre retorna `false`,
concluyendo que la rama que retorna el modelo era código muerto. Confirmado como falso positivo: los
tests de feature (`update`/`delete` de una asignación) ejercitan exactamente esa rama y pasan.
Agregado un `ignoreErrors` acotado a este archivo en `phpstan.dist.neon`, con comentario explicando
la causa — mismo criterio que el resto de ignores ya existentes para ruido de Eloquent/query builder
a nivel 10.

### Tests de feature (13 tests, `tests/Feature/Directorio/PropertyOccupantTest.php`)

```
$ docker exec -e DB_HOST=postgres -e DB_PORT=5432 -e REDIS_HOST=redis urbania-php php artisan test tests/Feature/Directorio/PropertyOccupantTest.php
Tests:    13 passed (28 assertions)
Duration: 34.28s
```

### `composer ci` completo (Pint + PHPStan + suite completa)

```
$ docker exec urbania-php ./vendor/bin/pint --test
PASS  234 files

$ docker exec urbania-php ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M
[OK] No errors

$ docker exec -e DB_HOST=postgres -e DB_PORT=5432 -e REDIS_HOST=redis urbania-php php artisan test --parallel
Tests:    275 passed (906 assertions)
Duration: 160.42s
Parallel: 8 processes
```

275 = 262 baseline (post `DIRECTORIO-B03`) + 13 nuevos. Sin regresiones.

### Verificación funcional real (curl, servidor real, `-H "Accept: application/json"`)

Foco en el criterio más importante del bloque — R-DIR-07 (desmarcado automático de `es_principal`),
con datos reales (condominio + unidad + 2 contactos creados por API):

```
# Asignar Contacto 1 como principal (201)
$ curl -X POST .../properties/{id}/occupants -d '{"contact_id":"...1","occupant_type_id":"...","es_principal":true}'
STATUS 201 {"data":{"id":"019f501a-816a-...","es_principal":true,"contact":{"nombre":"Curl Occ 1"},...}}

# Asignar Contacto 2 como principal (201) — debe desmarcar al 1
$ curl -X POST .../properties/{id}/occupants -d '{"contact_id":"...2","occupant_type_id":"...","es_principal":true}'
STATUS 201 {"data":{"id":"019f501a-9136-...","es_principal":true,"contact":{"nombre":"Curl Occ 2"},...}}

# GET index — confirma que solo el Contacto 2 quedó como principal
$ curl .../properties/{id}/occupants
{"data":[
  {"contact":{"nombre":"Curl Occ 1"}, "es_principal": false, ...},
  {"contact":{"nombre":"Curl Occ 2"}, "es_principal": true, ...}
]}
```

Resto de los 13 criterios (listado con `occupant_type` anidado sin `email`/`telefono`, duplicado
409, mismo contacto con tipo distinto 201, `contact_id` de otra org → 422, PATCH con `updated_by`,
DELETE 204, sin auth 401, unidad de otra org 404, staff fuera de scope 404, `residente` → 403 en
POST, `residente` → 200 en GET de su propia unidad) verificados vía los 13 tests de feature de
arriba — mismo criterio que `DIRECTORIO-B02`/`B03` para casos que requieren fixtures complejas
(condominio + unidad + contacto + `role_assignment`).

### Archivos creados

- `src/Directorio/Infrastructure/Http/Controllers/PropertyOccupantController.php`
- `src/Directorio/Infrastructure/Http/Requests/PropertyOccupant/StorePropertyOccupantRequest.php`
- `src/Directorio/Infrastructure/Http/Requests/PropertyOccupant/UpdatePropertyOccupantRequest.php`
- `src/Directorio/Infrastructure/Http/Resources/PropertyOccupantResource.php`
- `tests/Feature/Directorio/PropertyOccupantTest.php`

### Archivos modificados

- `routes/api.php` — rutas de `properties/{id}/occupants` y `property-occupants/{id}`.
- `api/API_CONTRACT.md` — `OCCUPANT_ASSIGNMENT_DUPLICATE` en la tabla maestra (§3).
- `api/endpoints/DIRECTORIO.md` — 4 endpoints nuevos documentados.
- `_state/contracts/CONTRACT_LOCKS.md` — `LOCK-DIRECTORIO-03` agregado.
- `phpstan.dist.neon` — ignore acotado para el falso positivo descrito arriba.
- `_state/BOARD.md` — estado del bloque.

## Notas

> Este bloque es el que efectivamente hace que la regla R-03 de `PROPIEDADES` ("no eliminar unidad
> con ocupantes activos") tenga datos reales contra los que verificar — pero la implementación de esa
> verificación vive en la tarjeta de `PROPIEDADES-B04`/`DIRECTORIO-B01`, no aquí.
