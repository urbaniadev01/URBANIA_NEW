---
tipo: referencia
proyecto: api
feature: AUTH
actualizado: 2026-07-05
---

# Endpoints: AUTH

> **Estado de implementación:** `POST /auth/register` y `GET /dev/invitations/last` están
> implementados (AUTH-B01). Los endpoints `POST /auth/login`, `POST /auth/refresh`,
> `POST /auth/logout`, y los 5 endpoints de MFA (`/auth/mfa/*`) están implementados
> (bloques AUTH-B02 a AUTH-B04, AUTH-B08). `POST /auth/forgot-password`,
> `POST /auth/reset-password`, y `GET /dev/password-resets/last`
> están implementados (bloque AUTH-B09).

## POST /api/v1/auth/register

**Bloque que lo produce:** [[../../features/AUTH/blocks/AUTH-B01-registro-por-invitacion]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-AUTH-01]]

### Request

```json
{
  "invitation_token": "string — obligatorio — token de invitación vigente",
  "password": "string — obligatorio — mínimo 8 caracteres, 1 mayúscula, 1 minúscula, 1 número",
  "name": "string — obligatorio — nombre del usuario (max 255)",
  "phone": "string — opcional — teléfono (max 20)"
}
```

### Response — éxito (`201`)

```json
{
  "message": "Registro exitoso",
  "user": {
    "id": "uuid",
    "email": "string",
    "name": "string",
    "estado": "active",
    "organization_id": "uuid",
    "created_at": "datetime"
  }
}
```

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 422 | `VALIDATION_ERROR` | Campos faltantes o inválidos (password no cumple política, etc.) |
| 403 | `INVITATION_TOKEN_INVALID` | Token no existe, ya consumido, o expirado |
| 409 | `EMAIL_ALREADY_REGISTERED` | El email de la invitación ya está asociado a un usuario |
| 429 | — | Rate limiting superado (10 intentos/minuto por IP) |

### Autorización

Ninguna — el endpoint es público. La validación de identidad se basa en el `invitation_token`.

## POST /api/v1/auth/login

**Bloque que lo produce:** [[../../features/AUTH/blocks/AUTH-B02-login]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-AUTH-02]]

### Request

```json
{
  "email": "string — obligatorio — email del usuario",
  "password": "string — obligatorio — password en texto plano"
}
```

### Response — éxito (`200`) — usuario sin MFA

```json
{
  "access_token": "string — JWT RS256 firmado, vida corta (15 min por defecto)",
  "token_type": "Bearer",
  "expires_in": 900
}
```

Además, se establece una cookie `refresh_token` httpOnly, secure, sameSite=strict, con path `/api/v1/auth`.

### Response — éxito (`200`) — usuario con MFA activo

```json
{
  "mfa_required": true,
  "mfa_token": "string — JWT RS256, vida ultra-corta (5 min), tipo 'mfa'"
}
```

Además, se establece una cookie `mfa_token` httpOnly, secure, sameSite=strict, con path `/api/v1/auth`.
**No se emite `access_token`** — el cliente debe completar el flujo MFA en `/auth/mfa/verify`.
Este es un cambio no-breaking: los clientes existentes que esperan `access_token` solo reciben la nueva respuesta cuando el usuario tiene MFA activo.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 422 | `VALIDATION_ERROR` | Campos faltantes o inválidos (email o password no enviados, email con formato inválido) |
| 401 | `INVALID_CREDENTIALS` | Email no existe o password incorrecta (no se distingue — mismo error en ambos casos) |
| 403 | `ACCOUNT_NOT_ACTIVE` | El usuario existe pero su estado no es `active` (ej. `suspended`) |
| 429 | — | Rate limiting superado (5 intentos/minuto por IP) |

### Autorización

Ninguna — el endpoint es público.

### Rate limiting

5 intentos por minuto por IP. Configurado con el middleware `throttle:5,1`.

### Seguridad

Los errores por "email no existe" y "password incorrecta" devuelven exactamente el mismo `error.code` (`INVALID_CREDENTIALS`) y estructura JSON para evitar enumeración de emails registrados.

## GET /dev/invitations/last?email=...

**Bloque que lo produce:** [[../../features/AUTH/blocks/AUTH-B01-registro-por-invitacion]]
**Entorno:** Solo disponible en `local` y `testing`. No es parte del contrato del producto.

