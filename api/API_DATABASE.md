---
tipo: referencia
proyecto: api
actualizado: 2026-07-05
---

# API_DATABASE — Esquema real implementado

> Las **convenciones** de esquema (tipos de clave, naming, soft delete) y las **tablas
> fundacionales** conceptuales viven en [[../shared/DATA_MODEL]] — no se repiten aquí. Este
> documento es el **esquema físico real**, tabla por tabla, tal como quedó implementado — se llena a
> medida que cada bloque que crea una tabla llega a `done` (parte del DoD de API, ver
> [[../_system/05_DEFINITION_OF_DONE]] §2). Mientras un bloque no esté `done`, su tabla no aparece
> aquí — este documento nunca describe una tabla que no existe todavía en el código.

## Estado

Migraciones de negocio creadas por AUTH-B01 (registro por invitación). Las tablas por defecto de
Laravel (`users`, `password_reset_tokens`, `sessions`) fueron reemplazadas por el esquema custom
de AUTH.

### Tablas de infraestructura (heredadas del bootstrap)

- `cache` / `cache_locks` — estándar de Laravel
- `jobs` / `job_batches` / `failed_jobs` — estándar de Laravel

### Tablas de negocio

#### `organizations`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `nombre` | `text` | NOT NULL |
| `created_at` | `timestamptz` | |
| `updated_at` | `timestamptz` | |
| `deleted_at` | `timestamptz` | nullable — soft delete |

#### `users`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `organization_id` | `uuid` | FK → `organizations.id` |
| `email` | `text` | UNIQUE, NOT NULL |
| `password_hash` | `text` | NOT NULL |
| `estado` | `text` | NOT NULL, DEFAULT `active` |
| `created_at` | `timestamptz` | |
| `updated_at` | `timestamptz` | |
| `deleted_at` | `timestamptz` | nullable — soft delete |

#### `contacts`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `user_id` | `uuid` | FK → `users.id`, UNIQUE |
| `nombre` | `text` | NOT NULL |
| `email` | `text` | NOT NULL |
| `telefono` | `text` | nullable |
| `created_at` | `timestamptz` | |
| `updated_at` | `timestamptz` | |
| `deleted_at` | `timestamptz` | nullable — soft delete |

#### `invitations`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `organization_id` | `uuid` | FK → `organizations.id` |
| `email` | `text` | NOT NULL |
| `token` | `text` | UNIQUE, NOT NULL |
| `estado` | `text` | NOT NULL, DEFAULT `vigente` |
| `expira_en` | `timestamptz` | NOT NULL |
| `created_at` | `timestamptz` | |
| `updated_at` | `timestamptz` | |
| `deleted_at` | `timestamptz` | nullable — soft delete |

#### `refresh_tokens`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `user_id` | `uuid` | FK → `users.id`, CASCADE ON DELETE |
| `jti` | `text` | UNIQUE, NOT NULL — JWT ID del refresh token |
| `estado` | `text` | NOT NULL — `valido` o `invalidado` |
| `expires_at` | `timestamptz` | NOT NULL — fecha de expiración del JWT |
| `created_at` | `timestamptz` | |
| `updated_at` | `timestamptz` | |
| `deleted_at` | `timestamptz` | nullable — soft delete |

#### `roles`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `name` | `text` | UNIQUE, NOT NULL |
| `description` | `text` | nullable |
| `created_at` | `timestamptz` | |
| `updated_at` | `timestamptz` | |

#### `permissions`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `name` | `text` | UNIQUE, NOT NULL — formato `recurso.accion` |
| `description` | `text` | nullable |
| `created_at` | `timestamptz` | |
| `updated_at` | `timestamptz` | |

#### `permission_role` (pivote M:N entre permissions y roles)

| Columna | Tipo | Constraints |
|---|---|---|
| `permission_id` | `uuid` | FK → `permissions.id`, PK compuesta |
| `role_id` | `uuid` | FK → `roles.id`, PK compuesta |
| `created_at` | `timestamptz` | |
| `updated_at` | `timestamptz` | |

#### `role_assignments`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `user_id` | `uuid` | FK → `users.id`, CASCADE ON DELETE |
| `role_id` | `uuid` | FK → `roles.id`, CASCADE ON DELETE |
| `scope_type` | `text` | NOT NULL — `organization`, `condominium`, `tower`, o `unit` |
| `scope_id` | `uuid` | nullable — ID de la entidad scope (null = global para ese scope_type) |
| `expires_at` | `timestamptz` | nullable — fecha de expiración de la asignación |
| `created_at` | `timestamptz` | |
| `updated_at` | `timestamptz` | |
| `deleted_at` | `timestamptz` | nullable — soft delete |

Unique constraint: `(user_id, role_id, scope_type, scope_id)` — un usuario no puede tener la misma
asignación de rol duplicada para el mismo scope.

#### `user_mfa`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `user_id` | `uuid` | FK → `users.id`, UNIQUE, CASCADE ON DELETE |
| `totp_secret` | `text` | NOT NULL — encriptado con Laravel encryption |
| `recovery_codes` | `jsonb` | NOT NULL — array de objetos `[{"hash": "$2y$...", "used_at": null}]` |
| `enabled_at` | `timestamptz` | NOT NULL — fecha/hora de activación del MFA |
| `created_at` | `timestamptz` | DEFAULT NOW() |
| `updated_at` | `timestamptz` | DEFAULT NOW() |

#### `password_reset_tokens`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `email` | `text` | NOT NULL, INDEX |
| `token_hash` | `text` | NOT NULL — SHA-256 del token en texto plano |
| `expires_at` | `timestamptz` | NOT NULL — `created_at + 60 min` |
| `created_at` | `timestamptz` | NOT NULL, DEFAULT CURRENT_TIMESTAMP |
| `updated_at` | `timestamptz` | NOT NULL, DEFAULT CURRENT_TIMESTAMP |
