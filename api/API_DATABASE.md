---
tipo: referencia
proyecto: api
actualizado: 2026-07-10
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

> Corregida por `DIRECTORIO-B01` (2026-07-10) para cumplir su propio diseño aprobado de AUTH
> (`user_id` nullable + `organization_id` propio, ADR-001) — `AUTH-B01` la había implementado con
> `user_id` NOT NULL. Backfill de datos reales ejecutado sobre las filas existentes. Ver
> `_state/BOARD.md` (nota de `DIRECTORIO-B01`) y
> [[../features/DIRECTORIO/blocks/DIRECTORIO-B01-fundacion-contactos-ocupantes]].

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `organization_id` | `uuid` | FK → `organizations.id`, NOT NULL, CASCADE ON DELETE |
| `user_id` | `uuid` | FK → `users.id`, nullable — un contacto puede existir sin user (R-DIR-02) |
| `nombre` | `text` | NOT NULL |
| `email` | `text` | NOT NULL |
| `telefono` | `text` | nullable |
| `created_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE |
| `updated_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE |
| `created_at` | `timestamptz` | |
| `updated_at` | `timestamptz` | |
| `deleted_at` | `timestamptz` | nullable — soft delete |

Partial unique index: `UNIQUE (user_id) WHERE user_id IS NOT NULL` — reemplaza el `UNIQUE` simple
original; varios contactos sin `user_id` no colisionan entre sí.

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

### Tablas de PROPIEDADES (PROPIEDADES-B01)

#### `condominiums`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `organization_id` | `uuid` | FK → `organizations.id`, NOT NULL, CASCADE ON DELETE |
| `nombre` | `text` | NOT NULL, UNIQUE(organization_id, nombre) |
| `direccion` | `text` | nullable |
| `nit` | `text` | nullable |
| `created_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE |
| `updated_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE |
| `created_at` | `timestamptz` | |
| `updated_at` | `timestamptz` | |
| `deleted_at` | `timestamptz` | nullable — soft delete |

#### `towers`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `condominium_id` | `uuid` | FK → `condominiums.id`, NOT NULL, CASCADE ON DELETE, inmutable |
| `nombre` | `text` | NOT NULL, UNIQUE(condominium_id, nombre) |
| `created_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE |
| `updated_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE |
| `created_at` | `timestamptz` | |
| `updated_at` | `timestamptz` | |
| `deleted_at` | `timestamptz` | nullable — soft delete |

#### `property_types`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `organization_id` | `uuid` | FK → `organizations.id`, nullable (NULL = sistema) |
| `nombre` | `text` | NOT NULL |
| `descripcion` | `text` | nullable |
| `created_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE |
| `updated_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE |
| `created_at` | `timestamptz` | |
| `updated_at` | `timestamptz` | |
| `deleted_at` | `timestamptz` | nullable — soft delete |

#### `property_statuses`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `organization_id` | `uuid` | FK → `organizations.id`, nullable (NULL = sistema) |
| `nombre` | `text` | NOT NULL |
| `descripcion` | `text` | nullable |
| `created_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE |
| `updated_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE |
| `created_at` | `timestamptz` | |
| `updated_at` | `timestamptz` | |
| `deleted_at` | `timestamptz` | nullable — soft delete |

#### `properties`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `condominium_id` | `uuid` | FK → `condominiums.id`, NOT NULL, CASCADE ON DELETE, inmutable |
| `tower_id` | `uuid` | FK → `towers.id`, nullable, SET NULL ON DELETE |
| `property_type_id` | `uuid` | FK → `property_types.id`, NOT NULL, RESTRICT ON DELETE |
| `property_status_id` | `uuid` | FK → `property_statuses.id`, NOT NULL, RESTRICT ON DELETE |
| `codigo` | `text` | NOT NULL, UNIQUE(condominium_id, codigo) |
| `piso` | `int` | nullable |
| `area_m2` | `decimal(10,2)` | nullable — dato sensible, solo en endpoint de detalle |
| `created_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE |
| `updated_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE |
| `created_at` | `timestamptz` | |
| `updated_at` | `timestamptz` | |
| `deleted_at` | `timestamptz` | nullable — soft delete |