Devuelve el `invitation_token` vigente más reciente para el email dado.

### Request

Query parameter: `email` (string, required).

### Response (`200`)

Texto plano con el token de invitación.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 422 | — | Falta el parámetro `email` |
| 404 | — | No hay invitación vigente para ese email |
| 404 | — | Entorno production (la ruta no existe) |

## POST /api/v1/auth/refresh

**Bloque que lo produce:** [[../../features/AUTH/blocks/AUTH-B03-refresh-token]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-AUTH-03]]

### Request

Sin body. El `refresh_token` se envía en la cookie httpOnly `refresh_token` (establecida por `/auth/login` o un refresh anterior).

### Response — éxito (`200`)

```json
{
  "access_token": "string — JWT RS256, vida corta (15 min por defecto)",
  "token_type": "Bearer",
  "expires_in": 900
}
```

Además, se establece una nueva cookie `refresh_token` httpOnly, secure, sameSite=strict, con path `/api/v1/auth`.
El refresh_token anterior queda invalidado (rotación).

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | `REFRESH_TOKEN_MISSING` | No se envió la cookie `refresh_token` |
| 401 | `REFRESH_TOKEN_EXPIRED` | El refresh token expiró |
| 401 | `REFRESH_TOKEN_REUSED` | El refresh token ya fue usado — posible robo de sesión. Todas las sesiones del usuario fueron revocadas. |

### Autorización

Ninguna — el endpoint es público. La identidad se deriva del refresh token.

### Seguridad

- **Rotación:** cada uso exitoso del refresh token invalida el token usado y emite uno nuevo.
- **Detección de reuso:** si un refresh token ya usado se presenta de nuevo, se revocan **todos** los refresh tokens del usuario (no solo el presentado), forzando un nuevo login.

## POST /api/v1/auth/logout

**Bloque que lo produce:** [[../../features/AUTH/blocks/AUTH-B04-logout]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-AUTH-04]]

### Request

Sin body. El `refresh_token` se envía (opcionalmente) en la cookie httpOnly `refresh_token`.

### Response — éxito (`200`)

```json
{
  "message": "Sesión cerrada exitosamente."
}
```

Además, se establece una cookie `refresh_token` con valor vacío y expiración en el pasado, instruyendo al browser a eliminarla.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 429 | — | Rate limiting superado (10 intentos/minuto por IP) |

### Autorización

Ninguna — el endpoint es público. La identidad se deriva del refresh token (si está presente).

### Idempotencia

El endpoint **siempre** devuelve `200`, sin importar si:
- Había una sesión activa (el token se revoca).
- No había cookie (ya estaba deslogueado).
- El token estaba expirado (no era usable de todas formas).
- El token ya estaba revocado (no-op).

Esto es por diseño: logout no debe revelar información sobre el estado de la sesión.

## POST /api/v1/auth/mfa/enroll

**Bloque que lo produce:** [[../../features/AUTH/blocks/AUTH-B08-mfa-enrollment]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-AUTH-08]]

### Request

Sin body. Requiere Bearer token (`access_token`).

### Response — éxito (`201`)

```json
{
  "qr_code": "data:image/png;base64,...",
  "recovery_codes": ["ABCDE-FGHIJ", "..."],
  "enrollment_token": "string"
}
```

- `qr_code`: QR code en base64 PNG (data URI) para escanear con app autenticadora.
- `recovery_codes`: 8 códigos de respaldo (10 caracteres alfanuméricos, formato XXXXX-XXXXX). Se muestran una sola vez.
- `enrollment_token`: token simple que identifica la sesión de enrollment.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | `UNAUTHENTICATED` | No se envió Bearer token o es inválido |
| 409 | `MFA_ALREADY_ENABLED` | El usuario ya tiene MFA activo |
| 429 | `TOO_MANY_REQUESTS` | Rate limit: 3 intentos/hora por usuario |

### Autorización

Requiere autenticación (`auth:api`). El enrollment es voluntario — cualquier usuario autenticado puede iniciarlo.

### Seguridad

El secreto TOTP se almacena solo en Redis durante el enrollment (TTL 10 min). No se persiste en BD hasta la confirmación exitosa. El `totp_secret` nunca aparece en la respuesta JSON.

## POST /api/v1/auth/mfa/confirm

