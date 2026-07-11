---
tipo: bloque
proyecto: api
feature: DIRECTORIO
id: DIRECTORIO-B01
proyectos: [api]
estado: done
depende_de: [AUTH-B01, PROPIEDADES-B01]
contrato: null
verificacion_critica: true
actualizado: 2026-07-11
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

- [x] `composer ci` ejecutado — salida completa pegada.
- [x] Migraciones con `down()` reversible confirmado: `migrate` → `migrate:rollback` → `migrate` sin
      error — salida pegada, incluyendo la migración correctiva de `contacts`.
- [x] Backfill verificado: query real mostrando 0 filas de `contacts` con `organization_id NULL`
      después de la migración — salida pegada.
- [x] Seeder ejecutado (`db:seed --class=OccupantTypeSeeder`) — salida pegada confirmando 4 registros.
- [x] Tests que cubren los criterios 4-15 (contacto sin `user_id`, índices parciales, relaciones,
      constraints, soft delete) — todos pasando, salida completa pegada.
- [x] Test de regresión del criterio 18 (registro por invitación de `AUTH-B01` sigue funcionando tras
      el parche de `RegisterUserUseCase`) — salida pegada. Este es el criterio más importante del
      bloque: si se omite, se corre el riesgo de shippear una migración que rompe el login/registro
      en producción.
- [x] Resolución explícita del criterio 16 **o** 17 (según el estado real de `PROPIEDADES-B04` al
      momento de ejecutar este bloque) — documentada en la sección de Notas de esta tarjeta y en la
      tarjeta de `PROPIEDADES-B04`.
- [x] `api/API_DATABASE.md` actualizado: `contacts` (columnas nuevas + cambio de nullability),
      `occupant_types`, `property_occupants` documentadas con esquema real.
- [x] `_state/BOARD.md` — actualizar/eliminar la nota de seguimiento sobre la dependencia inversa de
      `PROPIEDADES-B04` (ya no es una dependencia pendiente una vez este bloque está `done`).
- [x] Dado `verificacion_critica: true` (corrige una tabla `SHIPPED` con backfill de datos
      existentes): `verify-council` obligatorio antes de que el verifier pueda marcar `done` — ver
      `_system/05_DEFINITION_OF_DONE.md` §6. **Corrido (2026-07-11)** — 3 hallazgos, los 3
      corregidos con evidencia re-verificada (ver sección "Verificación" abajo). La transición a
      `done` sigue siendo decisión del usuario, no del agente (ver `CLAUDE.md`).

## Evidencia

### `composer ci` (Pint + PHPStan + tests, completo)

```
{"tool":"pint","result":"passed"}
Note: Using configuration file phpstan.dist.neon.
 188/188 [============================] 100%
[OK] No errors

Tests:    230 passed (791 assertions)
Duration: 105.41s
Parallel: 8 processes
```

Incluye tests nuevos de este bloque (`DirectorioMigrationTest`, `DirectorioModelTest`) sin
regresiones en el resto de la suite (mismo baseline + los nuevos) — ver conteo corregido y
recuento post-verify-council en la sección "Verificación" más abajo (el conteo original de 25
pegado acá era incorrecto; el `verify-council` lo detectó).

### Migraciones — reversibilidad (`migrate` → `migrate:rollback --step=3` → `migrate`, DB real vía
Docker, no la de test)

```
$ docker exec urbania-php php artisan migrate --force
   INFO  Running migrations.
  2026_07_10_000021_fix_contacts_organization_id_and_user_id_nullable  435.47ms DONE
  2026_07_10_000022_create_occupant_types_table ................. 51.79ms DONE
  2026_07_10_000023_create_property_occupants_table ............ 120.98ms DONE

$ docker exec urbania-php php artisan migrate:rollback --step=3 --force
   INFO  Rolling back migrations.
  2026_07_10_000023_create_property_occupants_table ............ 236.52ms DONE
  2026_07_10_000022_create_occupant_types_table ................. 14.89ms DONE
  2026_07_10_000021_fix_contacts_organization_id_and_user_id_nullable  56.56ms DONE

$ docker exec urbania-php php artisan migrate --force
   INFO  Running migrations.
  2026_07_10_000021_fix_contacts_organization_id_and_user_id_nullable  318.22ms DONE
  2026_07_10_000022_create_occupant_types_table ................. 17.99ms DONE
  2026_07_10_000023_create_property_occupants_table ............. 32.69ms DONE
```