#### `property_coefficients`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `property_id` | `uuid` | FK → `properties.id`, NOT NULL, CASCADE ON DELETE |
| `tipo` | `text` | NOT NULL, CHECK IN ('copropiedad', 'parqueadero', 'deposito', 'mantenimiento') |
| `valor` | `decimal(5,4)` | NOT NULL, rango 0-1 |
| `vigente_desde` | `date` | NOT NULL |
| `vigente_hasta` | `date` | nullable — NULL = vigente actual |
| `created_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE |
| `updated_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE |
| `created_at` | `timestamptz` | |
| `updated_at` | `timestamptz` | |
| `deleted_at` | `timestamptz` | nullable — soft delete |

Partial unique index: `UNIQUE (property_id, tipo) WHERE vigente_hasta IS NULL` — solo un coeficiente activo por propiedad+tipo.

### Tablas de DIRECTORIO (DIRECTORIO-B01)

#### `occupant_types`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `organization_id` | `uuid` | FK → `organizations.id`, nullable (NULL = sistema) |
| `nombre` | `text` | NOT NULL |
| `descripcion` | `text` | nullable |
| `created_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE |
| `updated_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE |
| `created_at` | `timestamptz` | |
| `updated_at` | `timestamptz` | |
| `deleted_at` | `timestamptz` | nullable — soft delete |

Catálogo de sistema sembrado por `OccupantTypeSeeder` (`propietario`, `residente`, `arrendatario`,
`familiar`, `organization_id = NULL`).

#### `property_occupants`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `contact_id` | `uuid` | FK → `contacts.id`, NOT NULL, CASCADE ON DELETE |
| `property_id` | `uuid` | FK → `properties.id`, NOT NULL, CASCADE ON DELETE |
| `occupant_type_id` | `uuid` | FK → `occupant_types.id`, NOT NULL, RESTRICT ON DELETE |
| `es_principal` | `boolean` | NOT NULL, DEFAULT `false` |
| `created_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE |
| `updated_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE |
| `created_at` | `timestamptz` | |
| `updated_at` | `timestamptz` | |
| `deleted_at` | `timestamptz` | nullable — soft delete |

Partial unique indexes:
- `UNIQUE (contact_id, property_id, occupant_type_id) WHERE deleted_at IS NULL` (R-DIR-11) — un
  contacto no puede tener el mismo tipo de ocupante repetido en la misma unidad mientras esté activo.
- `UNIQUE (property_id, occupant_type_id) WHERE es_principal = true AND deleted_at IS NULL`
  (R-DIR-07) — un solo ocupante principal por unidad+tipo mientras esté activo.

`PropertyController::destroy` (PROPIEDADES-B04) consulta esta tabla directamente para la regla R-03
("no eliminar unidad con ocupantes activos") — el guard clause temporal que asumía "sin ocupantes"
se reemplazó por la consulta real en esta misma sesión.

### Tablas de COBRANZA (COBRANZA-B01)

> Solo estructura (migraciones + modelos + seeders de permisos) — sin endpoints ni lógica de negocio
> todavía (eso llega en `COBRANZA-B02` en adelante). Bounded context de código: `src/Billing/`
> (R-COB-30, nomenclatura en inglés, consistente con `Auth`/`Authorization`/`Mfa`/`Properties`).
> Dinero en `NUMERIC(15,2)` COP; coeficiente/base de cálculo en `NUMERIC(5,4)`. `Invoice → property`
> y `PaymentReceipt → property`/`→ contact` son lecturas cross-bounded-context de solo lectura, ver
> [[../shared/adr/ADR-002-lectura-cross-context-modulo-monolito]].

