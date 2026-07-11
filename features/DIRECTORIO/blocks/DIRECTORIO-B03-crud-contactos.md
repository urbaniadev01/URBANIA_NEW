---
tipo: bloque
proyecto: api
feature: DIRECTORIO
id: DIRECTORIO-B03
proyectos: [api]
estado: done
depende_de: [DIRECTORIO-B01]
contrato: produce
verificacion_critica: false
actualizado: 2026-07-11
---

# DIRECTORIO-B03 — CRUD de contactos y autoservicio (`/me/contact`)

## Objetivo

Implementar los endpoints REST de gestión de `contacts`: listado, creación (siempre sin `user_id` —
ver alcance), detalle, edición, eliminación, y el endpoint de autoservicio `/me/contact` para que
cualquier usuario autenticado consulte/edite su propio contacto sin necesitar permisos de gestión.

## Alcance

- **Incluye:**
  - `ContactController` — `index` (`?search=`), `store`, `show`, `update`, `destroy`.
  - `POST /contacts` crea **siempre** un contacto con `user_id = NULL` — es el camino explícito para
    dar de alta personas sin login (propietario ausente, familiar, arrendatario). Un contacto **con**
    login se crea únicamente vía el flujo de registro por invitación de AUTH — este endpoint no
    acepta `user_id` en el body ni permite vincular uno después (ver "No incluye").
  - `MeContactController` — `show` (`GET /me/contact`), `update` (`PATCH /me/contact`): resuelve el
    contacto del usuario autenticado vía `contacts.user_id = auth()->id()`. No requiere ningún
    `role_assignment` — cualquier usuario autenticado tiene acceso a su propio contacto (R-DIR-04).
  - FormRequests para store/update con validación de campos requeridos (`nombre`), formato de
    `email`/`telefono`.
  - `organization_id` seteado automáticamente desde la organización del actor autenticado en `store`
    — nunca viene del body.
  - Scope automático por tenant (R-DIR-01) y por asignación de staff (R-DIR-03:
    `role_assignment.scope_type = condominium`/`tower` limita `index`/`show` a contactos con al
    menos una ocupación dentro de ese scope).
  - Regla de negocio: no se puede eliminar un contacto con `property_occupants` activos → `409
    CONTACT_HAS_OCCUPATIONS` (R-DIR-08).
  - Habeas Data (R-DIR-06): `ContactResource` de listado oculta `email`/`telefono` para actores sin
    permiso de gestión de contactos (ej. un residente consultando el directorio de su propia unidad
    ve nombres, no datos de contacto de sus vecinos ocupantes).
  - `created_by`/`updated_by` seteados en `store`/`update` (R-DIR-10).
  - `GET /contacts/{id}/properties` — unidades asociadas a un contacto (vía `property_occupants`),
    relevante para propietarios con varias unidades.
  - Tests de feature para todos los endpoints — al menos 1 caso negativo por acción de escritura.

