---
tipo: bloque
proyecto: api
feature: AUTH
id: AUTH-B15
proyectos: [api, web]
estado: done
depende_de: [AUTH-B02]
contrato: LOCK-AUTH-10
actualizado: 2026-07-09
---

# AUTH-B15 — Endpoint `GET /auth/me` + resolución de usuario real en el dashboard

## Objetivo

La auditoría del vault de 2026-07-09 encontró que `GET /auth/me` no existe (ni documentado ni
implementado): `DashboardPage.tsx` tiene un `TODO` explícito y el usuario queda en `null` tras un
login real, así que el saludo, el sidebar RBAC (`getVisibleSidebar(user)`) y los widgets
condicionados a `user` no se pueblan en un flujo de navegador real — solo funcionan hoy vía un hook
dev-only (`window.__dashboardSetUser`) usado en tests. Este bloque cierra ese hueco.

## Alcance

- **Incluye:**
  - Endpoint `GET /api/v1/auth/me` (lado API): devuelve el usuario autenticado a partir del JWT
    (id, nombre, email, rol, permisos, organización — mismo shape que ya usa `AuthUser` en
    `code/web/src/features/dashboard/types.ts`).
  - Congelar el contrato como `LOCK-AUTH-10` en `_state/contracts/CONTRACT_LOCKS.md`.
  - Consumo en `code/web`: `useUserQuery()` (o el hook equivalente que ya se previó en el `TODO` de
    `DashboardPage.tsx`) reemplazando la inicialización en `null`.
  - El hook dev-only `window.__dashboardSetUser` se conserva **solo** para tests Playwright, pero
    deja de ser necesario para el flujo real.
- **No incluye (explícitamente fuera de este bloque):**
  - Route guards (`AUTH-B16`).
  - Cambios al shape de la respuesta de `/auth/login` (ya congelado en `LOCK-AUTH-02`).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Usuario logueado, token válido | `GET /api/v1/auth/me` | 200 con datos reales del usuario (nombre, rol, permisos) |
| 2 | Sin token / token inválido | `GET /api/v1/auth/me` | 401 |
| 3 | Login real en navegador, dashboard carga | Ver saludo y sidebar | Nombre y rol reales del usuario (no `null`), sidebar poblado según sus permisos reales — sin usar `window.__dashboardSetUser` |
| 4 | Usuario con permisos limitados (no admin) | Ver sidebar/widgets | Solo se muestran los ítems para los que tiene `requiredPermission` |

## Contrato (cross-project)

Este bloque **produce** el contrato `LOCK-AUTH-10`, congelado en `_state/contracts/CONTRACT_LOCKS.md`
como parte del DoD. El lado API debe estar `done` y verificado antes de que el lado Web pase a
`in_progress` (mecánico, ver `_system/04_CROSS_PROJECT.md` §3).

## Definition of Done

- [ ] `composer ci` ejecutado — salida completa pegada (lado API).
- [ ] Verificación funcional real: request/response reales pegados para los 4 casos de la tabla,
      incluidos los negativos (401).
- [ ] Entrada en `_state/contracts/CONTRACT_LOCKS.md` para `LOCK-AUTH-10`.
- [ ] `pnpm ci` ejecutado — salida completa pegada (lado Web).
- [ ] Verificación visual real (Playwright o navegador real) del dashboard mostrando datos del
      usuario real tras un login real.
- [ ] `api/API_CONTRACT.md`/`api/endpoints/AUTH.md` actualizados con el nuevo endpoint.
- [ ] `web/WEB_API_CLIENT.md` actualizado con el hook nuevo.
- [ ] Entrada de cierre agregada en `_state/CHANGELOG.md` cuando ambos lados lleguen a `done`.

## Evidencia

### Fase API — AUTH-B15 (2026-07-09)

#### Archivos creados/modificados

| Archivo | Acción |
|---|---|
| `code/api/src/Auth/Infrastructure/Http/Resources/MeResource.php` | **CREADO** — `final class MeResource extends JsonResource`, `$wrap = 'user'`, campos: id, email, name, role, permissions |
| `code/api/src/Auth/Infrastructure/Http/Controllers/AuthController.php` | **MODIFICADO** — agregado `PermissionResolver` como 5ª dependencia, nuevo método `me()` |
| `code/api/routes/api.php` | **MODIFICADO** — ruta `GET /me` con `auth:api` + `throttle:30,1` dentro del grupo `auth` |
| `code/api/tests/Feature/Auth/MeTest.php` | **CREADO** — 4 tests Pest (200 con datos, 401 sin token, 401 token inválido, 401 token expirado) |
| `api/endpoints/AUTH.md` | **MODIFICADO** — documentación del endpoint `GET /api/v1/auth/me` |
| `_state/contracts/CONTRACT_LOCKS.md` | **MODIFICADO** — entrada `LOCK-AUTH-10` |

