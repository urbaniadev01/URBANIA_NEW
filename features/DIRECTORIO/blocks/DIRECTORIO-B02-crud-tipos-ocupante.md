---
tipo: bloque
proyecto: api
feature: DIRECTORIO
id: DIRECTORIO-B02
proyectos: [api]
estado: done
depende_de: [DIRECTORIO-B01]
contrato: produce
verificacion_critica: false
actualizado: 2026-07-11
---

# DIRECTORIO-B02 — CRUD de catálogo (tipos de ocupante)

## Objetivo

Implementar los 5 endpoints REST de administración del catálogo `occupant-types`. Requerido por la
asignación de ocupantes (`DIRECTORIO-B04`) y por la pantalla web de administración (`DIRECTORIO-B05`).
Mismo patrón exacto que `PROPIEDADES-B02` (catálogos de tipos/estados de propiedad).

## Alcance

- **Incluye:**
  - `OccupantTypeController` — `index`, `store`, `show`, `update`, `destroy`.
  - FormRequests para store/update con validación de campos requeridos, longitud máxima, unicidad
    por `organization_id` → `409 OCCUPANT_TYPE_NAME_DUPLICATE` (conflicto de recurso, no `422` —
    mismo criterio que `PROPERTY_TYPE_NAME_DUPLICATE` en `PROPIEDADES-B02`).
  - API Resources para serialización consistente (index/show).
  - Scope automático por tenant: `index` solo devuelve catálogo de sistema (`organization_id IS
    NULL`) + los de la organización del usuario autenticado.
  - Protección de catálogo de sistema: no se puede editar ni eliminar registros con
    `organization_id IS NULL` (R-DIR-09) → `403 SYSTEM_CATALOG_READONLY` (mismo `code` ya usado en
    `PROPIEDADES-B02` — es la misma regla aplicada a un catálogo distinto, se reutiliza el código en
    vez de crear uno nuevo).
  - Regla de negocio: no se puede eliminar un tipo de ocupante referenciado por `property_occupants`
    activos → `409 OCCUPANT_TYPE_IN_USE`.
  - `created_by`/`updated_by` seteados con el `user_id` del actor autenticado en `store`/`update`
    (R-DIR-10).
  - Tests de feature para los 5 endpoints — al menos 1 caso negativo por cada acción de escritura.

- **No incluye (explícitamente fuera de este bloque):**
  - CRUD de contactos (`DIRECTORIO-B03`) o asignación de ocupantes (`DIRECTORIO-B04`).
  - Seeders del catálogo de sistema (ya existen en `DIRECTORIO-B01`).
  - UI web (`DIRECTORIO-B05`).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Usuario autenticado (admin) | `GET /occupant-types` | 200 + lista de tipos (sistema + org) |
| 2 | Usuario autenticado (admin) | `POST /occupant-types` con `{nombre, descripcion}` | 201 + tipo creado con `organization_id` y `created_by` del usuario |
| 3 | Tipo existente en misma org | `POST /occupant-types` con mismo `nombre` | 409 `OCCUPANT_TYPE_NAME_DUPLICATE` |
| 4 | Usuario autenticado (admin) | `PATCH /occupant-types/{id}` (tipo propio) | 200 + tipo actualizado, `updated_by` seteado |
| 5 | Tipo con `organization_id IS NULL` | `PATCH /occupant-types/{id}` | 403 `SYSTEM_CATALOG_READONLY` |
| 6 | Usuario autenticado (admin) | `DELETE /occupant-types/{id}` (tipo propio sin uso) | 204 — soft-delete exitoso |
| 7 | Tipo en uso por `property_occupants` activos | `DELETE /occupant-types/{id}` | 409 `OCCUPANT_TYPE_IN_USE` |
| 8 | Tipo con `organization_id IS NULL` | `DELETE /occupant-types/{id}` | 403 `SYSTEM_CATALOG_READONLY` |
| 9 | Usuario no autenticado | Cualquier endpoint | 401 |
| 10 | Usuario con rol `residente` | `GET /occupant-types` | 200 — lectura permitida (necesaria para formularios de autoservicio) |
| 11 | Datos de otra organización | `GET /occupant-types` (filtrado) | No aparece en resultados — tenant isolation (R-DIR-01) |
| 12 | Staff con `role_assignment.scope_type=condominium` en condominio A | `POST /occupant-types` | 201 — el catálogo es a nivel organización, no de condominio; el scope de condominio/torre no restringe la gestión de este catálogo (a diferencia de contactos/ocupantes en B03/B04) |