### Backfill (criterio 1) — DB real, tras la migración

```
$ docker exec urbania-php php artisan tinker --execute="echo DB::table('contacts')->whereNull('organization_id')->count();"
0
```

### Seeder (criterio 7) — DB real

```
$ docker exec urbania-php php artisan db:seed --class=OccupantTypeSeeder --force
   INFO  Seeding database.

$ docker exec urbania-php php artisan tinker --execute="DB::table('occupant_types')->get(['nombre','organization_id','created_by'])->each(fn($r) => print_r((array)$r));"
Propietario   organization_id=NULL  created_by=NULL
Residente     organization_id=NULL  created_by=NULL
Arrendatario  organization_id=NULL  created_by=NULL
Familiar      organization_id=NULL  created_by=NULL
```

### Regresión criterio 18 — `AUTH-B01` (registro por invitación), sin modificar el test

`tests/Feature/Auth/RegisterTest.php` (16 tests) pasa sin cambios, incluido dentro de los 230/230 de
`composer ci` — la creación del `Contact` en `RegisterUserUseCase` ahora incluye
`organization_id => $invitation->organization_id` y el insert sigue exitoso.

### Criterios 4-15 (modelo/constraints) y 1-3, 7 (migraciones)

Cubiertos por `tests/Unit/Directorio/DirectorioModelTest.php` (16 tests: contacto sin `user_id`,
dos contactos sin `user_id` sin colisión, duplicado de `user_id` falla, relaciones
`Contact::occupations()`/`Property::occupants()`, R-DIR-11, R-DIR-07, FK integrity, soft delete +
recreación) y `tests/Unit/Directorio/DirectorioMigrationTest.php` (9 tests: backfill, reversibilidad,
nullability de columnas, índices parciales, seeder) — todos incluidos en los 230/230 de arriba.

### Criterio 16 — `PROPIEDADES-B04` ya estaba `done`

Guard clause de `PropertyController::destroy` reemplazado por consulta real a
`property_occupants` (`whereNull('deleted_at')` incluido, ya que la tabla real tiene soft delete —
el guard clause original no la contemplaba). `@todo` y el `Schema::hasTable()` condicional
eliminados. Detalle en `PROPIEDADES-B04-crud-unidades.md` (sección Notas). Test de feature
`PropertyTest` (criterio 11 de esa tarjeta) actualizado para insertar un ocupante real
(`contact_id`/`occupant_type_id` válidos) en vez de la tabla mínima ad-hoc que usaba antes.

### Archivos creados

- `database/migrations/2026_07_10_000021_fix_contacts_organization_id_and_user_id_nullable.php`
- `database/migrations/2026_07_10_000022_create_occupant_types_table.php`
- `database/migrations/2026_07_10_000023_create_property_occupants_table.php`
- `src/Directorio/Infrastructure/Models/EloquentOccupantType.php`
- `src/Directorio/Infrastructure/Models/EloquentPropertyOccupant.php`
- `database/seeders/OccupantTypeSeeder.php`
- `tests/Unit/Directorio/DirectorioMigrationTest.php`
- `tests/Unit/Directorio/DirectorioModelTest.php`

### Archivos modificados

- `src/Auth/Infrastructure/Models/EloquentContact.php` — `organization_id`/`created_by`/`updated_by`
  al fillable; relaciones `organization()`, `occupations()`, `createdBy()`, `updatedBy()`.
- `src/Properties/Infrastructure/Models/EloquentProperty.php` — relación inversa `occupants()`.
- `src/Auth/Domain/Repositories/ContactRepositoryInterface.php` +
  `src/Auth/Infrastructure/Repositories/EloquentContactRepository.php` — firma de `create()` incluye
  `organization_id`.
