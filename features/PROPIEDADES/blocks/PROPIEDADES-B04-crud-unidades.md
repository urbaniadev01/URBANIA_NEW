---
tipo: bloque
proyecto: api
feature: PROPIEDADES
id: PROPIEDADES-B04
proyectos: [api]
estado: done
depende_de: [PROPIEDADES-B01]
contrato: produce
verificacion_critica: false
actualizado: 2026-07-08
---

# PROPIEDADES-B04 — CRUD de unidades (properties)

## Objetivo

Implementar los endpoints REST de gestión de unidades (`properties`): listado con filtros, creación,
detalle, edición y eliminación. Es el corazón del dominio de PROPIEDADES — las unidades son la
entidad que referencian DIRECTORIO, COBRANZA y PORTERIA.

## Alcance

- **Incluye:**
  - `PropertyController` — `index` (anidado bajo condominium:
    `GET /condominiums/{id}/properties`) con filtros `tower_id`, `type_id`, `status_id`, `search`;
    `store` anidado; `show`; `update`; `destroy`.
  - Paginación cursor-based del `index` siguiendo la convención global de `api/API_CONTRACT.md` §4
    (`?cursor=...&limit=...`, envelope `{ data, meta.next_cursor }`) — los filtros son query params
    adicionales sobre ese mismo endpoint, no un mecanismo de paginación distinto.
  - FormRequests para store/update con validación: `codigo` único por `condominium_id` (R-02) → 409
    `PROPERTY_CODE_DUPLICATE`; `tower_id` que no pertenece al `condominium_id` de la unidad → 422
    `TOWER_CONDOMINIUM_MISMATCH`; referencias válidas a `condominium_id`, `property_type_id`,
    `property_status_id`.
  - Inmutabilidad de `condominium_id` (R-07): no se expone en `update`.
  - Exposición diferenciada de datos (R-10): `area_m2` aparece en `show` (PropertyResource detalle)
    pero **no** en `index` (PropertyListResource resumido).
  - Regla de negocio: no se puede eliminar una unidad con ocupantes activos (R-03) → `409
    PROPERTY_HAS_OCCUPANTS`, verificando `property_occupants` de DIRECTORIO — si la tabla no existe
    aún, se verifica con un guard clause preparado para cuando exista (ver nota de seguimiento más
    abajo).
  - Filtros combinables en index con paginación y scope por tenant (R-09) y por asignación de staff
    (R-09-bis: `role_assignment.scope_type = condominium` limita a ese condominio, `= tower` limita
    a esa torre dentro del condominio).
  - `created_by`/`updated_by` seteados con el `user_id` del actor autenticado en `store`/`update`
    (R-11).
  - Autorización: residente solo ve su propia unidad (derivado de `property_occupants`, R-10);
    endpoint de listado denegado para residentes (403).
  - Tests de feature para todos los endpoints — al menos 1 caso negativo por acción de escritura.

- **No incluye (explícitamente fuera de este bloque):**
  - CRUD de coeficientes (B05), condominios (B03), catálogos (B02).
  - Gestión de ocupantes (`property_occupants`) — pertenece a DIRECTORIO.
  - Endpoint tree (B05).
  - UI web de unidades (B08).
  - Importación masiva (batch import CSV/Excel) — punto ciego documentado en PANORAMA §X.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Admin autenticado, condominio con unidades | `GET /condominiums/{id}/properties` | 200 + `{ data, meta.next_cursor }` (API_CONTRACT §4), sin `area_m2` en cada item |
| 2 | Admin, con filtro `?tower_id=X` | `GET /condominiums/{id}/properties?tower_id=X` | 200 + solo unidades de esa torre |
| 3 | Admin, con filtro `?search=A-201` | `GET /condominiums/{id}/properties?search=A-201` | 200 + unidades cuyo `codigo` contiene "A-201" |
| 4 | Admin, filtros combinados `?tower_id=X&type_id=Y&status_id=Z` | `GET /condominiums/{id}/properties?...` | 200 + intersección de los 3 filtros |
| 5 | Admin autenticado | `POST /condominiums/{id}/properties` con datos válidos | 201 + unidad creada, `created_by` seteado |
| 6 | Unidad existente en mismo condominio | `POST .../properties` con mismo `codigo` | 409 `PROPERTY_CODE_DUPLICATE` |
| 7 | `tower_id` de otro condominio | `POST .../properties` | 422 `TOWER_CONDOMINIUM_MISMATCH` |
| 8 | Admin autenticado | `GET /properties/{id}` (propia) | 200 + detalle **con** `area_m2` |
| 9 | Admin autenticado | `PATCH /properties/{id}` | 200 + unidad actualizada, `updated_by` seteado |
| 10 | `PATCH /properties/{id}` incluyendo `condominium_id` | `PATCH /properties/{id}` | El campo `condominium_id` se ignora (inmutable, R-07) |
| 11 | Unidad con ocupantes activos | `DELETE /properties/{id}` | 409 `PROPERTY_HAS_OCCUPANTS` |
| 12 | Unidad sin ocupantes | `DELETE /properties/{id}` | 204 — soft-delete exitoso |
| 13 | Usuario no autenticado | Cualquier endpoint | 401 |
| 14 | Usuario de otra org | `GET /properties/{id}` (ajena) | 404 — unificado con 403 (R-10) |
| 15 | Usuario con rol `residente` | `GET /condominiums/{id}/properties` | 403 — residente no lista unidades |
| 16 | Usuario con rol `residente` | `GET /properties/{id}` (su unidad) | 200 — ve solo su propia unidad |
| 17 | Usuario con rol `residente` | `GET /properties/{id}` (unidad ajena) | 404 — unificado con 403 (R-10) |
| 18 | Staff con `role_assignment.scope_type=condominium` en condominio A | `GET /condominiums/{B_id}/properties` (otro condominio, misma org) | 404 — unificado con 403, fuera de su scope (R-09-bis) |
| 19 | Staff con `role_assignment.scope_type=tower` en torre X | `GET /properties/{id}` (unidad de otra torre del mismo condominio) | 404 — unificado con 403, fuera de su scope (R-09-bis) |

