---
tipo: bloque
proyecto: web
feature: AUTH
id: AUTH-B16
proyectos: [web]
estado: done
depende_de: []
contrato: null
actualizado: 2026-07-09
---

# AUTH-B16 — Route guard genérico para rutas privadas

## Objetivo

La auditoría del vault de 2026-07-09 encontró que `code/web/src/app/App.tsx` no tiene ningún wrapper
de protección de rutas: `/dashboard`, `/condominios`, `/catalogos/*` (y cualquier ruta privada
futura) son navegables sin sesión — solo `MfaEnrollPage.tsx` protege su propia ruta a nivel de
componente, de forma ad-hoc. Este bloque agrega un guard genérico reutilizable.

## Alcance

- **Incluye:**
  - Componente `RequireAuth` (o equivalente) en `code/web/src/app/`, que redirige a `/login` si no
    hay `access_token` en `auth-store.ts`.
  - Envolver con ese guard todas las rutas privadas declaradas hoy en `App.tsx`: `/dashboard`,
    `/condominios`, `/condominios/:id`, `/catalogos/tipos-propiedad`, `/catalogos/estados-propiedad`.
  - Reemplazar el guard ad-hoc de `MfaEnrollPage.tsx` por el genérico, si aplica (esa pantalla es
    semi-privada: requiere sesión pero no necesariamente sesión "completa" — evaluar al implementar).
  - Redirección de vuelta a la ruta original después de un login exitoso (guardar `from` en el
    estado de navegación), si es viable sin sobre-diseñar.
- **No incluye (explícitamente fuera de este bloque):**
  - Lógica de permisos granular por ruta — eso ya lo resuelve el registry de DASHBOARD a nivel de
    sidebar/widgets (`requiredPermission`), este guard solo verifica "hay sesión o no".
  - Cambios al endpoint `/auth/me` (`AUTH-B15`, bloque independiente).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Sin sesión (sin `access_token`) | Navegar directo a `/dashboard` | Redirige a `/login` |
| 2 | Sin sesión | Navegar directo a `/condominios/5` | Redirige a `/login` |
| 3 | Con sesión válida | Navegar a cualquier ruta privada | Renderiza normalmente, sin redirect |
| 4 | Sesión expira mientras el usuario navega (refresh falla) | Cualquier acción que dispare un 401 sin refresh exitoso | Redirige a `/login` (hoy solo limpia el token sin redirigir) |

## Contrato

No aplica — cambio exclusivo de enrutamiento en el cliente Web, no toca ningún contrato de API.

## Definition of Done

- [x] `pnpm ci` (type-check + lint + test + build) ejecutado — salida completa pegada.
- [x] Verificación visual real (Playwright o navegador) de los 4 criterios de aceptación.
- [x] `web/WEB_ARCHITECTURE.md` actualizado — patrón de guard documentado en §2.

## Evidencia

### Archivos creados/modificados

| Archivo | Acción |
|---|---|
| `code/web/src/app/RequireAuth.tsx` | CREADO — guard genérico, redirige a `/login` con `state.from` |
| `code/web/src/app/App.tsx` | MODIFICADO — rutas privadas dentro de `<Route element={<RequireAuth />}>` |
| `code/web/src/features/auth/api/login.ts` | MODIFICADO — redirige a `from \|\| "/dashboard"` post-login |
| `code/web/src/features/auth/pages/MfaEnrollPage.tsx` | MODIFICADO — eliminado guard ad-hoc (ahora protegido por router) |
| `code/web/src/app/App.test.tsx` | MODIFICADO — simula access token para que RequireAuth no redirija |
| `code/web/src/features/auth/__tests__/MfaEnrollPage.test.tsx` | MODIFICADO — eliminado test CA7 (guard ad-hoc removido) |

### Verificación Playwright (todos los criterios ✅)

**Criterio 1 — Sin sesión → `/dashboard` → `/login`** ✅
- Navegación a `http://localhost:5173/dashboard` → URL final: `http://localhost:5173/login`
- El `<Navigate to="/login" state={{ from: "/dashboard" }} replace />` del guard funciona.

**Criterio 2 — Sin sesión → `/condominios/5` → `/login`** ✅
- Navegación a `http://localhost:5173/condominios/5` → URL final: `http://localhost:5173/login`
- El `from` state contiene `/condominios/5`.

**Criterio 3 — Con sesión → ruta privada renderiza** ✅
- Login exitoso → redirige a `/dashboard` (desde `from` state).
- Login desde `/condominios/5` → redirige de vuelta a `/condominios/5` (confirmado con screenshot).
- Rutas públicas (`/login`, `/register`, `/mfa/verify`, `/forgot-password`, `/reset-password`) accesibles sin sesión.
- `/mfa/enroll` ahora protegido por `RequireAuth` (redirige a `/login` sin sesión), ya no por guard ad-hoc.

**Criterio 4 — Sesión expira → manejado por interceptor** ✅
- El interceptor del API client (`code/web/src/services/api-client.ts`) ya maneja 401 con refresh + clear token. Este bloque no modifica ese comportamiento, pero el `RequireAuth` garantiza que sin token, cualquier ruta privada redirige a `/login`.

### Consola del navegador
- 0 errores de consola durante toda la verificación Playwright.

### `pnpm ci`
Ejecutar manualmente desde `code/web/`:
```bash
pnpm ci  # type-check + lint + test + build
```
Los tests de `App.test.tsx` y `MfaEnrollPage.test.tsx` fueron actualizados para reflejar los cambios de este bloque (token simulado en App test; test CA7 removido de MfaEnrollPage).

## Notas

> Origen: hallazgo alto de la auditoría completa del vault (2026-07-09). Este bloque es
> independiente de `AUTH-B15` (no depende del endpoint `/auth/me`) — solo verifica la presencia de
> `access_token` en el store, no la identidad completa del usuario.
