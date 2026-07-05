---
tipo: referencia
proyecto: api
actualizado: 2026-07-04
---

# API_DATABASE — Esquema real implementado

> Las **convenciones** de esquema (tipos de clave, naming, soft delete) y las **tablas
> fundacionales** conceptuales viven en [[../shared/DATA_MODEL]] — no se repiten aquí. Este
> documento es el **esquema físico real**, tabla por tabla, tal como quedó implementado — se llena a
> medida que cada bloque que crea una tabla llega a `done` (parte del DoD de API, ver
> [[../_system/05_DEFINITION_OF_DONE]] §2). Mientras un bloque no esté `done`, su tabla no aparece
> aquí — este documento nunca describe una tabla que no existe todavía en el código.

## Estado

Tablas fundacionales creadas en `AUTH-B01`.

### `organizations`

| Columna | Tipo | Constraints | Notas |
|---|---|---|---|
| `id` | `uuid` | PK | UUID v7 |
| `nombre` | `varchar(255)` | NOT NULL | Nombre de la organización |
| `slug` | `varchar(255)` | UNIQUE, NOT NULL | Slug único para URLs |
| `created_at` | `timestamptz` | NOT NULL | |
| `updated_at` | `timestamptz` | NOT NULL | |

- **Bloque que la creó:** [[../features/AUTH/blocks/AUTH-B01-registro-por-invitacion]]

### `users`

| Columna | Tipo | Constraints | Notas |
|---|---|---|---|
| `id` | `uuid` | PK | UUID v7 |
| `organization_id` | `uuid` | FK → `organizations.id`, NOT NULL, ON DELETE CASCADE | |
| `email` | `varchar(255)` | UNIQUE, NOT NULL | |
| `password` | `varchar(255)` | NOT NULL | Hasheado con bcrypt |
| `name` | `varchar(255)` | NOT NULL | |
| `estado` | `varchar(255)` | NOT NULL, DEFAULT `'active'` | `active` o `inactive` |
| `created_at` | `timestamptz` | NOT NULL | |
| `updated_at` | `timestamptz` | NOT NULL | |

- **Índices:** `users_email_unique` (UNIQUE sobre `email`)
- **Bloque que la creó:** [[../features/AUTH/blocks/AUTH-B01-registro-por-invitacion]]

### `contacts`

| Columna | Tipo | Constraints | Notas |
|---|---|---|---|
| `id` | `uuid` | PK | UUID v7 |
| `user_id` | `uuid` | FK → `users.id`, NOT NULL, ON DELETE CASCADE | |
| `phone` | `varchar(20)` | NULLABLE | |
| `created_at` | `timestamptz` | NOT NULL | |
| `updated_at` | `timestamptz` | NOT NULL | |

- **Bloque que la creó:** [[../features/AUTH/blocks/AUTH-B01-registro-por-invitacion]]

### `invitations`

| Columna | Tipo | Constraints | Notas |
|---|---|---|---|
| `id` | `uuid` | PK | UUID v7 |
| `organization_id` | `uuid` | FK → `organizations.id`, NOT NULL, ON DELETE CASCADE | |
| `email` | `varchar(255)` | NOT NULL, INDEXED | Email del invitado |
| `token` | `varchar(255)` | UNIQUE, NOT NULL | Token de invitación |
| `expira_en` | `timestamptz` | NOT NULL | Fecha de expiración |
| `estado` | `varchar(255)` | NOT NULL, DEFAULT `'vigente'` | `vigente`, `consumida`, o `expirada` |
| `created_at` | `timestamptz` | NOT NULL | |
| `updated_at` | `timestamptz` | NOT NULL | |

- **Índices:** `invitations_token_unique` (UNIQUE sobre `token`), `invitations_email_index` (INDEX sobre `email`)
- **Bloque que la creó:** [[../features/AUTH/blocks/AUTH-B01-registro-por-invitacion]]