**Bloque que lo produce:** [[../../features/AUTH/blocks/AUTH-B08-mfa-enrollment]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-AUTH-08]]

### Request

```json
{
  "code": "string — obligatorio — código TOTP de 6 dígitos"
}
```

Requiere Bearer token (`access_token`).

### Response — éxito (`200`)

```json
{
  "message": "MFA activado exitosamente."
}
```

El secreto TOTP se encripta y persiste en `user_mfa`. Los recovery codes se hashean con bcrypt.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | `UNAUTHENTICATED` | No se envió Bearer token o es inválido |
| 404 | `MFA_ENROLLMENT_NOT_FOUND` | No hay enrollment pendiente (expirado o nunca iniciado) |
| 422 | `MFA_CODE_INVALID` | El código TOTP no coincide |
| 422 | `MFA_ENROLLMENT_EXPIRED` | 5 intentos fallidos consecutivos — enrollment cancelado |

## POST /api/v1/auth/mfa/verify

**Bloque que lo produce:** [[../../features/AUTH/blocks/AUTH-B08-mfa-enrollment]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-AUTH-08]]

### Request

```json
{
  "code": "string — obligatorio — código TOTP (6 dígitos) o recovery code (formato XXXXX-XXXXX)"
}
```

El `mfa_token` se envía como Bearer token (o en cookie `mfa_token`). Este endpoint **no** usa `auth:api` — valida el `mfa_token` manualmente.

### Response — éxito (`200`)

```json
{
  "access_token": "string — JWT RS256, vida corta (15 min), claim mfa_verified: true",
  "token_type": "Bearer",
  "expires_in": 900
}
```

Además, se establece una cookie `refresh_token` httpOnly, secure, sameSite=strict, con path `/api/v1/auth`.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | `MFA_TOKEN_INVALID` | El `mfa_token` no es válido o ha expirado |
| 409 | `MFA_NOT_ENABLED` | El usuario no tiene MFA activo |
| 422 | `MFA_CODE_INVALID` | El código no es válido |
| 422 | `MFA_RECOVERY_CODE_USED` | El código de respaldo ya fue usado |
| 429 | `TOO_MANY_REQUESTS` | Rate limit: 5 intentos/minuto por usuario |

### Seguridad

Si se usa un código de respaldo válido, este se marca como consumido (`used_at`) y no puede reutilizarse. El `access_token` emitido incluye `mfa_verified: true` para que el middleware `require_mfa` lo acepte.

## POST /api/v1/auth/mfa/disable

**Bloque que lo produce:** [[../../features/AUTH/blocks/AUTH-B08-mfa-enrollment]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-AUTH-08]]

### Request

```json
{
  "code": "string — obligatorio — código TOTP de 6 dígitos"
}
```

Requiere Bearer token (`access_token`).

### Response — éxito (`200`)

```json
{
  "message": "MFA desactivado exitosamente."
}
```

La fila `user_mfa` se elimina (delete físico).

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | `UNAUTHENTICATED` | No se envió Bearer token o es inválido |
| 409 | `MFA_NOT_ENABLED` | El usuario no tiene MFA activo |
| 422 | `MFA_CODE_INVALID` | El código TOTP no es válido |

## POST /api/v1/auth/mfa/recovery

**Bloque que lo produce:** [[../../features/AUTH/blocks/AUTH-B08-mfa-enrollment]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-AUTH-08]]

### Request

```json
{
  "code": "string — obligatorio — código TOTP de 6 dígitos"
}
```

Requiere Bearer token (`access_token`).

### Response — éxito (`200`)

```json
{
  "recovery_codes": ["NUEVO-ABCDE", "...8 códigos"]
}
```

Los códigos anteriores se invalidan (sobrescritos). Los nuevos se muestran una sola vez.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 401 | `UNAUTHENTICATED` | No se envió Bearer token o es inválido |
| 409 | `MFA_NOT_ENABLED` | El usuario no tiene MFA activo |
| 422 | `MFA_CODE_INVALID` | El código TOTP no es válido |

### Seguridad

Los códigos de respaldo se hashean con bcrypt (cost 12) antes de persistir. Los códigos en texto plano solo existen en la respuesta JSON de este endpoint y del enrollment — nunca se almacenan.

## POST /api/v1/auth/forgot-password

