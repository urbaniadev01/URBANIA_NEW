---
tipo: bloque
proyecto: api
feature: PROPIEDADES
id: PROPIEDADES-B01
proyectos: [api]
estado: done
depende_de: [API_BOOTSTRAP-B01]
contrato: null
verificacion_critica: false
actualizado: 2026-07-08
---

# PROPIEDADES-B01 — Migraciones, modelos y seeders de catálogos sistema

## Objetivo

Crear las 6 tablas fundacionales del dominio de propiedades (`condominiums`, `towers`,
`property_types`, `property_statuses`, `properties`, `property_coefficients`), sus modelos Eloquent
con traits estandarizados, y los seeders de catálogos del sistema. Este bloque no expone endpoints ni
contiene lógica de negocio — solo la estructura de datos sobre la que se construye el resto de la
feature.

## Alcance

- **Incluye:**
  - 6 migraciones con columnas, FKs, índices únicos y `down()` reversible.
  - `created_by`/`updated_by` (UUID nullable, FK `→ users.id`) en las 6 tablas — convención de
    auditoría fijada en `shared/DATA_MODEL.md` §1-bis (nullable porque los catálogos de sistema
    sembrados por seeders no tienen autor humano).
  - CHECK constraint en `property_coefficients.tipo` restringiendo el valor al set cerrado
    (`copropiedad`, `parqueadero`, `deposito`, `mantenimiento`) — defensa en profundidad a nivel de
    BD, ver PANORAMA R-06-bis. La validación con `code` de error de API (`COEFFICIENT_INVALID_TYPE`)
    la implementa `PROPIEDADES-B05`; este bloque solo garantiza que la BD nunca acepte un valor fuera
    del set, sin importar la vía de escritura.
  - 6 modelos Eloquent con traits: `HasUuidV7`, `SoftDeletes`, `BelongsToOrganization` (donde
    aplique).
  - Relaciones Eloquent: `Condominium → towers`, `Condominium → properties`, `Tower → condominium`,
    `Tower → properties`, `Property → condominium`, `Property → tower`, `Property → type`,
    `Property → status`, `Property → coefficients`, `PropertyCoefficient → property`. Además,
    `createdBy`/`updatedBy` (`belongsTo User`) en los 6 modelos.
  - Seeders de catálogos sistema: `PropertyTypeSeeder` (5 tipos base) y `PropertyStatusSeeder` (5
    estados base), ambos con `organization_id = NULL` y `created_by`/`updated_by = NULL`.
  - Registro de seeders en `DatabaseSeeder` para que `db:seed` los ejecute.

- **No incluye (explícitamente fuera de este bloque):**
  - Endpoints HTTP, controllers, FormRequests, API Resources, middleware.
  - Lógica de negocio (validaciones de unicidad en capa de aplicación, reglas de eliminación con
    hijos, cierre de coeficientes).
  - Tests de feature/integración HTTP — solo tests de migraciones (reversibilidad) y de modelos
    (relaciones, traits, constraints).
  - Seeders de datos de prueba (factories).
  - Catálogos personalizados por tenant (los crea el usuario vía endpoints en B02).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | DB vacía | `php artisan migrate` | 6 tablas creadas con columnas, FKs e índices según PANORAMA §4 |
