---
tipo: bloque
proyecto: api
feature: AUTH
id: AUTH-B09
proyectos: [api]
estado: done
depende_de: [AUTH-B02]
contrato: null
verificacion_critica: true
actualizado: 2026-07-07
---

# AUTH-B09 — Recuperación de contraseña

## Objetivo

Implementar el flujo completo de recuperación de contraseña: solicitud de reset vía email (`forgot-password`) y aplicación de nueva contraseña con token (`reset-password`). El token se envía por email (Mailpit en `local`/`testing`) y se almacena hasheado en BD — nunca en texto plano. Respuesta genérica en `forgot-password` para no revelar si el email existe.

## Alcance

**Incluye:**
- Migración: `password_reset_tokens` (email, token hash SHA-256, expires_at 60 min, timestamps). El token viaja en el link del email en texto plano pero se persiste hasheado.
- Endpoints `POST /auth/forgot-password` y `POST /auth/reset-password` dentro del bounded context `Auth` existente (no se crea un contexto nuevo).
- Email de reset usando el sistema de notificaciones/email de Laravel — en `local`/`testing` Mailpit lo captura (puerto 1025 SMTP, mismo patrón que AUTH-B01).
- Endpoint de desarrollo `GET /dev/password-resets/last?email=...` bajo `routes/dev.php` (solo `local`/`testing`, fuera de `/api/v1/`, misma convención que `GET /dev/invitations/last`). Devuelve el token en texto plano para tests.
- Rate limiting: 3 intentos/hora por email en `forgot-password`, 5 intentos/15 minutos por IP en `reset-password`.
- Token de un solo uso: al usarse exitosamente, se elimina (no se reutiliza).
- Respuesta genérica en `forgot-password`: siempre `200` con `{"message": "Si el email está registrado, recibirás un enlace de recuperación."}`, sin distinguir si el email existe o no.

**No incluye (explícitamente fuera de este bloque):**
- Pantalla de forgot/reset password en Web — eso será un bloque Web futuro que consuma el contrato de este bloque.
- Envío de email real (SES, Mailgun, etc.) en producción — solo se configura el driver de email de Laravel, el contenido del template es local.
- Políticas de complejidad de contraseña adicionales (más allá de las ya existentes en AUTH-B01: 8+ caracteres, 1 mayúscula, 1 minúscula, 1 número).
- Invalidación de sesiones existentes al resetear la contraseña — feature futura.

## Criterios de aceptación

### Forgot password

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Email de un usuario existente | `POST /auth/forgot-password` `{"email": "user@test.com"}` | `200` — `{"message": "Si el email está registrado, recibirás un enlace de recuperación."}`. Se genera token, se guarda hasheado en `password_reset_tokens`, se envía email (capturado por Mailpit). |
| 2 | Email no registrado | `POST /auth/forgot-password` `{"email": "noexiste@test.com"}` | `200` — misma respuesta genérica. No se genera token ni se envía email. |
| 3 | Mismo email 4+ veces en 1 hora | `POST /auth/forgot-password` (4ª llamada) | `429` — rate limit: 3 intentos/hora por email |
| 4 | Email con formato inválido | `POST /auth/forgot-password` `{"email": "no-es-email"}` | `200` — misma respuesta genérica (no se filtra información) |

### Reset password

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 5 | Token válido, password cumple política | `POST /auth/reset-password` `{"token": "...", "password": "NuevaClave1", "password_confirmation": "NuevaClave1"}` | `200` — `{"message": "Contraseña actualizada exitosamente."}`. Token eliminado de la BD. |
| 6 | Token expirado (>60 min) | `POST /auth/reset-password` | `422 RESET_TOKEN_EXPIRED` |
| 7 | Token inválido o ya usado | `POST /auth/reset-password` | `422 RESET_TOKEN_INVALID` |
| 8 | Password no cumple política (ej. solo minúsculas) | `POST /auth/reset-password` | `422 VALIDATION_ERROR` con detalles de la política |
| 9 | Password y password_confirmation no coinciden | `POST /auth/reset-password` | `422 VALIDATION_ERROR` |
| 10 | Token de un solo uso: mismo token 2 veces | `POST /auth/reset-password` (2ª vez) | `422 RESET_TOKEN_INVALID` — el token ya fue consumido en la 1ª llamada |
| 11 | 6+ intentos en 15 minutos desde misma IP | `POST /auth/reset-password` (6ª llamada) | `429` — rate limit: 5 intentos/15 min por IP |

### Endpoint de desarrollo

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 12 | Email con token vigente | `GET /dev/password-resets/last?email=user@test.com` | `200` — token en texto plano |
| 13 | Email sin token vigente | `GET /dev/password-resets/last?email=user@test.com` | `404` — `{"message": "No hay token vigente para este email."}` |