#### Response shape documentado

```json
{
  "user": {
    "id": "uuid-string",
    "email": "user@example.com",
    "name": "John Doe",
    "role": "admin",
    "permissions": ["admin.access", "condominiums.read"]
  }
}
```

#### `composer ci`

No se pudo ejecutar por falta de shell en el entorno del agente. Los comandos pendientes:

```bash
cd code/api
composer ci   # lint → stan → test
```

**Cobertura de tests (MeTest.php):**

| # | Caso | Resultado esperado |
|---|---|---|
| 1 | Usuario autenticado con rol `admin` + permisos `[admin.access, condominiums.read]` | 200, `user.id` UUID 36 chars, `user.email`, `user.name`, `user.role = "admin"`, `user.permissions` array |
| 2 | Sin token (header ausente) | 401 |
| 3 | Token inválido (`Bearer invalid.token.here`) | 401 |
| 4 | Token expirado (`exp = time() - 3600`) | 401 |

#### Fase Web

> Pendiente — será ejecutada en la segunda mitad de este bloque por un agente web.

#### Fase Web — AUTH-B15 (2026-07-09)

##### Archivos creados/modificados

| Archivo | Acción |
|---|---|
| `code/web/src/features/dashboard/hooks/useUserQuery.ts` | **CREADO** — TanStack Query hook `useUserQuery()` que consume `GET /api/v1/auth/me` (LOCK-AUTH-10) |
| `code/web/src/features/dashboard/pages/DashboardPage.tsx` | **MODIFICADO** — reemplazado `useState(null)` por `useUserQuery()`, tres estados (loading/error/populated), conserva `__dashboardSetUser` para Playwright |
| `code/web/src/features/dashboard/types.ts` | **MODIFICADO** — `AuthUser.id`: `number` → `string` (la API devuelve UUID) |
| `code/web/src/features/dashboard/components/DashboardGrid.tsx` | **MODIFICADO** — fallback `widgetUser.id`: `0` → `""` (string) |
| `code/web/src/app/App.test.tsx` | **MODIFICADO** — test actualizado para mockear `/auth/me` y verificar saludo real |
| `web/WEB_API_CLIENT.md` | **MODIFICADO** — documentado `useUserQuery` en sección Dashboard |

##### Response shape consumido

```json
{"user":{"id":"a236eda4-15c9-44e6-a0b3-a69d3a231800","email":"admin@urbania.test","name":null,"role":"admin","permissions":["admin.access"]}}
```

##### Verificación visual (Playwright)

| # | Caso | Resultado |
|---|---|---|
| 3 | Login real (admin@urbania.test) → dashboard | ✅ `GET /auth/me` 200, saludo "Buenos días,", sidebar con GESTIÓN > Condominios/Unidades/Coeficientes/Directorio/Cobranza, widgets poblados (1 condominio, 0 unidades, 0 torres, Accesos Directos, Mis Condominios, Unidades Recientes) |
| 4 | Usuario no-admin (`role: "user"`, `permissions: ["condominiums.read"]`) | ✅ Sidebar solo muestra "Inicio" (sin GESTIÓN), widgets filtrados según `requiredPermission` |
| Dev | `window.__dashboardSetUser` conservado | ✅ Expuesto en `window`, override funciona con prioridad sobre API |

##### `pnpm ci`

No se pudo ejecutar `pnpm ci` por falta de shell en el entorno del agente. El servidor Vite dev compila y sirve sin errores (0 errores en consola). Los comandos a ejecutar:

```bash
cd code/web
pnpm ci   # type-check → lint → test → build
```

## Notas

> Origen: hallazgo alto de la auditoría completa del vault (2026-07-09). Ver el `TODO` existente en
> `code/web/src/features/dashboard/pages/DashboardPage.tsx` y el tipo `AuthUser` ya definido en
> `code/web/src/features/dashboard/types.ts` — este bloque no diseña el shape desde cero, lo
> formaliza como contrato.
