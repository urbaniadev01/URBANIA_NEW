---
tipo: referencia
proyecto: api
feature: DIRECTORIO
actualizado: 2026-07-11
---

# Endpoints: DIRECTORIO

> **Estado de implementación:** `GET/POST/PATCH/DELETE /occupant-types` está implementado
> (DIRECTORIO-B02). `GET/POST/PATCH/DELETE /contacts`, `GET /contacts/{id}/properties` y
> `GET/PATCH /me/contact` están implementados (DIRECTORIO-B03).
> `GET/POST /properties/{id}/occupants` y `PATCH/DELETE /property-occupants/{id}` están
> implementados (DIRECTORIO-B04).

## GET /api/v1/occupant-types

**Bloque que lo produce:** [[../../features/DIRECTORIO/blocks/DIRECTORIO-B02-crud-tipos-ocupante]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-01]]

### Request

Sin body. Header `Authorization: Bearer <access_token>` requerido.

### Response — éxito (`200`)

```json
{
  "data": [
    {
      "id": "uuid",
      "organization_id": null,
      "nombre": "Propietario",
      "descripcion": "Dueño registrado de la unidad",
      "created_by": null,
      "updated_by": null,
      "created_at": "2026-07-11T00:00:00.000000Z",
      "updated_at": "2026-07-11T00:00:00.000000Z"
    }
  ]
}
```

### Comportamiento

- **Tenant isolation (R-DIR-01):** Devuelve registros con `organization_id IS NULL` (catálogo del
  sistema: `Propietario`, `Residente`, `Arrendatario`, `Familiar`, ver `OccupantTypeSeeder`) +
  registros con `organization_id` igual al del usuario autenticado.
- Ordenados alfabéticamente por `nombre`.
- Lectura permitida para todos los roles autenticados (incluyendo `residente`) — necesaria para
  formularios de autoservicio.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado (token faltante, inválido o expirado) |

## POST /api/v1/occupant-types

**Bloque que lo produce:** [[../../features/DIRECTORIO/blocks/DIRECTORIO-B02-crud-tipos-ocupante]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-01]]

### Request

```json
{
  "nombre": "string — obligatorio — nombre del tipo (max 255)",
  "descripcion": "string — opcional — descripción (max 1000)"
}
```

### Response — éxito (`201`)

```json
{
  "data": {
    "id": "uuid",
    "organization_id": "uuid",
    "nombre": "Cuidador",
    "descripcion": "Cuidador contratado",
    "created_by": "uuid",
    "updated_by": null,
    "created_at": "2026-07-11T00:00:00.000000Z",
    "updated_at": "2026-07-11T00:00:00.000000Z"
  }
}
```

### Comportamiento

- `organization_id` se asigna automáticamente del tenant del usuario autenticado.
- `created_by` se asigna con el `user_id` del actor (R-DIR-10).
- El catálogo es a nivel organización — no hay chequeo de scope de condominio/torre (a diferencia
  de contactos/ocupantes en DIRECTORIO-B03/B04): cualquier staff autenticado de la organización
  puede administrar este catálogo.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 409 | `OCCUPANT_TYPE_NAME_DUPLICATE` | Ya existe un tipo con ese nombre en la misma organización (case-insensitive) |
| 422 | `VALIDATION_ERROR` | Campos faltantes o inválidos (nombre vacío, excede 255 caracteres, etc.) |

## GET /api/v1/occupant-types/{occupant_type}

**Bloque que lo produce:** [[../../features/DIRECTORIO/blocks/DIRECTORIO-B02-crud-tipos-ocupante]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-01]]

### Response — éxito (`200`)

```json
{
  "data": {
    "id": "uuid",
    "organization_id": "uuid|null",
    "nombre": "Propietario",
    "descripcion": "Dueño registrado de la unidad",
    "created_by": "uuid|null",
    "updated_by": "uuid|null",
    "created_at": "2026-07-11T00:00:00.000000Z",
    "updated_at": "2026-07-11T00:00:00.000000Z"
  }
}
```

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `OCCUPANT_TYPE_NOT_FOUND` | El tipo no existe o pertenece a otra organización |

## PATCH /api/v1/occupant-types/{occupant_type}

**Bloque que lo produce:** [[../../features/DIRECTORIO/blocks/DIRECTORIO-B02-crud-tipos-ocupante]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-01]]

### Request

```json
{
  "nombre": "string — opcional — nuevo nombre (max 255)",
  "descripcion": "string — opcional — nueva descripción (max 1000)"
}
```

Al menos uno de los campos debe estar presente.

### Response — éxito (`200`)

