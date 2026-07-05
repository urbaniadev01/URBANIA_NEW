---
tipo: referencia
proyecto: api
feature: AUTH
actualizado: 2026-07-04
---

# Endpoints: AUTH

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

### Response — éxito (`200`)

```json
{
  "access_token": "string — JWT RS256 firmado, vida corta (15 min por defecto)",
  "token_type": "Bearer",
  "expires_in": 900
}
```

Además, se establece una cookie `refresh_token` httpOnly, secure, sameSite=strict, con path `/api/v1/auth`.

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
