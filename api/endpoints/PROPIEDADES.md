---
tipo: referencia
proyecto: api
feature: PROPIEDADES
actualizado: 2026-07-08
---

# Endpoints: PROPIEDADES

> **Estado de implementación:** `GET/POST/PATCH/DELETE /property-types` y
> `GET/POST/PATCH/DELETE /property-statuses` están implementados (PROPIEDADES-B02).
> `GET/POST/PATCH/DELETE /condominiums` y `GET/POST/PATCH/DELETE /towers` están
> implementados (PROPIEDADES-B03).
> `GET/POST /condominiums/{id}/properties` y `GET/PATCH/DELETE /properties/{id}` están
> implementados (PROPIEDADES-B04).
> `GET /properties/{id}/coefficients`, `PATCH /condominiums/{id}/coefficients` y
> `GET /condominiums/{id}/tree` están implementados (PROPIEDADES-B05).

## GET /api/v1/property-types

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B02-crud-catalogos]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-01]]

### Request

Sin body. Header `Authorization: Bearer <access_token>` requerido.

### Response — éxito (`200`)

```json
{
  "data": [
    {
      "id": "uuid",
      "organization_id": null,
      "nombre": "Apartamento",
      "descripcion": "Unidad de vivienda en edificio",
      "created_by": null,
      "updated_by": null,
      "created_at": "2026-07-08T00:00:00.000000Z",
      "updated_at": "2026-07-08T00:00:00.000000Z"
    }
  ]
}
```

### Comportamiento

- **Tenant isolation (R-09):** Devuelve registros con `organization_id IS NULL` (catálogos del sistema) +
  registros con `organization_id` igual al del usuario autenticado.
- Ordenados alfabéticamente por `nombre`.
- Lectura permitida para todos los roles autenticados (incluyendo `residente`).

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado (token faltante, inválido o expirado) |