Mismo formato que `GET /occupant-types/{occupant_type}`, con `updated_by` seteado al `user_id` del
actor (R-DIR-10).

### Comportamiento

- **R-DIR-09 (catálogo del sistema inmutable):** registros con `organization_id IS NULL` no pueden
  modificarse.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 403 | `SYSTEM_CATALOG_READONLY` | Intento de modificar un tipo del catálogo del sistema |
| 404 | `OCCUPANT_TYPE_NOT_FOUND` | El tipo no existe o pertenece a otra organización |
| 409 | `OCCUPANT_TYPE_NAME_DUPLICATE` | Ya existe otro tipo con ese nombre en la misma organización |
| 422 | `VALIDATION_ERROR` | Campos inválidos |

## DELETE /api/v1/occupant-types/{occupant_type}

**Bloque que lo produce:** [[../../features/DIRECTORIO/blocks/DIRECTORIO-B02-crud-tipos-ocupante]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-01]]

### Response — éxito (`204`)

Sin body. Soft-delete (`deleted_at` seteado).

### Comportamiento

- **R-DIR-09 (catálogo del sistema inmutable):** registros con `organization_id IS NULL` no pueden
  eliminarse.
- **Regla de negocio:** no se puede eliminar un tipo referenciado por `property_occupants` activos
  (`deleted_at IS NULL`).

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 403 | `SYSTEM_CATALOG_READONLY` | Intento de eliminar un tipo del catálogo del sistema |
| 404 | `OCCUPANT_TYPE_NOT_FOUND` | El tipo no existe o pertenece a otra organización |
| 409 | `OCCUPANT_TYPE_IN_USE` | El tipo está referenciado por ocupantes activos |

## GET /api/v1/contacts

**Bloque que lo produce:** [[../../features/DIRECTORIO/blocks/DIRECTORIO-B03-crud-contactos]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-02]]

### Request

Query params opcionales: `?search=<texto>` (filtra por `nombre`, `ILIKE`), `?cursor=<uuid>`,
`?limit=<n>` (default 15, máx 50). Header `Authorization: Bearer <access_token>` requerido.

### Response — éxito (`200`)

```json
{
  "data": [
    {
      "id": "uuid",
      "organization_id": "uuid",
      "nombre": "Juan Perez",
      "created_by": "uuid|null",
      "updated_by": "uuid|null",
      "created_at": "2026-07-11T00:00:00.000000Z",
      "updated_at": "2026-07-11T00:00:00.000000Z"
    }
  ],
  "meta": { "next_cursor": "uuid|null" }
}
```

`email`/`telefono` solo aparecen en cada item cuando el actor tiene scope `organization`
(ver Comportamiento).

### Comportamiento

- **R-DIR-01 (tenant isolation):** solo contactos de la organización del usuario.
- **R-DIR-03 (staff scoping):** usuarios con scope `condominium`/`tower` solo ven contactos con al
  menos una ocupación activa (`property_occupants`) dentro de su scope. Usuarios sin ningún scope de
  gestión (`organization`/`condominium`/`tower` — ej. `residente`) reciben `403`: este es un listado
  administrativo, no autoservicio (usar `GET /me/contact` para el propio contacto).
- **R-DIR-06 (habeas data):** `email`/`telefono` solo se incluyen en cada item cuando el actor tiene
  scope `organization` (gestión completa) — actores con scope `condominium`/`tower` solo ven `nombre`.
- Paginación cursor-based (API_CONTRACT §4), ordenado por `id`.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 403 | `FORBIDDEN` | El actor no tiene ningún scope de gestión (`organization`/`condominium`/`tower`) |

## POST /api/v1/contacts

**Bloque que lo produce:** [[../../features/DIRECTORIO/blocks/DIRECTORIO-B03-crud-contactos]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-02]]

### Request

```json
{
  "nombre": "string — obligatorio — nombre del contacto (max 255)",
  "email": "string — obligatorio — email válido (max 255)",
  "telefono": "string — opcional — teléfono (max 50)"
}
```

Un campo `user_id` en el body se ignora silenciosamente — el contacto se crea siempre con
`user_id = NULL`. Un contacto **con** login solo se crea vía el flujo de registro por invitación de
AUTH, nunca por este endpoint.

### Response — éxito (`201`)

```json
{
  "data": {
    "id": "uuid",
    "organization_id": "uuid",
    "user_id": null,
    "nombre": "Ana Gomez",
    "email": "ana@urbania.test",
    "telefono": "3001234567",
    "created_by": "uuid",
    "updated_by": null,
    "created_at": "2026-07-11T00:00:00.000000Z",
    "updated_at": "2026-07-11T00:00:00.000000Z"
  }
}
```

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 422 | `VALIDATION_ERROR` | Campos faltantes o inválidos (`nombre`/`email` requeridos, formato de `email`) |