| 2 | Tablas creadas | `php artisan migrate:rollback` | 6 tablas eliminadas sin error |
| 3 | Tablas eliminadas | `php artisan migrate` | 6 tablas re-creadas sin error (prueba de `down()` reversible) |
| 4 | Tablas creadas | `php artisan db:seed --class=PropertyTypeSeeder` | 5 tipos con `organization_id = NULL` insertados |
| 5 | Tablas creadas | `php artisan db:seed --class=PropertyStatusSeeder` | 5 estados con `organization_id = NULL` insertados |
| 6 | Modelos registrados | `$condominium->towers()->create([...])` | Relación `hasMany` funciona, FK correcto |
| 7 | Modelos registrados | `$tower->condominium()->first()` | Relación `belongsTo` funciona |
| 8 | Modelos registrados | `$condominium->properties()->create([...])` | Relación `hasMany` funciona |
| 9 | Modelos registrados | `$property->type()->first()` | Relación `belongsTo` a `property_types` funciona |
| 10 | Modelos registrados | `$property->coefficients()->create([...])` | Relación `hasMany` funciona |
| 11 | Modelos registrados | Crear torre con `condominium_id` inexistente | Error de FK — integridad referencial |
| 12 | Modelos registrados | Crear propiedad con mismo `codigo` + `condominium_id` | Error de unicidad (constraint BD) |
| 13 | Modelos registrados | `$condominium->delete()` | Soft delete: `deleted_at` se llena, registro no aparece en queries por defecto |
| 14 | Modelos registrados | `$condominium->forceDelete()` | Registro eliminado físicamente |
| 15 | Modelos registrados | `$property->id` en modelo nuevo | UUID v7 generado automáticamente |
| 16 | Tablas creadas | Insertar `property_coefficients` con `tipo = 'jardin'` (fuera del set cerrado) directo a BD | Error de CHECK constraint — la BD rechaza el insert (R-06-bis) |
| 17 | Tablas creadas | Insertar `property_coefficients` con `tipo` en el set cerrado (`copropiedad`, `parqueadero`, `deposito`, `mantenimiento`) | Insert exitoso para los 4 valores |
| 18 | Seeders ejecutados | `PropertyType::first()->created_by` | `NULL` — catálogo de sistema sin autor humano |
| 19 | Modelos registrados | `$condominium->createdBy` con `created_by` seteado a un `user_id` válido | Relación `belongsTo User` funciona |

## Nota de alcance

> El CHECK constraint del criterio 16 es la única validación de negocio que vive en este bloque
> (excepción a "no incluye lógica de negocio" de más arriba): es una restricción estructural de BD,
> no una regla de aplicación — coherente con cómo ya se tratan las demás constraints de integridad
> (FKs, unicidad) en este mismo bloque.

## Contrato

Este bloque no produce ni consume contrato — es puramente estructural.

## Definition of Done

- [ ] `composer ci` ejecutado — salida completa pegada.
- [ ] Migraciones con `down()` reversible confirmado: `migrate` → `migrate:rollback` → `migrate` sin
      error — salida pegada.
- [ ] Seeders ejecutados (`db:seed --class=...`) — salida pegada confirmando inserción de 5 + 5
      registros.
- [ ] Tests que cubren los criterios 6–19 (relaciones, constraints, UUID v7, soft delete, CHECK de
      `tipo`, `created_by`/`updated_by` nullable y relación `belongsTo User`) — todos pasando, salida
      completa pegada.
- [ ] `api/API_DATABASE.md` actualizado con las 6 tablas nuevas (esquema real documentado, incluyendo
      `created_by`/`updated_by` y el CHECK constraint de `tipo`).

## Evidencia

### CI (`composer ci`)
| Paso | Resultado |
|---|---|
| Lint (Pint) | ✅ PASS — 167 files |
| Type-check (PHPStan) | ✅ No errors |
| Tests (Pest) | ✅ 127 passed, 438 assertions |

### Migraciones reversibles
| Comando | Resultado |
|---|---|
| `php artisan migrate:fresh --force` | ✅ 18 migrations created |
| `php artisan migrate:rollback --force` | ✅ 18 migrations reverted |
| `php artisan migrate --force` | ✅ 18 migrations re-executed |

### Seeders
| Comando | Resultado |
|---|---|
| `php artisan db:seed --class=PropertyTypeSeeder --force` | ✅ 5 tipos insertados |
| `php artisan db:seed --class=PropertyStatusSeeder --force` | ✅ 5 estados insertados |

### Fixes aplicados durante CI
- `HasUuidV7`: agregado `initializeHasUuidV7()` con `$incrementing = false` + `$keyType = 'string'` (los modelos usaban IDs numéricos en PostgreSQL)
- `tests/Pest.php`: agregado `'Unit'` a `uses(TestCase::class)` (los tests de Unit no tenían acceso al container)
- `PropertiesModelTest.php`: renombrada `createOrganization()` → `createPropertiesTestOrg()` (conflicto con RegisterTest.php) + agregado `use function Pest\Laravel\artisan`

## Notas

> Las reglas de negocio documentadas en PANORAMA §5 (R-01 a R-11) se implementan en los bloques de
> endpoints (B02-B05), no aquí — con dos excepciones estructurales que sí viven en este bloque:
> R-06-bis (CHECK constraint de `tipo` de coeficiente) y R-11 (columnas `created_by`/`updated_by`,
> sin lógica de quién las setea — eso es de B02-B05). Este bloque garantiza que las constraints de
> integridad a nivel BD (FKs, unicidad, NOT NULL, CHECK) estén correctas.
