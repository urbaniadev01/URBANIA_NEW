---
tipo: contrato
proyecto: shared
actualizado: 2026-07-05
---

# CONTRACT_LOCKS — Contratos de API congelados

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
- **Endpoint:** `POST /api/v1/auth/login`
- **Request body:** `email` (string, required), `password` (string, required)
- **Response (200):** `{ "access_token": "<JWT RS256>", "token_type": "Bearer", "expires_in": 900 }`
- **Cookie:** `refresh_token` (httpOnly, secure, sameSite=strict, path=/api/v1/auth)
- **Errores documentados:** `401 INVALID_CREDENTIALS`, `403 ACCOUNT_NOT_ACTIVE`, `422 VALIDATION_ERROR`, `429` (throttle)
- **Rate limiting:** 5 intentos por minuto por IP
- **Detalle completo:** [[../../api/endpoints/AUTH#post-apiv1authlogin]]
- **Congelado:** 2026-07-04
- **Consumido por:** [[../../features/AUTH/blocks/AUTH-B06-pantalla-login]]

### LOCK-AUTH-03 — `POST /auth/refresh` {#LOCK-AUTH-03}

- **Bloque productor:** [[../../features/AUTH/blocks/AUTH-B03-refresh-token]]
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
