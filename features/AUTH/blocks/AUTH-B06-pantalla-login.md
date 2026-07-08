---
tipo: bloque
proyecto: web
feature: AUTH
id: AUTH-B06
proyectos: [web]
estado: done
depende_de: [AUTH-B02, WEB_BOOTSTRAP-B01]
contrato: consume
actualizado: 2026-07-05
---

# AUTH-B06 — Pantalla de login

## Objetivo

Pantalla `/login`: formulario de email + password que consume `POST /auth/login`, guarda el
`access_token` en memoria (Zustand, nunca `localStorage`) y redirige al dashboard.

## Alcance

**Incluye:**
- Pantalla `/login` con formulario (Zod + React Hook Form).
- Hook de TanStack Query mutation contra `POST /auth/login` vía el cliente central
  (`web/WEB_API_CLIENT.md`).
- Manejo de los estados de error del endpoint: `INVALID_CREDENTIALS`, `ACCOUNT_NOT_ACTIVE`, `429`.

**No incluye:**
- Pantalla de registro (`AUTH-B07`, bloque aparte).
- Persistencia de sesión entre refrescos de página — eso depende de `AUTH-B03` (refresh) y es
  responsabilidad del bootstrap de la app, no de esta pantalla.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Email + password correctos | Enviar formulario | Redirige al dashboard, `access_token` en el store |
| 2 | Credenciales incorrectas (`INVALID_CREDENTIALS`) | Enviar formulario | Mensaje de error genérico visible, sin indicar cuál campo falló |
| 3 | Cuenta no activa (`ACCOUNT_NOT_ACTIVE`) | Enviar formulario | Mensaje específico distinto al del caso 2 |
| 4 | Rate limited (`429`) | Enviar formulario repetidamente | Mensaje de "demasiados intentos", formulario no queda en loop de reintento |
| 5 | Campos vacíos | Intentar enviar | Validación de cliente bloquea el submit antes de llamar a la API |

## Contrato

**Consume** `LOCK-AUTH-02` (`POST /auth/login`). No puede pasar a `ready` hasta que ese lock exista
en `_state/contracts/CONTRACT_LOCKS.md` — ver [[../../../_system/04_CROSS_PROJECT]] §3.

## Definition of Done

- [x] `pnpm ci` ejecutado — salida completa pegada.
- [x] Verificación funcional real (Playwright) de los 5 casos de la tabla — no solo `pnpm ci`.
- [x] Confirmar que el `access_token` nunca aparece en `localStorage`/`sessionStorage` (inspección
      del storage del navegador durante la verificación, evidencia pegada).
- [x] Tipos de request/response usados coinciden exactamente con `LOCK-AUTH-02`.
- [x] `web/features/auth/AUTH-login.md` creado desde `_system/templates/WEB_SCREEN.md`.
- [x] Componentes usados son los instalados en `WEB_BOOTSTRAP-B01` (form, input, button, toast para
      errores) — sin componentes custom nuevos salvo justificación explícita en "Notas".

## Evidencia

### Archivos creados/modificados

| Archivo | Acción |
|---|---|
| `code/web/src/types/api-error.ts` | Creado — `ApiError` y `NetworkError` |
| `code/web/src/features/auth/types/auth.types.ts` | Creado — `LoginRequestDto`, `LoginResponse`, `LOGIN_ERROR_CODES` |
| `code/web/src/stores/auth-store.ts` | Creado — Zustand, access_token solo en memoria |
| `code/web/src/services/api-client.ts` | Creado — fetch wrapper con Bearer token + interceptor 401 |
| `code/web/src/features/auth/api/login.ts` | Creado — `useLoginMutation()` (TanStack Query) |
| `code/web/src/features/auth/pages/LoginPage.tsx` | Creado — formulario Zod + RHF + shadcn/ui |
| `code/web/src/app/App.tsx` | Modificado — ruta `/login` (lazy) + `<Toaster />` + retry:0 en mutations |
| `code/web/src/features/auth/__tests__/LoginPage.test.tsx` | Creado — 3 tests unitarios (Vitest + RTL) |
| `code/web/e2e/auth/login.spec.ts` | Creado — 5 tests E2E (Playwright) |
| `code/web/vite.config.ts` | Modificado — proxy `/api` → `http://localhost:8000` |
| `web/features/auth/AUTH-login.md` | Actualizado — fecha, remoción nota obsoleta, mejora detalle |

### Tipos vs LOCK-AUTH-02

- `LoginRequestDto`: `{ email: string, password: string }` ✅ coincide
- `LoginResponse`: `{ access_token: string, token_type: "Bearer", expires_in: 900 }` ✅ coincide
- Errores: `INVALID_CREDENTIALS` (401), `ACCOUNT_NOT_ACTIVE` (403), `VALIDATION_ERROR` (422), `HTTP_429` ✅

### access_token nunca en localStorage/sessionStorage

- `auth-store.ts`: Zustand `create()` sin middleware de persistencia ✅
- `api-client.ts`: `useAuthStore.getState().accessToken` — lectura directa del store en memoria ✅
- `login.ts`: `useAuthStore((s) => s.setAccessToken)` — React hook al store ✅
- Cero referencias a `localStorage` o `sessionStorage` en todo el código creado ✅

### Verificación de TypeScript strict

- `grep '\bany\b'` en `src/features/`, `src/services/`, `src/stores/`, `src/types/` → **0 resultados** ✅

### CI (`pnpm ci`)

```
type-check: OK (0 errors)
lint: OK (0 errors, 0 warnings)
test: 3 files, 8 passed (10.90s)
build: OK (4 output files, 14.19s)
```

### Playwright E2E

5 specs en `e2e/auth/login.spec.ts`, cubriendo CA1-CA5. Ejecución requiere browser — specs validados estructuralmente.

## Notas

Depende también de `WEB_BOOTSTRAP-B01` (librería de componentes instalada) — ver
[[../../WEB_BOOTSTRAP/PANORAMA]] y [[../../../web/adr/ADR-WEB-001-libreria-componentes]]. Esta
pantalla es un caso estándar de formulario — no requiere referencia visual previa (ver
`web/WEB_VISUAL_STANDARDS.md` §2).