## GET /api/v1/contacts/{contact}

**Bloque que lo produce:** [[../../features/DIRECTORIO/blocks/DIRECTORIO-B03-crud-contactos]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-02]]

### Response — éxito (`200`)

Detalle completo — a diferencia del listado, **siempre** incluye `email`/`telefono` (habeas data
solo restringe el listado, no el detalle de un contacto puntual al que el actor ya tiene acceso).

```json
{
  "data": {
    "id": "uuid",
    "organization_id": "uuid",
    "user_id": "uuid|null",
    "nombre": "Pedro Diaz",
    "email": "pedro@urbania.test",
    "telefono": "3009876543",
    "created_by": "uuid|null",
    "updated_by": "uuid|null",
    "created_at": "2026-07-11T00:00:00.000000Z",
    "updated_at": "2026-07-11T00:00:00.000000Z"
  }
}
```

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `CONTACT_NOT_FOUND` | El contacto no existe, pertenece a otra organización, o está fuera del scope del actor (anti-enumeración: mismo código para los 3 casos) |

## PATCH /api/v1/contacts/{contact}

**Bloque que lo produce:** [[../../features/DIRECTORIO/blocks/DIRECTORIO-B03-crud-contactos]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-02]]

### Request

```json
{
  "nombre": "string — opcional — nuevo nombre (max 255)",
  "email": "string — opcional — nuevo email válido (max 255)",
  "telefono": "string — opcional, nullable — nuevo teléfono (max 50)"
}
```

### Response — éxito (`200`)

Mismo formato que `GET /contacts/{contact}`, con `updated_by` seteado al actor (R-DIR-10).

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `CONTACT_NOT_FOUND` | El contacto no existe o está fuera del scope del actor |
| 422 | `VALIDATION_ERROR` | Campos inválidos |

## DELETE /api/v1/contacts/{contact}

**Bloque que lo produce:** [[../../features/DIRECTORIO/blocks/DIRECTORIO-B03-crud-contactos]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-02]]

### Response — éxito (`204`)

Sin body. Soft-delete.

### Comportamiento

- **R-DIR-08:** no se puede eliminar un contacto con ocupaciones activas (`property_occupants`,
  `deleted_at IS NULL`).

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `CONTACT_NOT_FOUND` | El contacto no existe o está fuera del scope del actor |
| 409 | `CONTACT_HAS_OCCUPATIONS` | El contacto tiene ocupaciones activas |

## GET /api/v1/contacts/{contact}/properties

**Bloque que lo produce:** [[../../features/DIRECTORIO/blocks/DIRECTORIO-B03-crud-contactos]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-02]]

### Response — éxito (`200`)

Lista de unidades (formato `PropertyListResource`, ver `api/endpoints/PROPIEDADES.md`) donde el
contacto tiene una ocupación activa (`property_occupants.deleted_at IS NULL`), sin duplicados.

```json
{ "data": [ { "id": "uuid", "codigo": "A-101", "condominium_id": "uuid", "...": "..." } ] }
```

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `CONTACT_NOT_FOUND` | El contacto no existe o está fuera del scope del actor |

## GET /api/v1/me/contact

**Bloque que lo produce:** [[../../features/DIRECTORIO/blocks/DIRECTORIO-B03-crud-contactos]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-02]]

### Comportamiento

**R-DIR-04 (autoservicio):** no requiere ningún `role_assignment` — cualquier usuario autenticado
accede a su propio contacto (resuelto vía `contacts.user_id = auth()->id()`), sin chequeo de scope.

### Response — éxito (`200`)

Mismo formato que `GET /contacts/{contact}` (detalle completo, incluye `email`/`telefono`).

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `CONTACT_NOT_FOUND` | El usuario autenticado no tiene un contacto asociado (defensivo — no debería ocurrir dado el invariante de `ADR-001`) |

## PATCH /api/v1/me/contact

**Bloque que lo produce:** [[../../features/DIRECTORIO/blocks/DIRECTORIO-B03-crud-contactos]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-02]]

### Request

Mismo formato que `PATCH /contacts/{contact}`.

### Response — éxito (`200`)

Mismo formato que `GET /me/contact`, con `updated_by` = el mismo usuario.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `CONTACT_NOT_FOUND` | El usuario autenticado no tiene un contacto asociado |
| 422 | `VALIDATION_ERROR` | Campos inválidos |