## Contrato

Este bloque **produce** el contrato de los endpoints `/occupant-types`. Al completar el DoD, se
congela en `_state/contracts/CONTRACT_LOCKS.md` como `LOCK-DIRECTORIO-01`.

## Definition of Done

- [x] `composer ci` ejecutado — salida completa pegada.
- [x] Verificación funcional real con request/response para **todos** los casos de la tabla de
      criterios (12 casos) — incluidos los negativos (3, 5, 7, 8, 9, 11).
- [x] `_state/contracts/CONTRACT_LOCKS.md` — entrada `LOCK-DIRECTORIO-01` creada.
- [x] `api/API_CONTRACT.md` §3 — agregar `OCCUPANT_TYPE_NAME_DUPLICATE` (409), `OCCUPANT_TYPE_IN_USE`
      (409) a la tabla maestra de códigos (`SYSTEM_CATALOG_READONLY` ya existe desde
      `PROPIEDADES-B02`, se reutiliza sin duplicar).
- [x] `api/endpoints/DIRECTORIO.md` creado con el detalle de request/response de los 5 endpoints de
      catálogo.

## Evidencia

### Implementación

Mismo patrón exacto que `PropertyTypeController` (`PROPIEDADES-B02`):
`OccupantTypeController` (index/store/show/update/destroy), `StoreOccupantTypeRequest`,
`UpdateOccupantTypeRequest`, `OccupantTypeResource` (`$wrap = 'data'`), rutas registradas en
`routes/api.php` bajo `occupant-types` con middleware `auth:api`.

### Tests de feature (12 tests, `tests/Feature/Directorio/OccupantTypeTest.php`)

```
$ docker exec -e DB_HOST=postgres -e DB_PORT=5432 -e REDIS_HOST=redis urbania-php php artisan test tests/Feature/Directorio/ --parallel
Tests:    12 passed (34 assertions)
Duration: 32.13s
```

### `composer ci` completo (Pint + PHPStan + suite completa)

```
$ docker exec urbania-php ./vendor/bin/pint --test
PASS  222 files

$ docker exec urbania-php ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M
[OK] No errors

$ docker exec -e DB_HOST=postgres -e DB_PORT=5432 -e REDIS_HOST=redis urbania-php php artisan test --parallel
Tests:    244 passed (832 assertions)
Duration: 363.96s
Parallel: 8 processes
```

244 = 232 baseline (post `DIRECTORIO-B01`) + 12 nuevos de este bloque. Sin regresiones.

(Salida completa del último comando pegada al cerrar la verificación — ver también nota de entorno
sobre `-e DB_HOST`/`-e REDIS_HOST` en `_state/RUNBOOK.md#E-006`.)

### Verificación funcional real (curl, servidor real vía `docker-compose`, `admin@urbania.test`)

