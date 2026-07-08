---
tipo: contrato
proyecto: shared
actualizado: 2026-07-08
---

# CONTRACT_LOCKS — Contratos de API congelados

> **Estado actual (2026-07-08):** 6 locks implementados (AUTH-01 a AUTH-05, AUTH-08, AUTH-09).
> Todos los productores en `done`. Consumidores web (B06, B07, B10, B11, B12) en `done`, B13 en `ready`.

> Registro de contratos de endpoint congelados para que un bloque de cliente pueda construir contra
> ellos. Formato y reglas completas en [[../../_system/04_CROSS_PROJECT]] §4–§5. Una entrada es
> inmutable mientras tenga un "Consumido por" activo — cambiarla es un bloque nuevo, no una edición
> (ver §5 de ese documento).
>
> **Regla mecánica:** un bloque de cliente con `proyectos: [web]` que depende de un endpoint no
> puede pasar a `ready` sin una entrada aquí que lo respalde.

## Locks activos

### LOCK-AUTH-01 — `POST /auth/register` {#LOCK-AUTH-01}

- **Bloque productor:** [[../../features/AUTH/blocks/AUTH-B01-registro-por-invitacion]]
- **Estado:** Implementado (AUTH-B01 en `done`). Reimplementación completa del endpoint.
- **Endpoint:** `POST /api/v1/auth/register`
- **Request body:** `invitation_token` (string, required), `password` (string, required), `name` (string, required), `phone` (string, optional)
- **Response (201):** `{ "message": "Registro exitoso", "user": { "id", "email", "name", "estado", "organization_id", "created_at" } }`
- **Errores documentados:** `403 INVITATION_TOKEN_INVALID`, `409 EMAIL_ALREADY_REGISTERED`, `422 VALIDATION_ERROR`, `429` (throttle)
- **Rate limiting:** 10 intentos por minuto por IP
- **Detalle completo:** [[../../api/endpoints/AUTH#post-apiv1authregister]]
- **Congelado:** 2026-07-04
- **Consumido por:** [[../../features/AUTH/blocks/AUTH-B07-pantalla-registro]]

### LOCK-AUTH-02 — `POST /auth/login` {#LOCK-AUTH-02}

- **Bloque productor:** [[../../features/AUTH/blocks/AUTH-B02-login]]
- **Modificado por:** [[../../features/AUTH/blocks/AUTH-B08-mfa-enrollment]] (adición no-breaking: respuesta `mfa_required` cuando el usuario tiene MFA activo)
- **Estado:** Implementado (AUTH-B02 en `done`). Reimplementación completa del endpoint.
- **Endpoint:** `POST /api/v1/auth/login`
- **Request body:** `email` (string, required), `password` (string, required)
- **Response (200) — usuario sin MFA:** `{ "access_token": "<JWT RS256>", "token_type": "Bearer", "expires_in": 900 }`
- **Response (200) — usuario con MFA:** `{ "mfa_required": true, "mfa_token": "<JWT RS256 tipo mfa>" }`
- **Cookie:** `refresh_token` (httpOnly, secure, sameSite=strict, path=/api/v1/auth) — solo cuando se emite `access_token`. `mfa_token` (httpOnly, secure, sameSite=strict, path=/api/v1/auth) — cuando `mfa_required: true`.
- **Errores documentados:** `401 INVALID_CREDENTIALS`, `403 ACCOUNT_NOT_ACTIVE`, `422 VALIDATION_ERROR`, `429` (throttle)
- **Rate limiting:** 5 intentos por minuto por IP
- **Detalle completo:** [[../../api/endpoints/AUTH#post-apiv1authlogin]]
- **Congelado:** 2026-07-04
- **Actualización (no-breaking):** 2026-07-07 — adición de respuesta `mfa_required` para usuarios con MFA activo
- **Consumido por:** [[../../features/AUTH/blocks/AUTH-B06-pantalla-login]]

### LOCK-AUTH-03 — `POST /auth/refresh` {#LOCK-AUTH-03}

- **Bloque productor:** [[../../features/AUTH/blocks/AUTH-B03-refresh-token]]
- **Estado:** Implementado (AUTH-B03 en `done`). Endpoint de refresh con rotación y detección de reuso.
- **Endpoint:** `POST /api/v1/auth/refresh`
- **Request:** Sin body. Cookie `refresh_token` (httpOnly, secure, sameSite=strict, path=/api/v1/auth)
- **Response (200):** `{ "access_token": "<JWT RS256>", "token_type": "Bearer", "expires_in": 900 }`
- **Cookie:** nuevo `refresh_token` (httpOnly, secure, sameSite=strict, path=/api/v1/auth)
- **Errores documentados:** `401 REFRESH_TOKEN_MISSING`, `401 REFRESH_TOKEN_EXPIRED`, `401 REFRESH_TOKEN_REUSED`
- **Detalle completo:** [[../../api/endpoints/AUTH#post-apiv1authrefresh]]
- **Congelado:** 2026-07-05
- **Consumido por:** _ninguno todavía_

### LOCK-AUTH-04 — `POST /auth/logout` {#LOCK-AUTH-04}

- **Bloque productor:** [[../../features/AUTH/blocks/AUTH-B04-logout]]
- **Estado:** Implementado (AUTH-B04 en `done`). Reimplementación completa del endpoint.
- **Endpoint:** `POST /api/v1/auth/logout`
- **Request:** Sin body. Cookie `refresh_token` (httpOnly, secure, sameSite=strict, path=/api/v1/auth) — opcional.
- **Response (200):** `{ "message": "Sesión cerrada exitosamente." }`
- **Cookie:** `refresh_token` se limpia (Set-Cookie con valor vacío y expiración pasada). Mismo path y flags que la cookie original.
- **Errores documentados:** Ninguno — logout es siempre `200` (idempotente). `429` por rate limiting (10 intentos/minuto por IP).
- **Idempotencia:** Si no hay cookie o el token ya está revocado/expirado, igual responde `200` — no revela si había sesión activa.
- **Detalle completo:** [[../../api/endpoints/AUTH#post-apiv1authlogout]]
- **Congelado:** 2026-07-05
- **Consumido por:** _ninguno todavía_

## Locks reemplazados

_Vacío._

### LOCK-AUTH-08 — Endpoints MFA {#LOCK-AUTH-08}

- **Bloque productor:** [[../../features/AUTH/blocks/AUTH-B08-mfa-enrollment]]
- **Estado:** Implementado. Endpoints de enrollment, verificación, desactivación y regeneración de códigos MFA.
- **Endpoints:**
  - `POST /api/v1/auth/mfa/enroll` — iniciar enrollment MFA (TOTP + recovery codes)
  - `POST /api/v1/auth/mfa/confirm` — confirmar enrollment con código TOTP
  - `POST /api/v1/auth/mfa/verify` — verificar MFA durante login (usa `mfa_token`)
  - `POST /api/v1/auth/mfa/disable` — desactivar MFA
  - `POST /api/v1/auth/mfa/recovery` — regenerar códigos de respaldo
- **Request/Response:** Ver detalle en [[../../api/endpoints/AUTH]]
- **Errores documentados:** `MFA_ALREADY_ENABLED` (409), `MFA_NOT_ENABLED` (409), `MFA_CODE_INVALID` (422), `MFA_TOKEN_INVALID` (401), `MFA_RECOVERY_CODE_USED` (422), `MFA_ENROLLMENT_NOT_FOUND` (404), `MFA_ENROLLMENT_EXPIRED` (422), `MFA_REQUIRED` (403), `MFA_RATE_LIMIT` (429)
- **Rate limiting:** Enroll: 3/hora/usuario. Verify: 5/minuto/usuario. Ambos implementados vía Redis (no middleware throttle).
- **Detalle completo:** [[../../api/endpoints/AUTH]]
- **Congelado:** 2026-07-07
- **Consumido por:** [[../../features/AUTH/blocks/AUTH-B10-mfa-verify-web]], [[../../features/AUTH/blocks/AUTH-B11-mfa-enroll-web]]

### LOCK-AUTH-09 — `POST /auth/forgot-password` y `POST /auth/reset-password` {#LOCK-AUTH-09}

- **Bloque productor:** [[../../features/AUTH/blocks/AUTH-B09-recuperacion-password]]
- **Estado:** Implementado. Endpoints de recuperación de contraseña: solicitud de reset y aplicación de nueva contraseña.
- **Endpoints:**
  - `POST /api/v1/auth/forgot-password` — solicitar recuperación (siempre 200 genérico)
  - `POST /api/v1/auth/reset-password` — aplicar nueva contraseña con token
  - `GET /dev/password-resets/last?email=...` — dev endpoint (solo local/testing)
- **Request/Response:** Ver detalle en [[../../api/endpoints/AUTH]]
- **Errores documentados:** `RESET_TOKEN_EXPIRED` (422), `RESET_TOKEN_INVALID` (422), `TOO_MANY_REQUESTS` (429), `VALIDATION_ERROR` (422)
- **Rate limiting:** Forgot: 3/hora/email. Reset: 5/15min/IP. Ambos implementados vía Redis (no middleware throttle).
- **Seguridad:** Respuesta genérica en forgot-password (mismo status/body/tiempo exista o no el email). Token hasheado con SHA-256 en BD. Token de un solo uso.
- **Detalle completo:** [[../../api/endpoints/AUTH]]
- **Congelado:** 2026-07-07
- **Consumido por:** [[../../features/AUTH/blocks/AUTH-B12-forgot-password-web]], [[../../features/AUTH/blocks/AUTH-B13-reset-password-web]]
