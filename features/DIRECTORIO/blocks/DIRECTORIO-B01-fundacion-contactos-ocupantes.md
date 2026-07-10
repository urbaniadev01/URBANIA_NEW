---
tipo: bloque
proyecto: api
feature: DIRECTORIO
id: DIRECTORIO-B01
proyectos: [api]
estado: ready
depende_de: [AUTH-B01, PROPIEDADES-B01]
contrato: null
verificacion_critica: true
actualizado: 2026-07-08
---

# DIRECTORIO-B01 — Fundación: corrección de `contacts`, tipos de ocupante y asignación de ocupantes

## Objetivo

Corregir `contacts` para que cumpla su propio diseño aprobado (`user_id` nullable + `organization_id`
propio), y crear las 2 tablas fundacionales de esta feature (`occupant_types`, `property_occupants`)
con sus modelos y seeders de catálogo. Este bloque no expone endpoints ni contiene lógica de negocio
de CRUD — solo la estructura de datos sobre la que se construye el resto de la feature (mismo rol que
tuvo `PROPIEDADES-B01` para esa feature).

## Alcance

- **Incluye:**
  - **Migración correctiva sobre `contacts`** (tabla existente, de `AUTH-B01`, ya `SHIPPED`):
    - Agregar `organization_id` (UUID, FK `→ organizations.id`), inicialmente nullable.
    - Backfill: `UPDATE contacts SET organization_id = (SELECT organization_id FROM users WHERE
      users.id = contacts.user_id)` para todas las filas existentes (hoy todas tienen `user_id`, así
      que el backfill cubre el 100% de los datos actuales).
    - Alterar `organization_id` a `NOT NULL` después del backfill.
    - Alterar `user_id` a nullable (`->nullable()->change()`).
    - Eliminar el índice único simple de `user_id` y reemplazarlo por un índice único parcial
      (`CREATE UNIQUE INDEX ... ON contacts (user_id) WHERE user_id IS NOT NULL` — vía
      `DB::statement`, Laravel no expone unique parcial en el schema builder).
    - Agregar `created_by`/`updated_by` (UUID nullable, FK `→ users.id`) — convención de
      `shared/DATA_MODEL.md` §1-bis.
  - Migración nueva `occupant_types` (mismo patrón que `property_types` de `PROPIEDADES-B01`:
    `organization_id` nullable, `nombre`, `descripcion`, `created_by`/`updated_by`, soft delete).
  - Migración nueva `property_occupants`: `contact_id` FK, `property_id` FK, `occupant_type_id` FK,
    `es_principal` (bool, default `false`), `created_by`/`updated_by`, soft delete.
    - `UNIQUE(contact_id, property_id, occupant_type_id) WHERE deleted_at IS NULL` (R-DIR-11).
    - `UNIQUE(property_id, occupant_type_id) WHERE es_principal = true AND deleted_at IS NULL`
      (R-DIR-07) — índice único parcial, vía `DB::statement`.
  - Actualizar `EloquentContact`: agregar `organization_id`, `created_by`, `updated_by` a
    `$fillable`; relaciones `organization()`, `createdBy()`, `updatedBy()` (`belongsTo`).
  - **Parche obligatorio en `RegisterUserUseCase` (AUTH-B01, ya `done`):** hoy crea el `Contact` sin
    `organization_id` (`src/Auth/Application/UseCases/RegisterUserUseCase.php`, línea del
    `$this->contactRepository->create([...])`) — una vez `organization_id` sea `NOT NULL`, ese insert
    rompe el registro por invitación en producción si no se corrige. Agregar `'organization_id' =>
    $invitation->organization_id` al array de creación. Es un cambio de una línea en código ya
    `SHIPPED`, justificado exclusivamente por esta migración — no se aprovecha para tocar nada más
    de ese flujo.
  - Modelos nuevos `EloquentOccupantType`, `EloquentPropertyOccupant` con traits estandarizados
    (`HasUuidV7`, `SoftDeletes`) y relaciones: `Contact → occupations` (hasMany
    `property_occupants`), `Property → occupants` (hasMany, en el modelo `Property` de
    `PROPIEDADES` — este bloque agrega la relación inversa ahí), `OccupantType → occupations`.
  - Seeder `OccupantTypeSeeder`: 4 tipos base (`propietario`, `residente`, `arrendatario`,
    `familiar`), `organization_id = NULL`, `created_by`/`updated_by = NULL`. Registrado en
    `DatabaseSeeder`.
  - **Reemplazar el guard clause de `PROPIEDADES-B04`** (regla "no eliminar unidad con ocupantes
    activos") por la verificación real contra `property_occupants` — **solo si `PROPIEDADES-B04` ya
    está `done` a la fecha en que se ejecuta este bloque.** Si `PROPIEDADES-B04` sigue en `ready`/
    `backlog`, dejar una nota en su tarjeta para que su propia implementación consulte
    `property_occupants` directamente en vez de crear el guard clause temporal (ya no hace falta:
    la tabla existirá antes de que ese bloque se ejecute).

- **No incluye (explícitamente fuera de este bloque):**
  - Endpoints HTTP, controllers, FormRequests, API Resources, middleware.
  - Lógica de negocio de CRUD (validaciones de unicidad en capa de aplicación más allá de las
    constraints de BD, reglas de autorización, self-service `/me/contact`).
  - Tests de feature/integración HTTP — solo tests de migraciones (reversibilidad, backfill) y de
    modelos (relaciones, traits, constraints).
  - Catálogos personalizados de `occupant_types` por tenant (los crea el usuario vía endpoints en
    `DIRECTORIO-B02`).
  - Temporalidad de ocupación (`vigente_desde`/`vigente_hasta`) — deuda técnica explícita, ver
    PANORAMA R-DIR-05.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | `contacts` con filas existentes (de tests/seeds de AUTH), todas con `user_id` no nulo | Ejecutar la migración correctiva | Todas las filas quedan con `organization_id` igual al `organization_id` de su `user_id` — 0 filas con `organization_id` NULL |
| 2 | Migración aplicada | `php artisan migrate:rollback` sobre esta migración | Revierte sin error: quita `organization_id`/`created_by`/`updated_by`, restaura `user_id` NOT NULL + índice único simple |
| 3 | Migración revertida | `php artisan migrate` | Re-aplica sin error (prueba de `down()` reversible) |
| 4 | Tabla corregida | `Contact::create(['organization_id' => $orgId, 'user_id' => null, 'nombre' => 'Propietario Ausente', 'email' => '...'])` | Insert exitoso — contacto sin `user_id` (R-DIR-02) |
| 5 | Dos contactos sin `user_id` en la misma organización | Crear ambos | Ambos insertan sin conflicto — el índice único de `user_id` es parcial, `NULL` no colisiona consigo mismo |
| 6 | Contacto con `user_id` ya usado por otro contacto | Intentar crear un segundo contacto con el mismo `user_id` | Error de unicidad (constraint BD) — el índice parcial sigue exigiendo unicidad cuando `user_id IS NOT NULL` |
| 7 | Tablas creadas | `php artisan db:seed --class=OccupantTypeSeeder` | 4 tipos (`propietario`, `residente`, `arrendatario`, `familiar`) con `organization_id = NULL` insertados |
| 8 | Modelos registrados | `$contact->occupations()->create(['property_id' => ..., 'occupant_type_id' => ...])` | Relación `hasMany` funciona, FKs correctas |
| 9 | Modelos registrados | `$property->occupants()->create([...])` (relación inversa agregada al modelo `Property` existente de PROPIEDADES) | Relación `hasMany` funciona |
| 10 | `property_occupants` con un registro `(contact_id=A, property_id=B, occupant_type_id=C)` | Crear otro registro idéntico (mismo contact+property+type) | Error de unicidad (R-DIR-11) |
| 11 | Mismo `contact_id` y `property_id`, `occupant_type_id` distinto | Crear el segundo registro | Insert exitoso — un contacto puede tener varios tipos en la misma unidad |
| 12 | `property_occupants` con un registro `es_principal = true` para `(property_id=B, occupant_type_id=C)` | Crear otro registro con `es_principal = true` para el mismo `property_id` + `occupant_type_id` | Error de unicidad (R-DIR-07) — la aplicación (B04) es responsable de desmarcar el anterior antes de marcar uno nuevo; a nivel BD, dos `true` simultáneos para la misma combinación deben fallar |
| 13 | Registro con `contact_id`/`property_id`/`occupant_type_id` inexistente | Crear `property_occupants` | Error de FK — integridad referencial |
| 14 | `$propertyOccupant->delete()` | Soft delete | `deleted_at` se llena, no aparece en queries por defecto; el `UNIQUE` parcial de R-DIR-11 permite recrear la misma combinación después (la condición `WHERE deleted_at IS NULL` lo habilita) |
| 15 | Seeder ejecutado | `OccupantType::first()->created_by` | `NULL` — catálogo de sistema sin autor humano |
| 16 | `PROPIEDADES-B04` está `done` a la fecha de este bloque | Revisar su implementación del guard clause | Guard clause reemplazado por consulta real a `property_occupants`; `@todo` eliminado; tarjeta de `PROPIEDADES-B04` actualizada con nota de qué cambió |
| 17 | `PROPIEDADES-B04` sigue `ready`/`backlog` a la fecha de este bloque | — | Se deja una nota explícita en la tarjeta de `PROPIEDADES-B04` indicando que `property_occupants` ya existe y que su implementación debe consultarla directamente, sin crear el guard clause |
| 18 | Migración aplicada + parche de `RegisterUserUseCase` | Ejecutar el test de feature existente de `AUTH-B01` (registro por invitación) sin modificarlo | Sigue pasando — el `Contact` creado durante el registro tiene `organization_id` igual al de la invitación, sin romper el flujo ya `SHIPPED` |

## Nota de alcance

> Los índices únicos parciales (criterios 5-6, 12) y la corrección de `contacts` son la única lógica
> "de negocio" que vive en este bloque — son restricciones estructurales de BD, no reglas de
> aplicación, coherente con cómo `PROPIEDADES-B01` trató su propio CHECK constraint de `tipo` de
> coeficiente.

## Contrato

Este bloque no produce ni consume contrato — es puramente estructural.

## Definition of Done

- [ ] `composer ci` ejecutado — salida completa pegada.
- [ ] Migraciones con `down()` reversible confirmado: `migrate` → `migrate:rollback` → `migrate` sin
      error — salida pegada, incluyendo la migración correctiva de `contacts`.
- [ ] Backfill verificado: query real mostrando 0 filas de `contacts` con `organization_id NULL`
      después de la migración — salida pegada.
- [ ] Seeder ejecutado (`db:seed --class=OccupantTypeSeeder`) — salida pegada confirmando 4 registros.
- [ ] Tests que cubren los criterios 4-15 (contacto sin `user_id`, índices parciales, relaciones,
      constraints, soft delete) — todos pasando, salida completa pegada.
- [ ] Test de regresión del criterio 18 (registro por invitación de `AUTH-B01` sigue funcionando tras
      el parche de `RegisterUserUseCase`) — salida pegada. Este es el criterio más importante del
      bloque: si se omite, se corre el riesgo de shippear una migración que rompe el login/registro
      en producción.
- [ ] Resolución explícita del criterio 16 **o** 17 (según el estado real de `PROPIEDADES-B04` al
      momento de ejecutar este bloque) — documentada en la sección de Notas de esta tarjeta y en la
      tarjeta de `PROPIEDADES-B04`.
- [ ] `api/API_DATABASE.md` actualizado: `contacts` (columnas nuevas + cambio de nullability),
      `occupant_types`, `property_occupants` documentadas con esquema real.
- [ ] `_state/BOARD.md` — actualizar/eliminar la nota de seguimiento sobre la dependencia inversa de
      `PROPIEDADES-B04` (ya no es una dependencia pendiente una vez este bloque está `done`).
- [ ] Dado `verificacion_critica: true` (corrige una tabla `SHIPPED` con backfill de datos
      existentes): `verify-council` obligatorio antes de que el verifier pueda marcar `done` — ver
      `_system/05_DEFINITION_OF_DONE.md` §6.

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> Las reglas de negocio de CRUD documentadas en PANORAMA §5 (R-DIR-01 a R-DIR-11) se implementan en
> los bloques de endpoints (B02-B04), no aquí — con las excepciones estructurales de R-DIR-07 y
> R-DIR-11 (índices únicos parciales) y R-DIR-10 (columnas `created_by`/`updated_by`), que sí viven
> en este bloque por ser constraints de BD, igual que en `PROPIEDADES-B01`.
>
> Este bloque toca una tabla ya `SHIPPED` (`contacts`, de `AUTH-B01`) con un backfill de datos reales,
> no solo esquema nuevo — de ahí `verificacion_critica: true` (ver criterios de
> `_system/05_DEFINITION_OF_DONE.md` §6: "Migraciones que modifican datos existentes").