#### `charge_concepts`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `condominium_id` | `uuid` | FK → `condominiums.id`, NOT NULL, CASCADE ON DELETE |
| `nombre` | `text` | NOT NULL |
| `tipo` | `text` | NOT NULL, CHECK IN (`administracion`, `fondo_imprevistos`, `multa`, `extraordinaria`) |
| `metodo_calculo` | `text` | NOT NULL, CHECK IN (`coeficiente`, `fijo`, `por_area`, `manual`) |
| `valor_base` | `numeric(15,2)` | NOT NULL |
| `activo` | `boolean` | NOT NULL, DEFAULT `true` |
| `created_by` / `updated_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE |
| `created_at` / `updated_at` | `timestamptz` | |
| `deleted_at` | `timestamptz` | nullable — soft delete |

`UNIQUE (condominium_id, nombre) WHERE deleted_at IS NULL`. Sin nivel de catálogo compartido —
`condominium_id` directo y `NOT NULL` (decisión 3 de `PANORAMA.md` §4).

#### `billing_periods`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `condominium_id` | `uuid` | FK → `condominiums.id`, NOT NULL, CASCADE ON DELETE |
| `anio` | `integer` | NOT NULL |
| `mes` | `integer` | NOT NULL, CHECK entre 1 y 12 |
| `estado` | `text` | NOT NULL, DEFAULT `abierto`, CHECK IN (`abierto`, `facturado`, `cerrado`) |
| `created_by` / `updated_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE |
| `created_at` / `updated_at` | `timestamptz` | |
| `deleted_at` | `timestamptz` | nullable — soft delete |

`UNIQUE (condominium_id, anio, mes) WHERE deleted_at IS NULL`.

#### `billing_runs`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `billing_period_id` | `uuid` | FK → `billing_periods.id`, NOT NULL, CASCADE ON DELETE |
| `ejecutado_por` | `uuid` | FK → `users.id`, NOT NULL, RESTRICT ON DELETE — actor obligatorio (decisión 4) |
| `fecha` | `timestamptz` | NOT NULL |
| `estado` | `text` | NOT NULL, CHECK IN (`en_proceso`, `completado`, `fallido`) |
| `resumen` | `jsonb` | nullable — `{unidades_facturadas, unidades_omitidas, detalle_omitidas[]}` (decisión 8) |
| `updated_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE |
| `created_at` / `updated_at` | `timestamptz` | |
| `deleted_at` | `timestamptz` | nullable — soft delete |

`UNIQUE (billing_period_id) WHERE estado = 'completado' AND deleted_at IS NULL` — constraint de BD,
no solo verificación de aplicación (endurece R-COB-09, decisión 7).

#### `invoices`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `condominium_id` | `uuid` | FK → `condominiums.id`, NOT NULL, CASCADE ON DELETE |
| `property_id` | `uuid` | FK → `properties.id`, NOT NULL, CASCADE ON DELETE — cross-context (ADR-002) |
| `billing_period_id` | `uuid` | FK → `billing_periods.id`, NOT NULL, CASCADE ON DELETE |
| `billing_run_id` | `uuid` | FK → `billing_runs.id`, NOT NULL, CASCADE ON DELETE (decisión 7) |
| `numero` | `text` | NOT NULL |
| `fecha_emision` | `date` | NOT NULL |
| `fecha_vencimiento` | `date` | NOT NULL |
| `valor_total` | `numeric(15,2)` | NOT NULL |
| `saldo` | `numeric(15,2)` | NOT NULL |
| `created_by` / `updated_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE |
| `created_at` / `updated_at` | `timestamptz` | |
| `deleted_at` | `timestamptz` | nullable — soft delete |

`UNIQUE (condominium_id, numero)`. Índices: `property_id`, `(condominium_id, billing_period_id)`,
`fecha_vencimiento`. **`estado` no es columna** — derivado en lectura (R-COB-08): `pendiente` /
`parcial` / `pagada` / `vencida`, calculado en el backend al serializar (`COBRANZA-B04`).

