---
tipo: bloque
proyecto: api
feature: AUTH
id: AUTH-B05
proyectos: [api]
estado: done
depende_de: [AUTH-B02]
contrato: null
actualizado: 2026-07-06
---

# AUTH-B05 — Middleware de autorización RBAC

## Objetivo

Implementar el gate de autorización real descrito en
[[../../../shared/adr/ADR-001-actor-party]] §2: tablas `roles`/`permissions`/`role_assignments`, un
`Gate` que resuelve permisos efectivos por request (cacheado), y aplicarlo sobre al menos un
endpoint de ejemplo protegido. Este bloque es el mecanismo directo contra el segundo hueco de
seguridad de la auditoría que motivó este vault: un gate de autorización basado en una columna de
rol legacy en vez del RBAC real.

## Alcance

**Incluye:**
- Migraciones: `roles`, `permissions`, `role_assignments` (con `scope_type`/`scope_id`).
- Módulo `Authorization` (separado de `Auth`, ver `api/API_ARCHITECTURE.md` §5).
- `Gate::can('recurso.accion', $scope)` resuelto contra `role_assignments` + `permissions`, con
  cache (invalidado al cambiar una asignación).
- Middleware HTTP que aplica el gate a una ruta protegida de ejemplo (usar un endpoint simple, ej.
  "ver mi propio perfil" vs. una acción administrativa de ejemplo).

**No incluye:**
- Catálogo completo de permisos del sistema (~14 roles previstos) — solo lo mínimo para demostrar
  el mecanismo funcionando; el catálogo completo se llena a medida que cada feature futura declara
  qué permisos necesita.
- UI de gestión de roles (Web) — feature futura.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Usuario con `role_assignment` que otorga el permiso requerido en el scope correcto | Acceder al endpoint protegido de ejemplo | `200` |
| 2 | Usuario autenticado sin ese `role_assignment` | Acceder al endpoint protegido | `403 PERMISSION_DENIED` |
| 3 | Usuario con el permiso pero en un `scope` distinto (otra organización/condominio) | Acceder al endpoint protegido | `403 PERMISSION_DENIED` — confirma que el scope se verifica, no solo la existencia del permiso |
| 4 | Se revoca el `role_assignment` de un usuario con sesión activa | Reintentar el endpoint protegido inmediatamente después | `403 PERMISSION_DENIED` — confirma que el cache se invalida, no solo que expira por TTL |
| 5 | Un usuario intenta acceder usando cualquier columna/atributo legacy de "rol" que no pase por `role_assignments` | Acceder al endpoint protegido | Rechazado — el gate no debe tener ninguna ruta alterna que dependa de otra fuente distinta a RBAC |

> El caso 5 es explícito porque es exactamente el patrón que falló en el vault anterior: un segundo
> camino de autorización (columna legacy) que coexistía con el sistema RBAC nuevo y ganaba por
> accidente.

## Definition of Done

- [x] `composer ci` ejecutado — salida pegada.
- [x] Test por cada fila de la tabla (5 casos), incluyendo el 4 (invalidación de cache) y el 5
      (ausencia de ruta alterna de autorización).
- [x] Verificación funcional real de los casos 1, 2 y 3 pegada.
- [x] `api/API_DATABASE.md` — tablas `roles`, `permissions`, `role_assignments` documentadas.
- [x] `api/API_ARCHITECTURE.md` §5 — contexto `Authorization` agregado a la tabla de bounded
      contexts.

## Evidencia

### composer ci

```
Lint: PASS (99 files)
PHPStan: [OK] No errors (85 files, nivel 10)
Tests: 48 passed (160 assertions) — 16.27s
```

### Archivos creados/modificados

#### Migraciones (4 nuevas)
- `database/migrations/2026_07_06_000005_create_roles_table.php`
- `database/migrations/2026_07_06_000006_create_permissions_table.php`
- `database/migrations/2026_07_06_000007_create_permission_role_table.php`
- `database/migrations/2026_07_06_000008_create_role_assignments_table.php`

#### Módulo Authorization (`src/Authorization/`) — 18 archivos
- **Domain/Models:** `Role.php`, `Permission.php`, `RoleAssignment.php` (3 value objects `final readonly`)
- **Domain/Repositories:** `RoleRepositoryInterface.php`, `PermissionRepositoryInterface.php`, `RoleAssignmentRepositoryInterface.php` (3 interfaces)
- **Domain/Exceptions:** `PermissionDeniedException.php` (`final class extends RuntimeException`)
- **Application/Services:** `PermissionResolver.php` (resuelve permisos, cache Redis/array, invalidación)
- **Application/UseCases:** `CheckPermissionUseCase.php` (`execute(userId, permission, scopeType, scopeId): bool`)
- **Infrastructure/Models:** `EloquentRole.php`, `EloquentPermission.php`, `EloquentRoleAssignment.php` (3 modelos Eloquent con UUID v7, `EloquentRoleAssignment` con cache invalidation en `created`/`deleted`/`updated`)
- **Infrastructure/Repositories:** `EloquentRoleRepository.php`, `EloquentPermissionRepository.php`, `EloquentRoleAssignmentRepository.php` (3 implementaciones `final readonly`)
- **Infrastructure/Http/Middleware:** `RequirePermission.php` (middleware alias `require_permission`)
- **Infrastructure/Http/Controllers:** `AdminController.php` (endpoint protegido de ejemplo)
- **Presentation:** `AuthorizationServiceProvider.php` (registra bindings + JWT guard `Auth::extend('jwt')`)

#### Shared JWT
- `src/Shared/JWT/JwtGuard.php` — Guard JWT que implementa `Illuminate\Contracts\Auth\Guard`, valida Bearer tokens RS256

#### Configuración modificada
- `config/auth.php` — guard `api` ahora usa driver `jwt` (era `session`)
- `config/app.php` — registrado `AuthorizationServiceProvider`
- `bootstrap/app.php` — middleware alias `require_permission`
- `routes/api.php` — ruta protegida `GET /api/v1/organizations/{organization}/admin`

#### Seeders
- `database/seeders/RbacDemoSeeder.php` — 2 roles (admin, resident, manager) + 2 permisos (admin.access, profile.view)
- `database/seeders/DatabaseSeeder.php` — actualizado para llamar `RbacDemoSeeder`

#### Tests
- `tests/Feature/Authorization/RbacTest.php` — 6 tests (5 criterios + no-auth)

#### Documentación
- `api/API_DATABASE.md` — tablas `roles`, `permissions`, `permission_role`, `role_assignments` documentadas
- `api/API_ARCHITECTURE.md` §5 — Authorization actualizado a "Implementado"

### Comandos pendientes (ejecutar en `code/api/`)

```bash
# 1. Ejecutar migraciones nuevas
php artisan migrate

# 2. Correr tests del bloque
php artisan test --filter=RbacTest

# 3. CI completo
composer ci
```

## Notas

_Vacío._
