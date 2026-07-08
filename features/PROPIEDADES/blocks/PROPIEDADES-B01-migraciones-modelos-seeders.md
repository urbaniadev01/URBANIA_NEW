---
tipo: bloque
proyecto: api
feature: PROPIEDADES
id: PROPIEDADES-B01
proyectos: [api]
estado: ready
depende_de: [API_BOOTSTRAP-B01]
contrato: null
verificacion_critica: false
actualizado: 2026-07-06
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
  - 6 modelos Eloquent con traits: `HasUuidV7`, `SoftDeletes`, `BelongsToOrganization` (donde
    aplique).
  - Relaciones Eloquent: `Condominium → towers`, `Condominium → properties`, `Tower → condominium`,
    `Tower → properties`, `Property → condominium`, `Property → tower`, `Property → type`,
    `Property → status`, `Property → coefficients`, `PropertyCoefficient → property`.
  - Seeders de catálogos sistema: `PropertyTypeSeeder` (5 tipos base) y `PropertyStatusSeeder` (5
    estados base), ambos con `organization_id = NULL`.
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

## Contrato

Este bloque no produce ni consume contrato — es puramente estructural.

## Definition of Done

- [ ] `composer ci` ejecutado — salida completa pegada.
- [ ] Migraciones con `down()` reversible confirmado: `migrate` → `migrate:rollback` → `migrate` sin
      error — salida pegada.
- [ ] Seeders ejecutados (`db:seed --class=...`) — salida pegada confirmando inserción de 5 + 5
      registros.
- [ ] Tests que cubren los criterios 6–15 (relaciones, constraints, UUID v7, soft delete) — todos
      pasando, salida completa pegada.
- [ ] `api/API_DATABASE.md` actualizado con las 6 tablas nuevas (esquema real documentado).

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> Las reglas de negocio documentadas en PANORAMA §5 (R-01 a R-10) se implementan en los bloques de
> endpoints (B02-B05), no aquí. Este bloque solo garantiza que las constraints de integridad a nivel
> BD (FKs, unicidad, NOT NULL) estén correctas.