- **No incluye (explícitamente fuera de este bloque):**
  - Asignación de un contacto a una unidad (`DIRECTORIO-B04`) — este bloque solo gestiona el
    contacto en sí, no su vínculo con `properties`.
  - Vincular un `user_id` a un contacto existente después de creado (ej. "este propietario ausente
    ahora quiere una cuenta") — es un flujo de invitación nuevo, fuera de alcance del MVP; el
    invariante actual es que un contacto con login solo se crea junto con su `user` en AUTH.
  - CRUD de `occupant-types` (`DIRECTORIO-B02`).
  - UI web (`DIRECTORIO-B06`).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Admin autenticado | `GET /contacts` | 200 + lista paginada (API_CONTRACT §4) de contactos de su organización |
| 2 | Admin, con filtro `?search=Perez` | `GET /contacts?search=Perez` | 200 + contactos cuyo `nombre` contiene "Perez" |
| 3 | Admin autenticado | `POST /contacts` con `{nombre, email, telefono}` (sin `user_id`) | 201 + contacto creado con `organization_id` del admin, `user_id = NULL`, `created_by` seteado |
| 4 | `POST /contacts` con `user_id` en el body | `POST /contacts` | El campo `user_id` se ignora — el contacto se crea igual con `user_id = NULL` (no es un error, simplemente no se usa) |
| 5 | Admin autenticado | `GET /contacts/{id}` (propio) | 200 + detalle completo (incluye `email`/`telefono`) |
| 6 | Admin autenticado | `PATCH /contacts/{id}` | 200 + contacto actualizado, `updated_by` seteado |
| 7 | Contacto con ocupaciones activas | `DELETE /contacts/{id}` | 409 `CONTACT_HAS_OCCUPATIONS` |
| 8 | Contacto sin ocupaciones | `DELETE /contacts/{id}` | 204 — soft-delete exitoso |
| 9 | Usuario no autenticado | Cualquier endpoint | 401 |
| 10 | Usuario de otra organización | `GET /contacts/{id}` (ajeno) | 404 — unificado con 403, anti-enumeración (R-DIR-03) |
| 11 | Staff con `role_assignment.scope_type=condominium` en condominio A | `GET /contacts/{id}` (contacto cuya única ocupación es en condominio B, misma org) | 404 — unificado con 403, fuera de su scope (R-DIR-03) |
| 12 | Usuario con rol `residente` | `GET /contacts` (listado general) | 403 — el listado administrativo no es autoservicio, usa `/me/contact` para su propio contacto |
| 13 | Usuario con rol `residente` | `GET /contacts` (index) mostrando contactos de su propia unidad si tuviera permiso de consulta | La respuesta de `ContactResource` en listado nunca incluye `email`/`telefono` para actores sin permiso de gestión — solo `nombre` (R-DIR-06) |
| 14 | Usuario autenticado con `contact` propio | `GET /me/contact` | 200 + su propio contacto completo (incluye `email`/`telefono` — es su propio dato) |
| 15 | Usuario autenticado | `PATCH /me/contact` con `{telefono: "..."}` | 200 + contacto actualizado, `updated_by` = el mismo usuario |
| 16 | Usuario autenticado sin `contact` asociado (caso imposible dado el invariante de ADR-001, pero defensivo) | `GET /me/contact` | 404 — no debería ocurrir en la práctica (todo `user` activo tiene `contact`), pero la ruta no asume que existe |
| 17 | Admin autenticado, contacto con 2 unidades | `GET /contacts/{id}/properties` | 200 + las 2 unidades donde el contacto tiene una ocupación activa |

## Contrato

Este bloque **produce** el contrato de los endpoints `/contacts` y `/me/contact`. Al completar el
DoD, se congela en `_state/contracts/CONTRACT_LOCKS.md` como `LOCK-DIRECTORIO-02`.

## Definition of Done

- [x] `composer ci` ejecutado — salida completa pegada.
- [x] Verificación funcional real con request/response para **todos** los casos de la tabla de
      criterios (17 casos) — incluidos los negativos (4, 7, 9, 10, 11, 12, 13, 16).
- [x] `_state/contracts/CONTRACT_LOCKS.md` — entrada `LOCK-DIRECTORIO-02` creada.
- [x] `api/API_CONTRACT.md` §3 — agregar `CONTACT_HAS_OCCUPATIONS` (409) a la tabla maestra de
      códigos (también se agregó `CONTACT_NOT_FOUND` (404), introducido por este bloque para el
      patrón de anti-enumeración, mismo criterio que `PROPERTY_NOT_FOUND`/`CONDOMINIUM_NOT_FOUND`).
- [x] `api/endpoints/DIRECTORIO.md` actualizado con el detalle de request/response de los endpoints
      de contactos y `/me/contact`.

## Evidencia

### Implementación

`ContactController` (index/store/show/update/destroy/properties) + `MeContactController`
(show/update, autoservicio sin scope). Mismo patrón de scoping/anti-enumeración que
`PropertyController`/`CondominiumController` (`PROPIEDADES-B03/B04`): `getManagementScope()` deriva
`all`/`condoIds`/`towerIds` de `role_assignments`, `findForTenantWithScope()` unifica 403/404 en 404.
`StoreContactRequest`/`UpdateContactRequest`, `ContactResource` (detalle completo, `$wrap='data'`),
`ContactListResource` (listado, oculta `email`/`telefono` salvo scope `organization` — R-DIR-06,
implementado vía `$request->attributes->set('contacts_show_sensitive', ...)` leído en el resource).
Paginación cursor-based idéntica a `PropertyController::index()`.

**Ajuste sobre el alcance original:** la tarjeta no especificaba si `email` era obligatorio en
`POST /contacts` — la migración real de `contacts` (`AUTH-B01`) tiene `email` como `NOT NULL`
(`telefono` sí es nullable). `StoreContactRequest`/`UpdateContactRequest` lo declaran `required`
para no dejar que la app envíe un `POST` que la BD rechazaría con un error 500 no controlado.

### Tests de feature (18 tests, `tests/Feature/Directorio/ContactTest.php`)

```
$ docker exec -e DB_HOST=postgres -e DB_PORT=5432 -e REDIS_HOST=redis urbania-php php artisan test tests/Feature/Directorio/ContactTest.php
Tests:    18 passed (46 assertions)
Duration: 38.25s
```

### `composer ci` completo (Pint + PHPStan + suite completa)

```
$ docker exec urbania-php ./vendor/bin/pint --test
PASS  229 files

$ docker exec urbania-php ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M
[OK] No errors

$ docker exec -e DB_HOST=postgres -e DB_PORT=5432 -e REDIS_HOST=redis urbania-php php artisan test --parallel
Tests:    262 passed (878 assertions)
Duration: 222.61s
Parallel: 8 processes
```

262 = 244 baseline (post `DIRECTORIO-B02`) + 18 nuevos. Sin regresiones.

### Verificación funcional real (curl, servidor real, `-H "Accept: application/json"` —
ver `_state/RUNBOOK.md#E-008`)

