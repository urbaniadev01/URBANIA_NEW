---
tipo: contrato
proyecto: api
actualizado: 2026-07-05
---

# API_CONTRACT — Convenciones REST (fuente de verdad de las reglas, no del catálogo)

> El catálogo de endpoints implementados vive distribuido en `api/endpoints/<FEATURE>.md` y se
> congela por bloque en `_state/contracts/CONTRACT_LOCKS.md` — este documento fija las **reglas**
> que todo endpoint, de cualquier feature, debe cumplir.

## 1. Versionado y base

Todas las rutas bajo `/api/v1/`. Un cambio incompatible de un endpoint ya congelado (lock activo)
requiere un bloque nuevo bajo el protocolo de [[../_system/04_CROSS_PROJECT]] §5 — nunca se
reversiona `/api/v1` completo por un solo endpoint.

## 2. Formato de error — único en todo el API

```json
{
  "error": {
    "code": "INVITATION_TOKEN_INVALID",
    "message": "El token de invitación no es válido o ya fue usado.",
    "trace_id": "01930000-0000-7000-8000-000000000000"
  }
}
```

- `code`: `SCREAMING_SNAKE_CASE`, estable — Web puede tomar decisiones de UI basadas en `code`, nunca
  en `message` (que es texto para humano y puede cambiar de redacción).
- `trace_id`: UUID v7 único de la request, presente en logs del servidor para correlación.
- Ningún endpoint devuelve un error con una forma distinta a esta — si un caso nuevo lo tienta, el
  código va aquí y el `code` se agrega a la tabla de §3.

## 3. Códigos de error — tabla maestra

> Se completa a medida que los bloques los introducen — cada `code` nuevo se agrega aquí como parte
> del DoD del bloque que lo creó (ver [[../_system/05_DEFINITION_OF_DONE]] §2).

| Código | HTTP | Significado |
|---|---|---|
| `VALIDATION_ERROR` | 422 | La request no pasó la validación (campos faltantes o inválidos) |
| `INVITATION_TOKEN_INVALID` | 403 | El token de invitación no existe, ya fue consumido, o está expirado |
| `EMAIL_ALREADY_REGISTERED` | 409 | El email de la invitación ya está asociado a un usuario existente |
| `INVALID_CREDENTIALS` | 401 | Email no existe o password incorrecta (mismo código en ambos casos — no distingue) |
| `ACCOUNT_NOT_ACTIVE` | 403 | El usuario existe pero su estado no es `active` |
| `REFRESH_TOKEN_MISSING` | 401 | No se envió la cookie `refresh_token` en la request |
| `REFRESH_TOKEN_EXPIRED` | 401 | El refresh token ha expirado — debe iniciar sesión de nuevo |
| `REFRESH_TOKEN_REUSED` | 401 | El refresh token ya fue usado — posible robo de sesión. Todas las sesiones del usuario fueron revocadas |
| `MFA_ALREADY_ENABLED` | 409 | MFA ya está activado para este usuario |
| `MFA_NOT_ENABLED` | 409 | MFA no está activado para este usuario |
| `MFA_CODE_INVALID` | 422 | El código MFA ingresado no es válido |
| `MFA_TOKEN_INVALID` | 401 | El token MFA no es válido o ha expirado |
| `MFA_RECOVERY_CODE_USED` | 422 | El código de respaldo ya fue utilizado |
| `MFA_ENROLLMENT_NOT_FOUND` | 404 | No hay un enrollment de MFA pendiente para este usuario |
| `MFA_ENROLLMENT_EXPIRED` | 422 | El enrollment de MFA ha expirado por demasiados intentos fallidos |
| `MFA_REQUIRED` | 403 | Se requiere verificación MFA para acceder a este recurso |
| `TOO_MANY_REQUESTS` | 429 | Rate limiting superado |
| `RESET_TOKEN_EXPIRED` | 422 | El token de recuperación de contraseña ha expirado |
| `RESET_TOKEN_INVALID` | 422 | El token de recuperación de contraseña no es válido o ya fue usado |

## 4. Paginación (para endpoints de listado)

Cursor-based por defecto (`?cursor=...&limit=...`), no offset — evita resultados inconsistentes
cuando la tabla cambia entre páginas. Respuesta envuelta:

```json
{ "data": [...], "meta": { "next_cursor": "..." } }
```

## 5. Rate limiting

Todo endpoint de autenticación (`login`, `register`, `forgot-password`) lleva throttle explícito por
IP + identificador (email/token) — se documenta el límite exacto en la tarjeta del bloque que lo
crea y en `api/endpoints/<FEATURE>.md`.

## 6. Cómo se agrega un endpoint nuevo

1. Se define en la tarjeta del bloque que lo produce (criterios de aceptación = casos de
   request/response, incluidos los negativos).
2. Al implementarlo, se documenta el detalle completo en `api/endpoints/<FEATURE>.md` (usar
   `_system/templates/API_ENDPOINT.md`).
3. Se congela en `_state/contracts/CONTRACT_LOCKS.md` como parte del DoD del bloque.
4. Si introduce un `code` de error nuevo, se agrega a la tabla de §3.
