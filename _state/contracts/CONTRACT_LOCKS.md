---
tipo: contrato
proyecto: shared
actualizado: 2026-07-04
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

### LOCK-AUTH-01 — `POST /auth/register`

- **Bloque productor:** [[../../features/AUTH/blocks/AUTH-B01-registro-por-invitacion]]
- **Endpoint:** `POST /api/v1/auth/register`
- **Request body:** `invitation_token` (string, required), `password` (string, required), `name` (string, required), `phone` (string, optional)
- **Response (201):** `{ "message": "Registro exitoso", "user": { "id", "email", "name", "estado", "organization_id", "created_at" } }`
- **Errores documentados:** `403 INVITATION_TOKEN_INVALID`, `409 EMAIL_ALREADY_REGISTERED`, `422 VALIDATION_ERROR`, `429` (throttle)
- **Rate limiting:** 10 intentos por minuto por IP
- **Detalle completo:** [[../../api/endpoints/AUTH#post-authregister]]
- **Congelado:** 2026-07-04
- **Consumido por:** _ninguno todavía_

### LOCK-AUTH-02 — `POST /auth/login`

- **Bloque productor:** [[../../features/AUTH/blocks/AUTH-B02-login]]
- **Endpoint:** `POST /api/v1/auth/login`
- **Request body:** `email` (string, required), `password` (string, required)
- **Response (200):** `{ "access_token": "<JWT RS256>", "token_type": "Bearer", "expires_in": 900 }`
- **Cookie:** `refresh_token` (httpOnly, secure, sameSite=strict, path=/api/v1/auth)
- **Errores documentados:** `401 INVALID_CREDENTIALS`, `403 ACCOUNT_NOT_ACTIVE`, `422 VALIDATION_ERROR`, `429` (throttle)
- **Rate limiting:** 5 intentos por minuto por IP
- **Detalle completo:** [[../../api/endpoints/AUTH#post-apiv1authlogin]]
- **Congelado:** 2026-07-04
- **Consumido por:** _ninguno todavía_

## Locks reemplazados

_Vacío._
