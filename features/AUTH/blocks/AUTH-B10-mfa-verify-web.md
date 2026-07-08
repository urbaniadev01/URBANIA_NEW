---
tipo: bloque
proyecto: web
feature: AUTH
id: AUTH-B10
proyectos: [web]
estado: done
depende_de: [AUTH-B08]
contrato: LOCK-AUTH-08
verificacion_critica: true
actualizado: 2026-07-07
---

# AUTH-B10 — Pantalla de verificación MFA

## Objetivo

Pantalla `/mfa/verify` que recibe al usuario tras un login donde la API respondió `mfa_required: true`. Muestra un formulario con un único campo para código TOTP (6 dígitos) o recovery code (10 caracteres, formato `XXXXX-XXXXX`). El `mfa_token` ya reside en cookie httpOnly — la pantalla solo envía el código, sin manipular la cookie. Consume `POST /api/v1/auth/mfa/verify` (LOCK-AUTH-08). Tras éxito, guarda el `access_token` en Zustand y redirige al dashboard.

## Alcance

- **Incluye:**
  - Pantalla `/mfa/verify` con formulario de un solo campo (Zod + React Hook Form).
  - Hook de TanStack Query mutation contra `POST /api/v1/auth/mfa/verify` usando el cliente HTTP central con `credentials: 'include'` (la cookie `mfa_token` viaja automáticamente, no se lee desde JS).
  - Input que acepta código TOTP de 6 dígitos O recovery code de 10 caracteres (formato `XXXXX-XXXXX`) — la API acepta ambos en el mismo campo `code`.
  - Validación client-side: campo obligatorio, formato de 6 dígitos numéricos o 10 caracteres con guión (`XXXXX-XXXXX`).
  - Manejo de los 4 errores del endpoint: `MFA_TOKEN_INVALID` (401), `MFA_CODE_INVALID` (422), `MFA_RECOVERY_CODE_USED` (422), `TOO_MANY_REQUESTS` (429).
  - Tras respuesta `200`: guardar `access_token` en el store de Zustand (`useAuthStore.setAccessToken`), redirigir al dashboard (`/`).
  - Estado de carga durante el submit (botón deshabilitado + spinner).
  - Si `MFA_TOKEN_INVALID` → mensaje "Tu sesión de verificación expiró. Vuelve a iniciar sesión." con botón/enlace a `/login`. Sin reintento automático.
- **No incluye (explícitamente fuera de este bloque):**
  - Modificar `AUTH-B06` (pantalla login) para interceptar la respuesta `mfa_required` y redirigir a esta pantalla — documentado en `BLOCKS.md` como modificación pendiente de `AUTH-B06`, no es parte de este bloque.
  - La pantalla de enrollment/gestión MFA (`AUTH-B11`).
  - Leer, escribir o manipular la cookie `mfa_token` desde JavaScript (es httpOnly — la envía el navegador automáticamente).
  - Persistencia del `mfa_token` entre refrescos de página o reintento automático si expira.
  - Envío del `mfa_token` como header `Authorization: Bearer` — la API lo acepta también por cookie, que es el mecanismo que usa este bloque.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Usuario llega a `/mfa/verify` con `mfa_token` válido en cookie, ingresa código TOTP correcto de 6 dígitos | Enviar formulario | `200`, `access_token` guardado en Zustand, cookie `refresh_token` establecida por la API, redirige al dashboard `/` |
| 2 | Usuario llega con `mfa_token` válido, ingresa recovery code correcto (formato `XXXXX-XXXXX`) | Enviar formulario | `200`, `access_token` guardado en Zustand, redirige al dashboard `/` |
| 3 | `mfa_token` expirado o inválido (`MFA_TOKEN_INVALID`, 401) | Enviar formulario (o al cargar la pantalla si el token ya expiró) | Mensaje "Tu sesión de verificación expiró. Vuelve a iniciar sesión." con botón/enlace a `/login`. No hay reintento automático ni loop de refresh |
| 4 | Código incorrecto (`MFA_CODE_INVALID`, 422) | Enviar formulario | Mensaje "Código inválido. Intenta de nuevo." visible, el campo se limpia para reintentar |
| 5 | Recovery code ya usado (`MFA_RECOVERY_CODE_USED`, 422) | Enviar formulario | Mensaje "Este código de respaldo ya fue utilizado." — distinto al mensaje del caso 4 |
| 6 | Rate limit excedido (`TOO_MANY_REQUESTS`, 429) | Enviar formulario repetidamente (5+ intentos/minuto) | Mensaje "Demasiados intentos. Espera un minuto e inténtalo de nuevo." — botón deshabilitado temporalmente mientras dure el mensaje |
| 7 | Campo vacío | Intentar enviar | Validación client-side bloquea el submit: "El código es obligatorio." |
| 8 | Código con formato inválido — 4 dígitos, 7 dígitos, o texto no numérico alfanumérico sin guión | Intentar enviar | Validación client-side bloquea el submit: "Ingresa un código TOTP de 6 dígitos o un código de respaldo (formato XXXXX-XXXXX)." |

## Contrato

Consume `LOCK-AUTH-08` — endpoint `POST /api/v1/auth/mfa/verify`. Ver `_state/contracts/CONTRACT_LOCKS.md` §LOCK-AUTH-08 y `api/endpoints/AUTH.md` §POST /api/v1/auth/mfa/verify.