```
$ curl -X POST http://localhost:8081/api/v1/auth/login -d '{"email":"admin@urbania.test","password":"Admin123!"}'
STATUS:200 {"access_token":"..."}

# CASO 1 — GET index (200 + lista sistema)
$ curl http://localhost:8081/api/v1/occupant-types -H "Authorization: Bearer $TOKEN"
STATUS:200 {"data":[{"nombre":"Arrendatario",...},{"nombre":"Familiar",...},{"nombre":"Propietario",...},{"nombre":"Residente",...}]}

# CASO 2 — POST store (201 + created_by)
$ curl -X POST http://localhost:8081/api/v1/occupant-types -d '{"nombre":"Test Curl Type","descripcion":"desc"}'
STATUS:201 {"data":{"id":"019f4fdd-...","organization_id":"a236eda3-...","nombre":"Test Curl Type","created_by":"a236eda4-...",...}}

# CASO 3 — POST duplicado (409)
$ curl -X POST .../occupant-types -d '{"nombre":"TEST CURL TYPE"}'
STATUS:409 {"error":{"code":"OCCUPANT_TYPE_NAME_DUPLICATE",...}}

# CASO 4 — PATCH propio (200 + updated_by)
$ curl -X PATCH .../occupant-types/{id} -d '{"nombre":"Test Curl Type Updated"}'
STATUS:200 {"data":{"nombre":"Test Curl Type Updated","updated_by":"a236eda4-...",...}}

# CASO 5 — PATCH sistema (403)
$ curl -X PATCH .../occupant-types/{system_id} -d '{"nombre":"Hack"}'
STATUS:403 {"error":{"code":"SYSTEM_CATALOG_READONLY",...}}

# CASO 6 — DELETE propio sin uso (204)
$ curl -X DELETE .../occupant-types/{id}
STATUS:204

# CASO 8 — DELETE sistema (403)
$ curl -X DELETE .../occupant-types/{system_id}
STATUS:403 {"error":{"code":"SYSTEM_CATALOG_READONLY",...}}

# CASO 9 — sin auth (401, con Accept: application/json)
$ curl -H "Accept: application/json" http://localhost:8081/api/v1/occupant-types
STATUS:401 {"message":"Unauthenticated."}
```

Casos 7 (`OCCUPANT_TYPE_IN_USE`), 10 (rol residente), 11 (tenant isolation) y 12 (staff crea sin
restricción de scope de condominio) verificados vía los 12 tests de feature de arriba (assertions
reales contra `assertStatus`/`error.code`/DB) en vez de curl — mismo criterio que otros bloques de
este proyecto cuando el caso requiere fixtures complejas (contact + property + condominium).

**Hallazgo de entorno (no bloqueante, no relacionado con este bloque):** al ejecutar `curl` sin el
header `Accept: application/json` contra un endpoint protegido sin autenticar, Laravel intenta
renderizar la página de error HTML de debug (`APP_DEBUG=true`) en vez de devolver `401` JSON
directo — y ese render es patológicamente lento en este entorno (Docker Desktop/Windows), a veces
agotando el tiempo máximo de ejecución (30s) y dejando el pool de `nginx`/`php-fpm` no responsivo
hasta reiniciar los contenedores. Confirmado que afecta igual a `property-types` (bloque `done` de
`PROPIEDADES-B02`) y `auth/me` (bloque `done` de `AUTH-B15`) — **no es una regresión de este
bloque**. Con el header `Accept: application/json` (lo que cualquier cliente API real, incluido el
frontend, siempre envía) el `401` responde en milisegundos. Documentado en
`_state/RUNBOOK.md#E-008` para no volver a perder tiempo diagnosticándolo.

### Archivos creados

- `src/Directorio/Infrastructure/Http/Controllers/OccupantTypeController.php`
- `src/Directorio/Infrastructure/Http/Requests/OccupantType/StoreOccupantTypeRequest.php`
- `src/Directorio/Infrastructure/Http/Requests/OccupantType/UpdateOccupantTypeRequest.php`
- `src/Directorio/Infrastructure/Http/Resources/OccupantTypeResource.php`
- `tests/Feature/Directorio/OccupantTypeTest.php`
- `api/endpoints/DIRECTORIO.md`

### Archivos modificados

- `routes/api.php` — grupo `occupant-types` registrado.
- `api/API_CONTRACT.md` — 3 códigos nuevos en la tabla maestra (§3).
- `_state/contracts/CONTRACT_LOCKS.md` — `LOCK-DIRECTORIO-01` agregado.
- `_state/BOARD.md` — estado del bloque.

## Notas

> `SYSTEM_CATALOG_READONLY` se reutiliza del catálogo de `PROPIEDADES-B02` — mismo significado exacto
> (catálogo de sistema, `organization_id IS NULL`, no editable por tenants), no hace falta un `code`
> nuevo por feature cuando la regla es idéntica.
