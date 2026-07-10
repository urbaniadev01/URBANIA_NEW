---
tipo: bloque
proyecto: api
feature: DIRECTORIO
id: DIRECTORIO-B03
proyectos: [api]
estado: backlog
depende_de: [DIRECTORIO-B01]
contrato: produce
verificacion_critica: false
actualizado: 2026-07-08
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

- [ ] `composer ci` ejecutado — salida completa pegada.
- [ ] Verificación funcional real con request/response para **todos** los casos de la tabla de
      criterios (17 casos) — incluidos los negativos (4, 7, 9, 10, 11, 12, 13, 16).
- [ ] `_state/contracts/CONTRACT_LOCKS.md` — entrada `LOCK-DIRECTORIO-02` creada.
- [ ] `api/API_CONTRACT.md` §3 — agregar `CONTACT_HAS_OCCUPATIONS` (409) a la tabla maestra de
      códigos.
- [ ] `api/endpoints/DIRECTORIO.md` actualizado con el detalle de request/response de los endpoints
      de contactos y `/me/contact`.

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> El criterio 4 es deliberado: en vez de rechazar un `user_id` inesperado en el body con un error
> (que obligaría a mantener una validación extra sin beneficio real), simplemente se ignora — el
> `FormRequest` no lo declara como campo permitido, así que el mass-assignment nunca lo toca. Más
> simple que inventar un `code` de error para un campo que ningún cliente legítimo debería enviar.
>
> El criterio 16 es defensivo, no un caso de negocio real: el invariante de `ADR-001` garantiza que
> todo `user` activo tiene `contact`. Se documenta para que el endpoint no asuma silenciosamente que
> el contacto existe (evita un error 500 no manejado si el invariante alguna vez se rompe).