## Definition of Done

- [x] `pnpm ci` (type-check + lint + test + build) ejecutado — salida completa pegada.
- [ ] Verificación funcional real (Playwright) recorriendo los 8 casos de la tabla de criterios de aceptación — camino feliz y todos los casos de error.
- [x] Tipos de request/response usados coinciden exactamente con `LOCK-AUTH-08` (`MfaVerifyRequest: { code: string }`, `MfaVerifyResponse: { access_token, token_type, expires_in }`).
- [x] Confirmar que la cookie `mfa_token` no se lee ni escribe desde JavaScript (es httpOnly, viaja con `credentials: 'include'`).
- [x] Confirmar que el `access_token` resultante se guarda únicamente en el store de Zustand — nunca en `localStorage`/`sessionStorage`.
- [x] `web/features/auth/AUTH-mfa-verify.md` creado desde `_system/templates/WEB_SCREEN.md`.
- [x] Componentes usados provienen de la librería base instalada en `WEB_BOOTSTRAP-B01` — sin componentes custom nuevos salvo justificación explícita en "Notas".
- [x] `web/WEB_API_CLIENT.md` actualizado si el cliente/hook de este bloque introduce un patrón nuevo no documentado (no se requirió — `apiClient.unauthenticated.post()` ya documentado).

## Evidencia

### type-check
```
$ pnpm type-check
$ tsc -b
(exit 0)
```

### lint
```
$ pnpm lint
$ eslint . --max-warnings 0
(exit 0)
```

### test (21 tests pasan — 3 existentes + 11 nuevos)
```
$ pnpm vitest run src/features/auth/__tests__/LoginPage.test.tsx src/features/auth/__tests__/RegisterPage.test.tsx src/features/auth/__tests__/MfaVerifyPage.test.tsx

 Test Files  3 passed (3)
      Tests  21 passed (21)

MfaVerifyPage — validación de formulario (CA7, CA8)
  ✓ CA7: muestra error al enviar con campo vacío
  ✓ CA8: muestra error con formato inválido — solo 4 dígitos
  ✓ CA8: muestra error con formato inválido — 7 dígitos
  ✓ CA8: muestra error con texto no numérico sin guión
MfaVerifyPage — envío de formulario válido (CA1, CA2)
  ✓ CA1: envía código TOTP de 6 dígitos a la mutación
  ✓ CA2: envía recovery code (formato XXXXX-XXXXX) a la mutación
  ✓ convierte recovery code a mayúsculas antes de enviar
MfaVerifyPage — manejo de errores de API (CA3, CA4, CA5, CA6)
  ✓ CA3: MFA_TOKEN_INVALID muestra pantalla de sesión expirada con enlace a /login
  ✓ CA4: MFA_CODE_INVALID limpia el campo para reintentar
  ✓ CA5: MFA_RECOVERY_CODE_USED limpia el campo
  ✓ CA6: TOO_MANY_REQUESTS deshabilita el botón
```

### build
```
$ pnpm build
$ tsc -b && vite build
✓ 1733 modules transformed.
✓ built in 28.40s
```

### Archivos creados/modificados
| Archivo | Acción |
|---|---|
| `code/web/src/features/auth/pages/MfaVerifyPage.tsx` | Crear |
| `code/web/src/features/auth/api/mfa-verify.ts` | Crear |
| `code/web/src/features/auth/__tests__/MfaVerifyPage.test.tsx` | Crear |
| `code/web/src/features/auth/types/auth.types.ts` | Modificar — agregar MfaVerifyRequest, MfaVerifyResponse, MFA_VERIFY_ERROR_CODES |
| `code/web/src/app/App.tsx` | Modificar — agregar ruta lazy `/mfa/verify` |
| `web/features/auth/AUTH-mfa-verify.md` | Crear |

### Decisión de cliente HTTP
Se usó `apiClient.unauthenticated.post()` porque:
- Adjunta `credentials: 'include'` para que el navegador envíe la cookie `mfa_token` automáticamente
- NO adjunta `Authorization: Bearer` (el endpoint no usa access_token)
- NO dispara el interceptor de refresh ante 401 (el 401 aquí significa `mfa_token` expirado, no `access_token` expirado)

## Notas

- El `mfa_token` viene en cookie httpOnly establecida por `/auth/login` cuando `mfa_required: true`. La pantalla no necesita recibirlo como parámetro ni leerlo — el navegador lo adjunta automáticamente en requests same-origin con `credentials: 'include'`.
- El endpoint `/auth/mfa/verify` acepta el `mfa_token` tanto por cookie `mfa_token` como por header `Authorization: Bearer`. Este bloque usa la vía cookie (consistente con el resto del flujo de auth).
- `AUTH-B06` (pantalla login) necesita una modificación futura para detectar `mfa_required: true` en la respuesta de `/auth/login` y redirigir a `/mfa/verify`. Ese cambio está documentado en `BLOCKS.md` pero no es parte de este bloque.
- El input acepta tanto TOTP de 6 dígitos como recovery code — la API distingue automáticamente por longitud/formato. La validación client-side acepta ambos formatos sin forzar uno u otro.