```
# POST /contacts (201, sin user_id)
$ curl -X POST .../contacts -d '{"nombre":"Curl Contact","email":"curlcontact@urbania.test","telefono":"3009998877"}'
STATUS:201 {"data":{"id":"019f5001-...","user_id":null,"nombre":"Curl Contact",...}}

# GET /me/contact (200, autoservicio sin scope)
$ curl .../me/contact
STATUS:200 {"data":{"nombre":"Administrador Demo","email":"admin@urbania.test",...}}

# GET /contacts sin auth (401)
$ curl .../contacts
STATUS:401 {"message":"Unauthenticated."}

# POST /contacts sin email (422, mensaje en español tras el fix de i18n)
$ curl -X POST .../contacts -d '{"nombre":"Sin Email 2"}'
STATUS:422 {"error":{"code":"VALIDATION_ERROR","message":"El email del contacto es obligatorio.",...}}
```

Resto de los 17 criterios (paginación/meta, search, ignorar `user_id`, detalle completo, `updated_by`,
`CONTACT_HAS_OCCUPATIONS`, delete sin uso, anti-enumeración cross-org, staff scope
condominium/tower, `residente` → 403 en listado admin, habeas data en listado condo/tower vs.
organización, `/me/contact` update/404 defensivo, `/contacts/{id}/properties`) verificados vía los
18 tests de feature de arriba (assertions reales contra `assertStatus`/`error.code`/campos del
JSON) — mismo criterio que `DIRECTORIO-B02` para casos que requieren fixtures complejas
(organización + condominio + propiedad + ocupación + role_assignment).

**Hallazgo menor corregido durante la verificación:** el mensaje de `VALIDATION_ERROR` para
`email.required` salía en inglés ("The email field is required.") porque `messages()` no lo
declaraba explícitamente — Laravel cae al mensaje default del validador. Agregado
`email.required` a `messages()` en ambos FormRequests. Reverificado con curl tras el fix.

### Archivos creados

- `src/Directorio/Infrastructure/Http/Controllers/ContactController.php`
- `src/Directorio/Infrastructure/Http/Controllers/MeContactController.php`
- `src/Directorio/Infrastructure/Http/Requests/Contact/StoreContactRequest.php`
- `src/Directorio/Infrastructure/Http/Requests/Contact/UpdateContactRequest.php`
- `src/Directorio/Infrastructure/Http/Resources/ContactResource.php`
- `src/Directorio/Infrastructure/Http/Resources/ContactListResource.php`
- `tests/Feature/Directorio/ContactTest.php`

### Archivos modificados

- `routes/api.php` — grupos `contacts` y `me` registrados.
- `api/API_CONTRACT.md` — `CONTACT_HAS_OCCUPATIONS` y `CONTACT_NOT_FOUND` en la tabla maestra (§3).
- `api/endpoints/DIRECTORIO.md` — 8 endpoints nuevos documentados.
- `_state/contracts/CONTRACT_LOCKS.md` — `LOCK-DIRECTORIO-02` agregado.
- `_state/BOARD.md` — estado del bloque.

## Notas

> El criterio 4 es deliberado: en vez de rechazar un `user_id` inesperado en el body con un error
> (que obligaría a mantener una validación extra sin beneficio real), simplemente se ignora — el
> `FormRequest` no lo declara como campo permitido, así que el mass-assignment nunca lo toca. Más
> simple que inventar un `code` de error para un campo que ningún cliente legítimo debería enviar.
>
> El criterio 16 es defensivo, no un caso de negocio real: el invariante de `ADR-001` garantiza que
> todo `user` activo tiene `contact`. Se documenta para que el endpoint no asuma silenciosamente que
> el contacto existe (evita un error 500 no manejado si el invariante alguna vez se rompe).