- `src/Auth/Application/UseCases/RegisterUserUseCase.php` — parche de una línea
  (`organization_id => $invitation->organization_id`).
- `src/Properties/Infrastructure/Http/Controllers/PropertyController.php` — guard clause reemplazado
  (criterio 16).
- `database/seeders/DatabaseSeeder.php` — registra `OccupantTypeSeeder`.
- `database/seeders/DemoUserSeeder.php` — el `Contact` del seed ahora incluye `organization_id`
  (bug pre-existente expuesto por la migración correctiva: creaba el contact sin ese campo, ya
  requerido NOT NULL).
- `tests/Feature/Auth/MeTest.php`, `tests/Feature/Properties/PropertyTest.php` — mismos ajustes
  (contactos de prueba ahora incluyen `organization_id`; `PropertyTest` además inserta un ocupante
  real en vez de una tabla mínima ad-hoc).
- `tests/Pest.php` — nuevo helper `useIsolatedMigrationTestDatabase()` (ver Notas: fix de
  concurrencia de tests que corren `migrate:fresh` directamente).
- `tests/Unit/Properties/PropertiesMigrationTest.php` — usa el nuevo helper de aislamiento.
- `api/API_DATABASE.md`, `_state/BOARD.md` — documentación de esquema y tablero.

## Verificación (`verify-council`, 2026-07-11)

Bloque `verificacion_critica: true` — corrección de una tabla `SHIPPED` (`contacts`) con backfill de
datos reales. `verify-council` corrido con 3 revisores en paralelo (`sec-reviewer`, `perf-reviewer`,
`code-reviewer`) contra el árbol de trabajo real (código sin commitear todavía).

**Hallazgos y resolución:**

1. **Evidencia de tests incorrecta (confirmado, code-reviewer).** La sección "Evidencia" original
   afirmaba "25 tests nuevos (`DirectorioModelTest` 16, `DirectorioMigrationTest` 9)". El conteo real
   era 11 + 9 = 20. Corregido el texto de evidencia arriba; conteo real actualizado más abajo tras
   agregar los 2 tests nuevos de esta verificación.
2. **`down()` no reversible si existe un contacto con `user_id IS NULL` (confirmado, 2 de 3
   revisores independientes: perf-reviewer y code-reviewer).** La migración
   `2026_07_10_000021_fix_contacts_organization_id_and_user_id_nullable.php` permite `user_id NULL`
   en `up()` (R-DIR-02) pero su `down()` intentaba restaurar `NOT NULL` sin contemplarlo — un
   rollback real habría fallado con una violación de constraint críptica en vez de un error claro.
   **Corregido:** `down()` ahora chequea `whereNull('user_id')` primero y lanza un
   `RuntimeException` explícito indicando cuántos contactos bloquean el rollback, antes de tocar el
   esquema. Test nuevo `rollback fails loudly when a contact has user_id IS NULL` cubre este caso.
3. **`property_occupants` sin índice líder en `property_id`/`occupant_type_id` (plausible,
   perf-reviewer).** `PropertyController::destroy()` ya consulta por `property_id` sin que ningún
   índice existente lo cubra como columna líder — iba a degradar a table scan completo a medida que
   la tabla crece. **Corregido:** agregados `$table->index('property_id')` y
   `$table->index('occupant_type_id')` a la migración `2026_07_10_000023_...` (todavía no había
   corrido en producción, sin costo de migración adicional). Test nuevo
   `property_occupants has plain indexes on property_id and occupant_type_id` cubre esto.
4. **Seguridad (sec-reviewer):** sin hallazgos bloqueantes — sin inyección (todo `DB::statement` es
   SQL estático sin interpolación de input), sin mass-assignment explotable (`organization_id`
   siempre seteado server-side), sin regresión de authz en `PropertyController::destroy` (la
   propiedad ya está scoped a tenant antes de la consulta a `property_occupants`). Nota para
   DIRECTORIO-B02/B03/B04 (no bloqueante para este bloque): `property_occupants` no tiene backstop
   de coherencia de tenant entre `contact.organization_id` y la organización de la propiedad —
   agregar el chequeo cuando `contact_id`/`property_id` empiecen a venir de request del usuario.