**Bloque que lo produce:** [[../../features/AUTH/blocks/AUTH-B09-recuperacion-password]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-AUTH-09]]

### Request

```json
{
  "email": "string — obligatorio — email del usuario"
}
```

### Response — éxito (`200`)

```json
{
  "message": "Si el email está registrado, recibirás un enlace de recuperación."
}
```

El mensaje es **siempre el mismo** exista o no el email. El tiempo de respuesta es deliberadamente similar para ambos casos (≈100ms adicionales para emails no existentes) para prevenir timing attacks.

Si el email existe, se genera un token de 64 caracteres hex, se persiste hasheado con SHA-256 en `password_reset_tokens` (TTL 60 minutos), y se envía un email con un link de recuperación.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 429 | `TOO_MANY_REQUESTS` | Rate limiting: 3 intentos/hora por email |

**Nota:** Validación de formato de email inválido **también** devuelve `200` con el mismo mensaje genérico — no se distingue entre "email no existe" y "email mal formado" para evitar enumeración.

### Autorización

Ninguna — el endpoint es público.

### Rate limiting

3 intentos por hora por email. Implementado vía Redis (`password_reset_forgot:{email}`). No usa el middleware `throttle` — es un rate limit custom por email, no por IP.

### Seguridad

- **Respuesta genérica:** mismo status, body y tiempo de respuesta para email existente, no existente, y formato inválido.
- **Token hasheado:** el token se persiste como `hash('sha256', $token)` — nunca en texto plano en BD.
- **Un solo uso:** el token se elimina al usarse exitosamente.

## POST /api/v1/auth/reset-password

**Bloque que lo produce:** [[../../features/AUTH/blocks/AUTH-B09-recuperacion-password]]
**Lock de contrato:** [[../../_state/contracts/CONTRACT_LOCKS#LOCK-AUTH-09]]

### Request

```json
{
  "token": "string — obligatorio — token de 64 caracteres hex recibido por email",
  "password": "string — obligatorio — mínimo 8 caracteres, 1 mayúscula, 1 minúscula, 1 número",
  "password_confirmation": "string — obligatorio — debe coincidir con password"
}
```

### Response — éxito (`200`)

```json
{
  "message": "Contraseña actualizada exitosamente."
}
```

El token se elimina de la BD — no puede reutilizarse.

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 422 | `VALIDATION_ERROR` | Campos faltantes, password no cumple política, o passwords no coinciden |
| 422 | `RESET_TOKEN_INVALID` | Token nunca existió, ya fue usado, o el usuario asociado fue eliminado |
| 422 | `RESET_TOKEN_EXPIRED` | Token válido pero expirado (>60 min) |
| 429 | `TOO_MANY_REQUESTS` | Rate limiting: 5 intentos/15 minutos por IP |

### Autorización

Ninguna — el endpoint es público. La identidad se deriva del token.

### Rate limiting

5 intentos por 15 minutos por IP. Implementado vía Redis (`password_reset_attempts:{ip}`). No usa el middleware `throttle`.

### Seguridad

- **Token hasheado:** se busca por `hash('sha256', $tokenRecibido)` — nunca se compara en texto plano.
- **Un solo uso:** al usarse exitosamente, el token se elimina físicamente de la BD.
- **Expiración:** el repositorio descarta tokens expirados en la misma query, y el caso de uso lo verifica para distinguir `RESET_TOKEN_INVALID` de `RESET_TOKEN_EXPIRED`.

## GET /dev/password-resets/last?email=...

**Bloque que lo produce:** [[../../features/AUTH/blocks/AUTH-B09-recuperacion-password]]
**Entorno:** Solo disponible en `local` y `testing`. No es parte del contrato del producto.

Devuelve el token de recuperación en texto plano más reciente para el email dado. El token se lee de Redis (`dev:password_reset:plain:{email}`) — no de la BD — porque la BD solo almacena el hash SHA-256 (irreversible).

### Request

Query parameter: `email` (string, required).

### Response (`200`)

```json
{
  "token": "string — token en texto plano de 64 caracteres hex"
}
```

### Errores

| Código HTTP | `error.code` | Cuándo ocurre |
|---|---|---|
| 422 | `VALIDATION_ERROR` | Falta el parámetro `email` |
| 404 | `NOT_FOUND` | No hay token vigente para ese email |
| 404 | — | Entorno production (la ruta no existe) |