## GET /api/v1/properties/{property}/occupants

**Bloque que lo produce:** [[../../features/DIRECTORIO/blocks/DIRECTORIO-B04-asignacion-ocupantes]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-03]]

### Response — éxito (`200`)

```json
{
  "data": [
    {
      "id": "uuid",
      "property_id": "uuid",
      "contact_id": "uuid",
      "occupant_type_id": "uuid",
      "es_principal": true,
      "created_by": "uuid|null",
      "updated_by": "uuid|null",
      "created_at": "2026-07-11T00:00:00.000000Z",
      "updated_at": "2026-07-11T00:00:00.000000Z",
      "contact": { "id": "uuid", "nombre": "Juan Perez" },
      "occupant_type": { "id": "uuid", "organization_id": null, "nombre": "Propietario", "...": "..." }
    }
  ]
}
```

`contact` nunca incluye `email`/`telefono` — el detalle completo del contacto vive en
`GET /contacts/{id}` (DIRECTORIO-B03), con sus propios chequeos de permiso.

### Comportamiento

- **Acceso de lectura (más amplio que escritura, CA 13):** permite scope `organization`,
  `condominium`, `tower` **o `unit`** — un residente puede ver quién más ocupa su propia unidad.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `PROPERTY_NOT_FOUND` | La unidad no existe, pertenece a otra organización, o está fuera del scope del actor |

## POST /api/v1/properties/{property}/occupants

**Bloque que lo produce:** [[../../features/DIRECTORIO/blocks/DIRECTORIO-B04-asignacion-ocupantes]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-03]]

### Request

```json
{
  "contact_id": "uuid — obligatorio — debe pertenecer a la organización del actor",
  "occupant_type_id": "uuid — obligatorio — catálogo del sistema o de la organización",
  "es_principal": "boolean — opcional, default false"
}
```

### Response — éxito (`201`)

Mismo formato que un item de `GET /properties/{property}/occupants`.

### Comportamiento

- **Acceso de escritura (más estrecho que lectura, CA 12):** solo scope `organization`,
  `condominium` o `tower` — un residente (solo scope `unit`) recibe `403`.
- **R-DIR-07:** si `es_principal: true`, desmarca automáticamente cualquier otro ocupante
  `es_principal: true` para el mismo `property_id` + `occupant_type_id` (transacción).
- **R-DIR-11:** rechaza duplicados activos de `(contact_id, property_id, occupant_type_id)`.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 403 | `FORBIDDEN` | El actor no tiene ningún scope de gestión |
| 404 | `PROPERTY_NOT_FOUND` | La unidad no existe o está fuera del scope de gestión del actor |
| 409 | `OCCUPANT_ASSIGNMENT_DUPLICATE` | Ya existe una asignación activa con el mismo `contact_id`+`occupant_type_id` en esta unidad |
| 422 | `VALIDATION_ERROR` | Campos faltantes/inválidos, o `contact_id`/`occupant_type_id` que no pertenecen a la organización del actor |

## PATCH /api/v1/property-occupants/{property_occupant}

**Bloque que lo produce:** [[../../features/DIRECTORIO/blocks/DIRECTORIO-B04-asignacion-ocupantes]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-03]]

### Request

```json
{
  "occupant_type_id": "uuid — opcional",
  "es_principal": "boolean — opcional"
}
```

`contact_id`/`property_id` son inmutables — para reasignar a otro contacto o unidad, eliminar y
crear una nueva asignación.

### Response — éxito (`200`)

Mismo formato que `GET`, con `updated_by` seteado (R-DIR-10).

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 403 | `FORBIDDEN` | El actor no tiene ningún scope de gestión |
| 404 | `PROPERTY_NOT_FOUND` | La asignación no existe o su unidad está fuera del scope de gestión del actor |
| 409 | `OCCUPANT_ASSIGNMENT_DUPLICATE` | El cambio de `occupant_type_id` colisiona con otra asignación activa del mismo contacto en la unidad |
| 422 | `VALIDATION_ERROR` | Campos inválidos |

## DELETE /api/v1/property-occupants/{property_occupant}

**Bloque que lo produce:** [[../../features/DIRECTORIO/blocks/DIRECTORIO-B04-asignacion-ocupantes]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-DIRECTORIO-03]]

### Response — éxito (`204`)

Sin body. Soft-delete (des-asignación).

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 403 | `FORBIDDEN` | El actor no tiene ningún scope de gestión |
| 404 | `PROPERTY_NOT_FOUND` | La asignación no existe o su unidad está fuera del scope de gestión del actor |