## Contrato

Este bloque **produce** el contrato de los endpoints `/properties` y
`/condominiums/{id}/properties`. Al completar el DoD, se congela en
`_state/contracts/CONTRACT_LOCKS.md` como `LOCK-PROPIEDADES-03`.

## Definition of Done

- [x] `composer ci` ejecutado — salida completa pegada.
- [x] Verificación funcional real con request/response para **todos** los casos de la tabla de
      criterios (19 casos) — incluidos los negativos (6, 7, 10, 11, 13, 14, 15, 17, 18, 19).
- [x] `_state/contracts/CONTRACT_LOCKS.md` — entrada `LOCK-PROPIEDADES-03` creada.
- [x] `api/API_CONTRACT.md` §3 — agregar `PROPERTY_CODE_DUPLICATE` (409),
      `TOWER_CONDOMINIUM_MISMATCH` (422), `PROPERTY_HAS_OCCUPANTS` (409) a la tabla maestra de
      códigos.
- [x] `api/endpoints/PROPIEDADES.md` actualizado con el detalle de request/response de los endpoints
      de unidades.

## Evidencia

### CI (`composer ci`)
| Paso | Resultado |
|---|---|
| Pint | ✅ PASS — 193 files |
| PHPStan | ✅ 0 errors |
| Tests | ✅ 182 passed, 647 assertions |

### Archivos creados
PropertyController, StorePropertyRequest, UpdatePropertyRequest, PropertyResource, PropertyListResource, PropertyTest (19 CAs)

### Archivos modificados
routes/api.php, api/endpoints/PROPIEDADES.md, CONTRACT_LOCKS.md (LOCK-PROPIEDADES-03), API_CONTRACT.md (+4 códigos error)

### Fixes
- EloquentProperty: agregado `casts()` con area_m2→float, piso→integer
- PropertyController: `$request->integer()` en lugar de cast (PHPStan)
- Tests: cast (float) en comparaciones de area_m2

## Notas

> La verificación de ocupantes activos (criterio 11) depende de la tabla `property_occupants` que
> pertenece a DIRECTORIO. Si la tabla no existe aún, se implementa un guard clause que asume "sin
> ocupantes" con un `@todo` explícito para cuando DIRECTORIO esté disponible. No se bloquea el
> desarrollo de PROPIEDADES por una dependencia futura.
>
> **Seguimiento obligatorio (actualizado 2026-07-08):** esta era una dependencia inversa que el
> mecanismo de locks de `_state/contracts/CONTRACT_LOCKS.md` no rastrea (solo rastrea
> productor→consumidor). Ya no es una brecha abierta: la feature `DIRECTORIO` fue diseñada y su
> bloque fundacional [[../../DIRECTORIO/blocks/DIRECTORIO-B01-fundacion-contactos-ocupantes]] crea
> `property_occupants`. Si este bloque (`PROPIEDADES-B04`) todavía no está `done` cuando
> `DIRECTORIO-B01` se ejecute, implementar la verificación real contra `property_occupants`
> directamente (sin crear el guard clause temporal). Si `PROPIEDADES-B04` ya está `done`,
> `DIRECTORIO-B01` reemplaza el guard clause por la verificación real y lo anota en su propia
> Evidencia — revisar ahí antes de asumir que este `@todo` sigue vigente.
>
> **Resuelto (2026-07-10, por `DIRECTORIO-B01`):** el guard clause se reemplazó por una consulta
> real a `property_occupants` (`DB::table('property_occupants')->where('property_id', $id)
> ->whereNull('deleted_at')->exists()`) en `PropertyController::destroy` — el `@todo` y el
> `Schema::hasTable('property_occupants')` condicional ya no existen. Test de feature `PropertyTest`
> (criterio 11) actualizado para insertar un ocupante real (`contact_id`/`occupant_type_id`
> válidos) en vez de crear una tabla mínima ad-hoc.