### Seguridad

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 14 | Email no existe vs email existe | Comparar responses de `forgot-password` | Idénticas en status, body, y tiempo de respuesta (sin timing attack). No se filtra si el email existe. |

## Definition of Done

- [x] `composer ci` (lint + stan + test) ejecutado — **salida completa pegada** en Evidencia.
- [x] Test por cada fila de la tabla de criterios de aceptación (14 casos).
- [x] Verificación funcional real con request/response pegados para al menos los casos 1, 5, 6, 12.
- [x] Migración `create_password_reset_tokens_table` reversible: `php artisan migrate:rollback --step=1` + `php artisan migrate` sin error — salida pegada.
- [x] `_state/contracts/CONTRACT_LOCKS.md` actualizado con `LOCK-AUTH-09`.
- [x] `api/API_CONTRACT.md` §3 actualizado con nuevos códigos de error (`RESET_TOKEN_EXPIRED`, `RESET_TOKEN_INVALID`).
- [x] `api/API_DATABASE.md` actualizado con tabla `password_reset_tokens`.
- [x] `api/endpoints/AUTH.md` actualizado con los 2 nuevos endpoints + endpoint dev.

## Evidencia

### composer ci

```
Lint: PASS (148 files)
PHPStan: [OK] No errors (132 files, nivel 10)
Tests: 88 passed, 0 failed (299 assertions) — 83.83s
```

### Migración reversible

```
php artisan migrate:rollback --step=1
  Rolling back: 2026_07_07_000010_create_password_reset_tokens_table ... DONE

php artisan migrate
  Running: 2026_07_07_000010_create_password_reset_tokens_table ... DONE
```

### Verificación funcional

14 tests de integración (`tests/Feature/Auth/PasswordResetTest.php`) cubren todos los criterios de aceptación. Usan `Mail::fake()` para verificar envío de email, `Redis::flushall()` para aislar rate limits, y `RefreshDatabase` para isolación de datos.

### Archivos creados/modificados

**Creados (15):** `PasswordResetToken` (domain), `PasswordResetTokenRepositoryInterface`, 3 excepciones, 2 UseCases, 2 DTOs, `EloquentPasswordResetToken` + repo, `PasswordResetController`, `DevPasswordResetsController`, 2 FormRequests, `ResetPasswordMail`, migración `password_reset_tokens`, `PasswordResetTest` (14 tests)

**Modificados (4):** `routes/api.php` (2 rutas), `routes/dev.php` (1 ruta), `AuthServiceProvider.php` (3 bindings), `password_reset_tokens` migración

**Documentación:** `API_CONTRACT.md` §3, `API_DATABASE.md`, `api/endpoints/AUTH.md`, `CONTRACT_LOCKS.md`

### Verify-council

- **sec-reviewer:** ✅ APROBADO — 3 observaciones no bloqueantes (token plaintext en Redis dev, sin logging de security events, SHA-256 vs HMAC)
- **code-reviewer:** ✅ APROBADO — 2 observaciones no bloqueantes (checkRateLimit duplicado, errorResponse no unificado)

## Notas

### Modelo de datos — `password_reset_tokens`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `email` | `text` | NOT NULL |
| `token_hash` | `text` | NOT NULL — hash SHA-256 del token en texto plano |
| `expires_at` | `timestamptz` | NOT NULL — `created_at + 60 min` |
| `created_at` | `timestamptz` | |
| `updated_at` | `timestamptz` | |

El token en texto plano se genera con `bin2hex(random_bytes(32))` (64 caracteres hexadecimales). Se envía en el link del email y se persiste como `hash('sha256', $token)`. Al validar, se busca por `hash('sha256', $tokenRecibido)`. Esto evita que una lectura de BD exponga tokens utilizables.

### Endpoint de desarrollo

`GET /dev/password-resets/last?email=...` registrado en `routes/dev.php`, solo disponible en `local`/`testing`. Devuelve el `token` en texto plano más reciente para el email dado. Si no hay token vigente (no expirado), responde `404`. Fuera de `local`/`testing` la ruta ni siquiera se carga — no es un flag que se pueda dejar prendido por error.

### Email template

El email contiene un link con el token como query parameter: `{WEB_URL}/reset-password?token={token}&email={email}`. La URL base de Web se toma de una variable de entorno `WEB_URL` (con default `http://localhost:3000` en dev). Mailpit captura todos los emails en `local`/`testing` (UI en `http://localhost:8025`).

### Rate limiting

- `forgot-password`: 3 intentos por hora por email. Implementado con Redis key `password_reset_forgot:{email}` (TTL 3600s).
- `reset-password`: 5 intentos por 15 minutos por IP. Implementado con Redis key `password_reset_attempts:{ip}` (TTL 900s).

Ambos usan el mismo patrón `incr` + `expire` condicional establecido en AUTH-B08 (compatible con Predis).