#### `invoice_items`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `invoice_id` | `uuid` | FK → `invoices.id`, NOT NULL, CASCADE ON DELETE |
| `charge_concept_id` | `uuid` | FK → `charge_concepts.id`, NOT NULL, RESTRICT ON DELETE |
| `descripcion` | `text` | nullable |
| `valor` | `numeric(15,2)` | NOT NULL |
| `base_calculo` | `numeric(5,4)` | nullable — snapshot inmutable (R-COB-06) |
| `created_by` / `updated_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE |
| `created_at` / `updated_at` | `timestamptz` | |
| `deleted_at` | `timestamptz` | nullable — soft delete. Edición/eliminación solo si `metodo_calculo = manual` y sin `payment_allocations` (R-COB-24) |

Índice: `invoice_id`.

#### `payment_receipts`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `condominium_id` | `uuid` | FK → `condominiums.id`, NOT NULL, CASCADE ON DELETE |
| `property_id` | `uuid` | FK → `properties.id`, NOT NULL, CASCADE ON DELETE — cross-context (ADR-002) |
| `contact_id` | `uuid` | FK → `contacts.id`, NOT NULL, RESTRICT ON DELETE — cross-context, party nunca `user_id` (ADR-001 §3) |
| `valor` | `numeric(15,2)` | NOT NULL |
| `fecha` | `date` | NOT NULL |
| `medio` | `text` | NOT NULL, CHECK IN (`efectivo`, `banco`) — R-COB-15, sin `transaction_id` (eliminada, decisión 6) |
| `referencia` | `text` | nullable — dato sensible, no se loguea en texto plano (R-COB-26) |
| `soporte_url` | `text` | nullable |
| `created_by` | `uuid` | FK → `users.id`, NOT NULL, RESTRICT ON DELETE — quién registró (R-COB-13) |
| `updated_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE — quién anuló/corrigió |
| `created_at` / `updated_at` | `timestamptz` | |
| `deleted_at` | `timestamptz` | nullable — anulación (R-COB-13) |

#### `payment_allocations`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `payment_receipt_id` | `uuid` | FK → `payment_receipts.id`, NOT NULL, CASCADE ON DELETE |
| `invoice_id` | `uuid` | FK → `invoices.id`, NOT NULL, CASCADE ON DELETE |
| `valor_aplicado` | `numeric(15,2)` | NOT NULL — suma exacta al 100% del recibo (R-COB-23) |
| `created_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE |
| `created_at` / `updated_at` | `timestamptz` | |
| `deleted_at` | `timestamptz` | nullable — inmutable en la práctica (R-COB-14) |

Sin `updated_by` — a diferencia del resto de tablas, esta es inmutable por diseño. Índice:
`invoice_id`.

#### `peace_certificates`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `condominium_id` | `uuid` | FK → `condominiums.id`, NOT NULL, CASCADE ON DELETE |
| `property_id` | `uuid` | FK → `properties.id`, NOT NULL, CASCADE ON DELETE — cross-context (ADR-002) |
| `emitido_por` | `uuid` | FK → `users.id`, NOT NULL, RESTRICT ON DELETE — actor obligatorio (decisión 4) |
| `numero` | `text` | NOT NULL |
| `fecha` | `date` | NOT NULL |
| `vigente_hasta` | `date` | nullable — `NULL` = sin vencimiento definido |
| `pdf_url` | `text` | nullable — poblado sincrónicamente antes de responder (decisión 9) |
| `updated_by` | `uuid` | FK → `users.id`, nullable, SET NULL ON DELETE — revocación (R-COB-28) |
| `created_at` / `updated_at` | `timestamptz` | |
| `deleted_at` | `timestamptz` | nullable — revocado (R-COB-28, reutiliza soft-delete existente, sin campo de estado nuevo) |

`UNIQUE (condominium_id, numero)`.

### Permisos RBAC de COBRANZA (`CobranzaPermissionsSeeder`)

10 permisos nuevos (`cobranza.conceptos.ver`/`.gestionar`, `cobranza.periodos.ver`,
`cobranza.facturacion.ejecutar`, `cobranza.facturas.ver`/`.gestionar`, `pagos.registrar`/`.anular`,
`cobranza.paz_salvo.generar`/`.revocar`) — catálogo completo en `PANORAMA.md` §5.

`billing.ver` — **hallazgo real de esta sesión**: el `PANORAMA.md` de COBRANZA (§5) asumía que ya
existía, creado por `DASHBOARD`. En la práctica `DASHBOARD-B01/B02/B03` son los tres `proyecto: web`
— nunca hubo un bloque API que persistiera el permiso, solo se referenciaba client-side
(`QuickLinksWidget.tsx`). `CobranzaPermissionsSeeder` lo crea idempotentemente
(`firstOrCreate`) junto con los 10 nuevos, cerrando el gap real sin duplicar si algún proceso futuro
también lo siembra.