## POST /api/v1/property-types

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B02-crud-catalogos]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-01]]

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
    "nombre": "Oficina",
    "descripcion": "Unidad de oficina",
    "created_by": "uuid",
    "updated_by": null,
    "created_at": "2026-07-08T00:00:00.000000Z",
    "updated_at": "2026-07-08T00:00:00.000000Z"
  }
}
```

### Comportamiento

- `organization_id` se asigna automáticamente del tenant del usuario autenticado.
- `created_by` se asigna con el `user_id` del actor (R-11).

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 409 | `PROPERTY_TYPE_NAME_DUPLICATE` | Ya existe un tipo con ese nombre en la misma organización (case-insensitive) |
| 422 | `VALIDATION_ERROR` | Campos faltantes o inválidos (nombre vacío, excede 255 caracteres, etc.) |

## GET /api/v1/property-types/{property_type}

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B02-crud-catalogos]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-01]]

### Response — éxito (`200`)

```json
{
  "data": {
    "id": "uuid",
    "organization_id": "uuid|null",
    "nombre": "Apartamento",
    "descripcion": "Unidad de vivienda en edificio",
    "created_by": "uuid|null",
    "updated_by": "uuid|null",
    "created_at": "2026-07-08T00:00:00.000000Z",
    "updated_at": "2026-07-08T00:00:00.000000Z"
  }
}
```

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `PROPERTY_TYPE_NOT_FOUND` | El tipo no existe o pertenece a otra organización |

## PATCH /api/v1/property-types/{property_type}

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B02-crud-catalogos]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-01]]

### Request

```json
{
  "nombre": "string — opcional — nuevo nombre (max 255)",
  "descripcion": "string — opcional — nueva descripción (max 1000)"
}
```

Al menos uno de los campos debe estar presente.

### Response — éxito (`200`)

```json
{
  "data": {
    "id": "uuid",
    "organization_id": "uuid",
    "nombre": "Loft",
    "descripcion": "Loft moderno",
    "created_by": "uuid",
    "updated_by": "uuid",
    "created_at": "2026-07-08T00:00:00.000000Z",
    "updated_at": "2026-07-08T00:00:01.000000Z"
  }
}
```

### Comportamiento

- `updated_by` se asigna con el `user_id` del actor (R-11).
- R-08: Los catálogos del sistema (`organization_id IS NULL`) **no pueden modificarse**.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 403 | `SYSTEM_CATALOG_READONLY` | Intento de modificar un catálogo del sistema |
| 404 | `PROPERTY_TYPE_NOT_FOUND` | El tipo no existe o pertenece a otra organización |
| 409 | `PROPERTY_TYPE_NAME_DUPLICATE` | El nuevo nombre ya existe en la misma organización |
| 422 | `VALIDATION_ERROR` | Campos inválidos |

## DELETE /api/v1/property-types/{property_type}

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B02-crud-catalogos]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-01]]

### Response — éxito (`204`)

Sin body. Soft-delete exitoso.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 403 | `SYSTEM_CATALOG_READONLY` | Intento de eliminar un catálogo del sistema |
| 404 | `PROPERTY_TYPE_NOT_FOUND` | El tipo no existe o pertenece a otra organización |
| 409 | `PROPERTY_TYPE_IN_USE` | El tipo está referenciado por propiedades activas (no soft-deleted) |

---

## GET /api/v1/property-statuses

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B02-crud-catalogos]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-01]]

### Request

Sin body. Header `Authorization: Bearer <access_token>` requerido.

### Response — éxito (`200`)

```json
{
  "data": [
    {
      "id": "uuid",
      "organization_id": null,
      "nombre": "Disponible",
      "descripcion": "Unidad libre, sin ocupante",
      "created_by": null,
      "updated_by": null,
      "created_at": "2026-07-08T00:00:00.000000Z",
      "updated_at": "2026-07-08T00:00:00.000000Z"
    }
  ]
}
```

### Comportamiento

- **Tenant isolation (R-09):** Devuelve registros con `organization_id IS NULL` (catálogos del sistema) +
  registros con `organization_id` igual al del usuario autenticado.
- Ordenados alfabéticamente por `nombre`.
- Lectura permitida para todos los roles autenticados.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |

## POST /api/v1/property-statuses

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B02-crud-catalogos]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-01]]

### Request

```json
{
  "nombre": "string — obligatorio — nombre del estado (max 255)",
  "descripcion": "string — opcional — descripción (max 1000)"
}
```

### Response — éxito (`201`)

```json
{
  "data": {
    "id": "uuid",
    "organization_id": "uuid",
    "nombre": "Pre-venta",
    "descripcion": "Unidad en pre-venta",
    "created_by": "uuid",
    "updated_by": null,
    "created_at": "2026-07-08T00:00:00.000000Z",
    "updated_at": "2026-07-08T00:00:00.000000Z"
  }
}
```

### Comportamiento

- `organization_id` se asigna automáticamente del tenant del usuario autenticado.
- `created_by` se asigna con el `user_id` del actor (R-11).

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 409 | `PROPERTY_STATUS_NAME_DUPLICATE` | Ya existe un estado con ese nombre en la misma organización (case-insensitive) |
| 422 | `VALIDATION_ERROR` | Campos faltantes o inválidos |

## GET /api/v1/property-statuses/{property_status}

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B02-crud-catalogos]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-01]]

### Response — éxito (`200`)

Misma estructura que el elemento individual del index.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `PROPERTY_STATUS_NOT_FOUND` | El estado no existe o pertenece a otra organización |

## PATCH /api/v1/property-statuses/{property_status}

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B02-crud-catalogos]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-01]]

### Request

```json
{
  "nombre": "string — opcional — nuevo nombre (max 255)",
  "descripcion": "string — opcional — nueva descripción (max 1000)"
}
```

### Response — éxito (`200`)

Misma estructura que la de update de property-types.

### Comportamiento

- `updated_by` se asigna con el `user_id` del actor (R-11).
- R-08: Los catálogos del sistema (`organization_id IS NULL`) **no pueden modificarse**.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 403 | `SYSTEM_CATALOG_READONLY` | Intento de modificar un catálogo del sistema |
| 404 | `PROPERTY_STATUS_NOT_FOUND` | El estado no existe o pertenece a otra organización |
| 409 | `PROPERTY_STATUS_NAME_DUPLICATE` | El nuevo nombre ya existe en la misma organización |
| 422 | `VALIDATION_ERROR` | Campos inválidos |

## DELETE /api/v1/property-statuses/{property_status}

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B02-crud-catalogos]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-01]]

### Response — éxito (`204`)

Sin body. Soft-delete exitoso.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 403 | `SYSTEM_CATALOG_READONLY` | Intento de eliminar un catálogo del sistema |
| 404 | `PROPERTY_STATUS_NOT_FOUND` | El estado no existe o pertenece a otra organización |
| 409 | `PROPERTY_STATUS_IN_USE` | El estado está referenciado por propiedades activas (no soft-deleted) |

---

## GET /api/v1/condominiums

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B03-crud-condominios-torres]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-02]]

### Request

Sin body. Header `Authorization: Bearer <access_token>` requerido.

### Response — éxito (`200`)

```json
{
  "data": [
    {
      "id": "uuid",
      "organization_id": "uuid",
      "nombre": "Conjunto Las Palmas",
      "direccion": "Calle 123",
      "nit": "900123456-7",
      "created_by": "uuid",
      "updated_by": null,
      "created_at": "2026-07-08T00:00:00.000000Z",
      "updated_at": "2026-07-08T00:00:00.000000Z"
    }
  ]
}
```

### Comportamiento

- **Tenant isolation (R-09):** Solo condominios de la organización del usuario.
- **Staff scoping (R-09-bis):** Usuarios con scope `condominium` solo ven los condominios asignados.
  Usuarios sin scope `organization` ni `condominium` (ej. residentes con scope `unit`) reciben 403.
- Ordenados alfabéticamente por `nombre`.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 403 | `FORBIDDEN` | Usuario sin scope de condominio/organización (ej. residente puro) |

## POST /api/v1/condominiums

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B03-crud-condominios-torres]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-02]]

### Request

```json
{
  "nombre": "string — obligatorio — nombre del condominio (max 255)",
  "direccion": "string — opcional — dirección (max 500)",
  "nit": "string — opcional — NIT (max 50)"
}
```

### Response — éxito (`201`)

```json
{
  "condominium": {
    "id": "uuid",
    "organization_id": "uuid",
    "nombre": "Conjunto El Paraíso",
    "direccion": "Avenida Siempre Viva 742",
    "nit": "800987654-3",
    "created_by": "uuid",
    "updated_by": null,
    "created_at": "2026-07-08T00:00:00.000000Z",
    "updated_at": "2026-07-08T00:00:00.000000Z"
  }
}
```

### Comportamiento

- `organization_id` se asigna automáticamente del tenant del usuario.
- `created_by` se asigna con el `user_id` del actor (R-11).

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 409 | `CONDOMINIUM_NAME_DUPLICATE` | Ya existe un condominio con ese nombre en la misma organización (case-insensitive) |
| 422 | `VALIDATION_ERROR` | Campos faltantes o inválidos |

## GET /api/v1/condominiums/{condominium}

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B03-crud-condominios-torres]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-02]]

### Response — éxito (`200`)

```json
{
  "condominium": {
    "id": "uuid",
    "organization_id": "uuid",
    "nombre": "Conjunto Las Flores",
    "direccion": "Calle 456",
    "nit": null,
    "created_by": "uuid",
    "updated_by": null,
    "created_at": "2026-07-08T00:00:00.000000Z",
    "updated_at": "2026-07-08T00:00:00.000000Z",
    "towers": [
      {
        "id": "uuid",
        "condominium_id": "uuid",
        "nombre": "Torre A",
        "created_by": "uuid",
        "updated_by": null,
        "created_at": "2026-07-08T00:00:00.000000Z",
        "updated_at": "2026-07-08T00:00:00.000000Z"
      }
    ]
  }
}
```

### Comportamiento

- Incluye las torres (`towers`) como arreglo anidado en el detalle (no en index).
- **R-10 Anti-enumeration:** 404 unificado con 403 para condominios de otra org o fuera del scope del usuario.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `CONDOMINIUM_NOT_FOUND` | El condominio no existe, pertenece a otra organización, o está fuera del scope del usuario |

## PATCH /api/v1/condominiums/{condominium}

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B03-crud-condominios-torres]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-02]]

### Request

```json
{
  "nombre": "string — opcional — nuevo nombre (max 255)",
  "direccion": "string — opcional — nueva dirección (max 500)",
  "nit": "string — opcional — nuevo NIT (max 50)"
}
```

### Response — éxito (`200`)

Misma estructura que el show (sin `towers`).

### Comportamiento

- `updated_by` se asigna con el `user_id` del actor (R-11).

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `CONDOMINIUM_NOT_FOUND` | El condominio no existe o está fuera del scope del usuario |
| 409 | `CONDOMINIUM_NAME_DUPLICATE` | El nuevo nombre ya existe en la misma organización |
| 422 | `VALIDATION_ERROR` | Campos inválidos |

## DELETE /api/v1/condominiums/{condominium}

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B03-crud-condominios-torres]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-02]]

### Response — éxito (`204`)

Sin body. Soft-delete exitoso.

### Comportamiento

- **R-03:** No se puede eliminar si tiene torres o propiedades activas (no soft-deleted).

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `CONDOMINIUM_NOT_FOUND` | El condominio no existe o está fuera del scope del usuario |
| 409 | `CONDOMINIUM_HAS_TOWERS` | El condominio tiene torres activas |
| 409 | `CONDOMINIUM_HAS_PROPERTIES` | El condominio tiene propiedades activas |

---

## GET /api/v1/condominiums/{condominium}/towers

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B03-crud-condominios-torres]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-02]]

### Request

Sin body. Header `Authorization: Bearer <access_token>` requerido.

### Response — éxito (`200`)

```json
{
  "data": [
    {
      "id": "uuid",
      "condominium_id": "uuid",
      "nombre": "Torre Norte",
      "created_by": "uuid",
      "updated_by": null,
      "created_at": "2026-07-08T00:00:00.000000Z",
      "updated_at": "2026-07-08T00:00:00.000000Z"
    }
  ]
}
```

### Comportamiento

- **R-01:** Ruta anidada bajo el condominio padre.
- **R-09-bis:** Si el usuario tiene scope `tower`, solo ve las torres de su scope.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `CONDOMINIUM_NOT_FOUND` | El condominio no existe o está fuera del scope del usuario |

## POST /api/v1/condominiums/{condominium}/towers

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B03-crud-condominios-torres]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-02]]

### Request

```json
{
  "nombre": "string — obligatorio — nombre de la torre (max 255)"
}
```

### Response — éxito (`201`)

```json
{
  "tower": {
    "id": "uuid",
    "condominium_id": "uuid",
    "nombre": "Torre Central",
    "created_by": "uuid",
    "updated_by": null,
    "created_at": "2026-07-08T00:00:00.000000Z",
    "updated_at": "2026-07-08T00:00:00.000000Z"
  }
}
```

### Comportamiento

- `condominium_id` se asigna desde la ruta anidada (R-01, R-07).
- `created_by` se asigna con el `user_id` del actor (R-11).

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `CONDOMINIUM_NOT_FOUND` | El condominio no existe o está fuera del scope del usuario |
| 409 | `TOWER_NAME_DUPLICATE` | Ya existe una torre con ese nombre en el mismo condominio (case-insensitive) |
| 422 | `VALIDATION_ERROR` | Campos faltantes o inválidos |

## GET /api/v1/towers/{tower}

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B03-crud-condominios-torres]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-02]]

### Response — éxito (`200`)

```json
{
  "tower": {
    "id": "uuid",
    "condominium_id": "uuid",
    "nombre": "Torre Norte",
    "created_by": "uuid",
    "updated_by": null,
    "created_at": "2026-07-08T00:00:00.000000Z",
    "updated_at": "2026-07-08T00:00:00.000000Z"
  }
}
```

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `TOWER_NOT_FOUND` | La torre no existe, pertenece a otra organización, o está fuera del scope del usuario |

## PATCH /api/v1/towers/{tower}

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B03-crud-condominios-torres]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-02]]

### Request

```json
{
  "nombre": "string — opcional — nuevo nombre (max 255)"
}
```

### Comportamiento

- **R-07:** `condominium_id` es inmutable. Si se envía en el body, se ignora silenciosamente.
- `updated_by` se asigna con el `user_id` del actor (R-11).

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `TOWER_NOT_FOUND` | La torre no existe o está fuera del scope del usuario |
| 409 | `TOWER_NAME_DUPLICATE` | El nuevo nombre ya existe en el mismo condominio |
| 422 | `VALIDATION_ERROR` | Campos inválidos |

## DELETE /api/v1/towers/{tower}

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B03-crud-condominios-torres]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-02]]

### Response — éxito (`204`)

Sin body. Soft-delete exitoso.

### Comportamiento

- **R-03:** No se puede eliminar si tiene propiedades activas (no soft-deleted).

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `TOWER_NOT_FOUND` | La torre no existe o está fuera del scope del usuario |
| 409 | `TOWER_HAS_PROPERTIES` | La torre tiene propiedades activas |

---

## GET /api/v1/condominiums/{condominium}/properties

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B04-crud-unidades]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-03]]

### Request

Sin body. Header `Authorization: Bearer <access_token>` requerido.

Query params:
- `tower_id` — UUID — opcional — filtrar por torre
- `type_id` — UUID — opcional — filtrar por tipo de propiedad
- `status_id` — UUID — opcional — filtrar por estado
- `search` — string — opcional — buscar por código (búsqueda parcial, case-insensitive)
- `cursor` — string — opcional — cursor para paginación (UUID v7 del último elemento de la página anterior)
- `limit` — int — opcional (default 15, max 50) — cantidad de resultados por página

### Response — éxito (`200`)

```json
{
  "data": [
    {
      "id": "uuid",
      "condominium_id": "uuid",
      "tower_id": "uuid|null",
      "property_type_id": "uuid",
      "property_status_id": "uuid",
      "codigo": "A-101",
      "piso": 1,
      "created_by": "uuid",
      "updated_by": null,
      "created_at": "2026-07-08T00:00:00.000000Z",
      "updated_at": "2026-07-08T00:00:00.000000Z"
    }
  ],
  "meta": {
    "next_cursor": "uuid|null"
  }
}
```

### Comportamiento

- **R-09:** Tenant isolation — solo propiedades de condominios en la organización del usuario.
- **R-09-bis:** Staff scoping — usuarios con scope `condominium` solo ven propiedades de sus condominios asignados. Usuarios con scope `tower` solo ven propiedades de sus torres.
- **CA 15:** Residentes (solo scope `unit`) reciben 403.
- **R-10:** `area_m2` **no** se expone en el listado (PropertyListResource).
- **Paginación:** Cursor-based usando UUID v7 como cursor (`WHERE id > cursor`). `next_cursor` es `null` cuando no hay más páginas. Filtros se combinan con paginación.
- **Filtros:** `tower_id`, `type_id`, `status_id`, `search` son combinables (intersección AND).

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 403 | `FORBIDDEN` | Usuario sin scope de listado (ej. residente puro) |
| 404 | `CONDOMINIUM_NOT_FOUND` | El condominio no existe o está fuera del scope del usuario |

## POST /api/v1/condominiums/{condominium}/properties

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B04-crud-unidades]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-03]]

### Request

```json
{
  "codigo": "string — obligatorio — código de la unidad (max 255)",
  "tower_id": "uuid — opcional — torre a la que pertenece (debe pertenecer al condominio)",
  "property_type_id": "uuid — obligatorio — tipo de propiedad",
  "property_status_id": "uuid — obligatorio — estado de propiedad",
  "piso": "int — opcional — número de piso",
  "area_m2": "numeric — opcional — área en metros cuadrados (min 0)"
}
```

### Response — éxito (`201`)

```json
{
  "property": {
    "id": "uuid",
    "condominium_id": "uuid",
    "tower_id": "uuid|null",
    "property_type_id": "uuid",
    "property_status_id": "uuid",
    "codigo": "AP-301",
    "piso": 3,
    "area_m2": 82.00,
    "created_by": "uuid",
    "updated_by": null,
    "created_at": "2026-07-08T00:00:00.000000Z",
    "updated_at": "2026-07-08T00:00:00.000000Z"
  }
}
```

### Comportamiento

- `condominium_id` se asigna desde la ruta anidada (R-01, R-07).
- `created_by` se asigna con el `user_id` del actor (R-11).
- **R-02:** `codigo` único por `condominium_id` → 409 `PROPERTY_CODE_DUPLICATE`.
- **CA 7:** Si se envía `tower_id`, la torre debe pertenecer al `condominium_id` → 422 `TOWER_CONDOMINIUM_MISMATCH`.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `CONDOMINIUM_NOT_FOUND` | El condominio no existe o está fuera del scope del usuario |
| 409 | `PROPERTY_CODE_DUPLICATE` | Ya existe una unidad con ese código en el mismo condominio |
| 422 | `VALIDATION_ERROR` | Campos faltantes o inválidos |
| 422 | `TOWER_CONDOMINIUM_MISMATCH` | La torre no pertenece al condominio |

## GET /api/v1/properties/{property}

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B04-crud-unidades]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-03]]

### Response — éxito (`200`)

```json
{
  "property": {
    "id": "uuid",
    "condominium_id": "uuid",
    "tower_id": "uuid|null",
    "property_type_id": "uuid",
    "property_status_id": "uuid",
    "codigo": "A-101",
    "piso": 1,
    "area_m2": 75.50,
    "created_by": "uuid",
    "updated_by": null,
    "created_at": "2026-07-08T00:00:00.000000Z",
    "updated_at": "2026-07-08T00:00:00.000000Z",
    "type": { "id": "uuid", "nombre": "Apartamento", "..." },
    "status": { "id": "uuid", "nombre": "Disponible", "..." },
    "tower": { "id": "uuid", "nombre": "Torre A", "..." },
    "condominium": { "id": "uuid", "nombre": "Conjunto Las Flores", "..." }
  }
}
```

### Comportamiento

- **R-10:** `area_m2` **sí** se expone en el detalle (PropertyResource).
- Incluye relaciones anidadas: `type`, `status`, `tower`, `condominium`.
- **R-09-bis:** Staff scoping — solo accesible si está en el scope del usuario.
- **R-10 Anti-enumeration:** 403/404 unificados para unidades de otra org o fuera del scope.
- **CA 16:** Residentes pueden ver su propia unidad (asignación `scope_type=unit` con `scope_id=property_id`).
- **CA 17:** Residentes reciben 404 para unidades ajenas.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `PROPERTY_NOT_FOUND` | La propiedad no existe, pertenece a otra organización, o está fuera del scope del usuario |

## PATCH /api/v1/properties/{property}

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B04-crud-unidades]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-03]]

### Request

```json
{
  "codigo": "string — opcional — nuevo código (max 255)",
  "tower_id": "uuid — opcional — nueva torre (debe pertenecer al condominio de la unidad)",
  "property_type_id": "uuid — opcional — nuevo tipo",
  "property_status_id": "uuid — opcional — nuevo estado",
  "piso": "int — opcional — nuevo piso",
  "area_m2": "numeric — opcional — nueva área"
}
```

Al menos uno de los campos debe estar presente.

### Response — éxito (`200`)

Misma estructura que el show.

### Comportamiento

- **R-07:** `condominium_id` es inmutable. Si se envía en el body, se ignora silenciosamente.
- `updated_by` se asigna con el `user_id` del actor (R-11).
- **R-02:** Si se cambia `codigo`, verificar unicidad en el mismo condominio → 409 `PROPERTY_CODE_DUPLICATE`.
- **CA 7:** Si se cambia `tower_id`, verificar que la torre pertenece al condominio de la unidad → 422 `TOWER_CONDOMINIUM_MISMATCH`.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `PROPERTY_NOT_FOUND` | La propiedad no existe o está fuera del scope del usuario |
| 409 | `PROPERTY_CODE_DUPLICATE` | El nuevo código ya existe en el mismo condominio |
| 422 | `VALIDATION_ERROR` | Campos inválidos |
| 422 | `TOWER_CONDOMINIUM_MISMATCH` | La torre no pertenece al condominio de la unidad |

## DELETE /api/v1/properties/{property}

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B04-crud-unidades]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-03]]

### Response — éxito (`204`)

Sin body. Soft-delete exitoso.

### Comportamiento

- **R-03:** No se puede eliminar si tiene ocupantes activos (`property_occupants`) → 409 `PROPERTY_HAS_OCCUPANTS`.
  Si la tabla `property_occupants` aún no existe (pendiente de DIRECTORIO), se omite la verificación
  con un guard clause y `@todo` explícito.
- **R-04:** Soft delete.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `PROPERTY_NOT_FOUND` | La propiedad no existe o está fuera del scope del usuario |
| 409 | `PROPERTY_HAS_OCCUPANTS` | La propiedad tiene ocupantes activos |

---

## GET /api/v1/properties/{property}/coefficients

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B05-coeficientes-tree]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-04]]

### Request

Sin body. Header `Authorization: Bearer <access_token>` requerido.

### Response — éxito (`200`)

```json
{
  "data": [
    {
      "id": "uuid",
      "property_id": "uuid",
      "tipo": "copropiedad",
      "valor": 0.25,
      "vigente_desde": "2026-07-08",
      "vigente_hasta": null,
      "created_by": "uuid",
      "updated_by": null,
      "created_at": "2026-07-08T00:00:00.000000Z",
      "updated_at": "2026-07-08T00:00:00.000000Z"
    },
    {
      "id": "uuid",
      "property_id": "uuid",
      "tipo": "copropiedad",
      "valor": 0.20,
      "vigente_desde": "2025-01-01",
      "vigente_hasta": "2026-07-07",
      "created_by": "uuid",
      "updated_by": "uuid",
      "created_at": "2025-01-01T00:00:00.000000Z",
      "updated_at": "2026-07-08T00:00:00.000000Z"
    }
  ]
}
```

### Comportamiento

- Devuelve todos los coeficientes de la unidad: activos (`vigente_hasta: null`) e históricos (`vigente_hasta` con fecha).
- Ordenados por `tipo` ASC y `vigente_desde` DESC.
- **R-09:** Tenant isolation vía property → condominium → organization.
- **R-09-bis:** Accesible para admin (org/condo scope), staff con scope torre/unidad, y residentes solo para su propia unidad.
- **R-10:** Anti-enumeration — 404 para unidades fuera del scope del usuario.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `PROPERTY_NOT_FOUND` | La propiedad no existe, pertenece a otra organización, o está fuera del scope del usuario |

---

## PATCH /api/v1/condominiums/{condominium}/coefficients

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B05-coeficientes-tree]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-04]]

### Request

```json
{
  "items": [
    {
      "property_id": "uuid — obligatorio — ID de la unidad",
      "tipo": "string — obligatorio — tipo de coeficiente (copropiedad, parqueadero, deposito, mantenimiento)",
      "valor": "float — obligatorio — valor del coeficiente (0–1)"
    }
  ]
}
```

### Response — éxito (`200`)

```json
{
  "data": [
    {
      "id": "uuid",
      "property_id": "uuid",
      "tipo": "copropiedad",
      "valor": 0.25,
      "vigente_desde": "2026-07-08",
      "vigente_hasta": null,
      "created_by": "uuid",
      "updated_by": null,
      "created_at": "2026-07-08T00:00:00.000000Z",
      "updated_at": "2026-07-08T00:00:00.000000Z"
    }
  ],
  "warnings": [
    {
      "code": "COEFFICIENT_SUM_MISMATCH",
      "detail": {
        "condominium_id": "uuid",
        "sum": 0.97
      }
    }
  ]
}
```

### Comportamiento

- **Atómico:** Todas las operaciones dentro de una transacción DB. Si cualquier item falla, se hace rollback completo — ningún coeficiente queda modificado.
- **R-05:** Al crear un nuevo coeficiente para una `property_id + tipo`, se cierra automáticamente el anterior vigente seteando `vigente_hasta = hoy - 1 día` y `updated_by = user_id`.
- **R-06:** Después de aplicar todos los coeficientes, se calcula la suma de todos los coeficientes de tipo `copropiedad` activos en el condominio. Si la suma ≠ 1.0, la respuesta incluye `warnings: [{ code: "COEFFICIENT_SUM_MISMATCH", detail: { condominium_id, sum } }]`. Esto no bloquea el guardado — es un warning para que el frontend muestre la discrepancia al usuario.
- **R-06-bis:** Solo se aceptan tipos del set cerrado: `copropiedad`, `parqueadero`, `deposito`, `mantenimiento`.
- **R-09-bis:** Solo usuarios con scope `organization` o `condominium` pueden gestionar coeficientes. Scope `tower` es insuficiente (datos financieros).
- **R-11:** `created_by` y `updated_by` seteados con el `user_id` del actor autenticado.
- Los coeficientes de tipos no-copropiedad (parqueadero, depósito, mantenimiento) no participan de la validación de suma de R-06.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
||---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `CONDOMINIUM_NOT_FOUND` | El condominio no existe, pertenece a otra organización, o está fuera del scope del usuario |
| 422 | `COEFFICIENT_OUT_OF_RANGE` | El `valor` de un coeficiente está fuera del rango 0–1 |
| 422 | `COEFFICIENT_INVALID_TYPE` | El `tipo` no pertenece al set cerrado (R-06-bis) |
| 422 | `PROPERTY_NOT_IN_CONDOMINIUM` | Una o más unidades del body no pertenecen al condominio del path |
| 422 | `VALIDATION_ERROR` | Campos faltantes o mal formados (ej. items array vacío, property_id no UUID) |

---

## GET /api/v1/condominiums/{condominium}/tree

**Bloque que lo produce:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B05-coeficientes-tree]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-04]]

### Request

Sin body. Header `Authorization: Bearer <access_token>` requerido.

### Response — éxito (`200`)

```json
{
  "tree": {
    "id": "uuid",
    "nombre": "Conjunto Las Flores",
    "organization_id": "uuid",
    "towers": [
      {
        "id": "uuid",
        "nombre": "Torre A",
        "properties_count": 12
      },
      {
        "id": "uuid",
        "nombre": "Torre B",
        "properties_count": 8
      }
    ],
    "untowered_properties_count": 3
  }
}
```

### Comportamiento

- Devuelve la estructura jerárquica: condominio → torres → conteo de unidades por torre.
- Incluye conteo de unidades sin torre (`untowered_properties_count`).
- Las torres se ordenan alfabéticamente por `nombre`.
- Solo cuenta propiedades activas (no soft-deleted).
- **R-09:** Tenant isolation — solo condominios de la organización del usuario.
- **R-09-bis:** Solo usuarios con scope `organization` o `condominium`. Scope `tower` es explícitamente insuficiente para ver el tree completo (PANORAMA §5 R-09-bis).
- **R-10:** Anti-enumeration — 404 para condominios fuera del scope del usuario.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
||---|---|---|
| 401 | — | Usuario no autenticado |
| 404 | `CONDOMINIUM_NOT_FOUND` | El condominio no existe, pertenece a otra organización, o está fuera del scope del usuario |