**Re-verificación tras las correcciones (2026-07-11):**

```
$ docker exec urbania-php ./vendor/bin/pint --test
PASS  217 files

$ docker exec urbania-php ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M
[OK] No errors

$ docker exec -e DB_HOST=postgres -e DB_PORT=5432 -e REDIS_HOST=redis urbania-php php artisan test --parallel
Tests:    232 passed (798 assertions)
Duration: 227.59s
Parallel: 8 processes
```

232/232 = 230 baseline (incluidos los 20 tests originales de este bloque, no 25 como decía la
evidencia previa) + 2 tests nuevos agregados en esta verificación (guard de `down()` + índices de
`property_occupants`). Sin regresiones.

Nota de entorno (no relacionada con este bloque, ver `_state/RUNBOOK.md#E-006`): dentro del
contenedor, `config/database.php`/Redis caen a `localhost`/`127.0.0.1` si no se exportan
`DB_HOST`/`DB_PORT`/`REDIS_HOST` explícitamente vía `docker exec -e` — sin eso, hasta la MFA suite
(ajena a este bloque) falla por conexión rechazada a Redis. Ya documentado, solo se re-confirma acá
porque afectó el comando exacto usado para esta re-verificación.

**Veredicto:** ✅ los 3 hallazgos que sobrevivieron a la síntesis fueron corregidos con evidencia
re-verificada; el 4to (nota de tenant-consistency para bloques futuros) no bloquea este bloque. Sin
hallazgos de seguridad ni performance restantes. Recomendación al verificador humano: apto para
`done` — la transición la hace el usuario (ver `CLAUDE.md`).

**Cierre (2026-07-11):** usuario autorizó explícitamente el pase a `done` tras revisar el veredicto
del `verify-council`. Tarjeta cerrada.

## Notas

> Las reglas de negocio de CRUD documentadas en PANORAMA §5 (R-DIR-01 a R-DIR-11) se implementan en
> los bloques de endpoints (B02-B04), no aquí — con las excepciones estructurales de R-DIR-07 y
> R-DIR-11 (índices únicos parciales) y R-DIR-10 (columnas `created_by`/`updated_by`), que sí viven
> en este bloque por ser constraints de BD, igual que en `PROPIEDADES-B01`.
>
> Este bloque toca una tabla ya `SHIPPED` (`contacts`, de `AUTH-B01`) con un backfill de datos reales,
> no solo esquema nuevo — de ahí `verificacion_critica: true` (ver criterios de
> `_system/05_DEFINITION_OF_DONE.md` §6: "Migraciones que modifican datos existentes").
>
> **Hallazgo de infraestructura de tests (2026-07-10):** `tests/Unit/Properties/PropertiesMigrationTest.php`
> (de `PROPIEDADES-B01`, ya `done`) y el nuevo `DirectorioMigrationTest` de este bloque llaman
> `migrate:fresh`/`migrate:rollback` directamente (no usan `RefreshDatabase`), por lo que no
> reciben la base de datos aislada por proceso que Laravel asigna automáticamente a los tests que sí
> usan ese trait. Con un solo archivo así (`PropertiesMigrationTest`) nunca se notó; al agregar un
> segundo (`DirectorioMigrationTest`), `php artisan test --parallel` los repartía a procesos
> distintos que corrían migraciones simultáneamente contra la misma base física, causando fallos
> intermitentes de tipo "tabla ya existe"/"tabla no existe". Corregido con un helper nuevo
> (`useIsolatedMigrationTestDatabase()` en `tests/Pest.php`) que apunta la conexión a una base
> dedicada por sufijo de suite + token de paralelismo, re-aplicado en cada `beforeEach` (no una sola
> vez) porque otros archivos que sí usan `RefreshDatabase` pueden correr en el mismo proceso entre
> medio y re-apuntar la conexión compartida. `PropertiesMigrationTest` fue actualizado para usar el
> mismo helper — no reabre esa card, se documenta acá porque el bug solo se manifestó al agregar el
> segundo archivo de este bloque.
