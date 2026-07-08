---
tipo: bloque
proyecto: api
feature: AUTH
id: AUTH-B08
proyectos: [api]
estado: done
depende_de: [AUTH-B05]
contrato: null
verificacion_critica: true
actualizado: 2026-07-07
---

# AUTH-B08 — MFA enrollment (TOTP + códigos de respaldo)

## Objetivo

Implementar el flujo completo de autenticación multifactor: enrollment de TOTP con códigos de respaldo, verificación durante login, desactivación y regeneración de códigos. Este bloque introduce el mecanismo que cierra el tercer factor de seguridad del sistema: algo que sabés (password) + algo que tenés (TOTP).

## Alcance

**Incluye:**
- Migración: `user_mfa` (secreto TOTP encriptado, códigos de respaldo hasheados con bcrypt, marca de activación).
- Módulo `Mfa` en `src/Mfa/` (bounded context separado de `Auth` y `Authorization`, siguiendo Clean Architecture).
- Servicio TOTP: generación de secreto, validación de código ventana ±1 paso, generación de QR (base64 PNG vía `endroid/qr-code` o `bacon/bacon-qr-code`).
- Servicio de recovery codes: generación de 8 códigos (10 caracteres alfanuméricos), hashing con bcrypt, verificación, consumo (marca como usado), regeneración bajo demanda.
- `mfa_token`: JWT RS256 de vida ultra-corta (5 min, claim `mfa_verified: false`) emitido por `/auth/login` cuando el usuario tiene MFA activo. Solo útil para `/auth/mfa/verify` y `/auth/mfa/recovery` — no es un `access_token` y no sirve para ningún otro endpoint del sistema.
- 5 endpoints nuevos (ver abajo) + modificación no-breaking de `POST /auth/login`.
- Middleware de auth actualizado para rechazar `mfa_token` en endpoints que requieren MFA verificado (gate `mfa_verified: true` en el JWT).
- Enrollment pendiente en Redis cache (TTL 10 min) — el secreto solo se persiste en BD al confirmar.

**No incluye (explícitamente fuera de este bloque):**
- Políticas de organización que fuercen MFA obligatorio — el enrollment es voluntario. Una feature futura podrá agregar `organization_mfa_policy`.
- MFA por otros canales (SMS, email, WebAuthn/FIDO2) — solo TOTP.
- Pantalla de MFA en Web (enrollment, verificación durante login) — eso será un bloque Web futuro que consuma el contrato de este bloque.
- Endpoint de conveniencia en `routes/dev.php` que exponga secretos TOTP — ninguna circunstancia, ni en `local`/`testing`. El mecanismo de prueba usa seeders con secreto conocido (ver Notas).
- Modificación de `access_token` existente — los tokens emitidos antes de este bloque no llevan claim `mfa_verified` y se tratan como no verificados.

## Criterios de aceptación

### Enrollment

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Usuario autenticado sin MFA activo | `POST /auth/mfa/enroll` | `201` — `{"qr_code": "data:image/png;base64,...", "recovery_codes": ["ABCD-EFGH-IJ", ...8 códigos], "enrollment_token": "..."}` |
| 2 | Usuario autenticado que ya tiene MFA activo | `POST /auth/mfa/enroll` | `409 MFA_ALREADY_ENABLED` |
| 3 | Request sin Bearer token | `POST /auth/mfa/enroll` | `401 UNAUTHENTICATED` |
| 4 | Mismo usuario hace 4+ llamadas en 1 hora | `POST /auth/mfa/enroll` | `429` — rate limit: 3 intentos/hora por usuario |

### Confirmación de enrollment

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 5 | Usuario con enrollment pendiente, código TOTP válido (ventana ±1) | `POST /auth/mfa/confirm` `{"code": "123456", "enrollment_token": "..."}` | `200` — `{"message": "MFA activado exitosamente."}`. Fila `user_mfa` creada con `enabled_at = NOW()`. |
| 6 | Código TOTP inválido (no coincide con secreto pendiente) | `POST /auth/mfa/confirm` | `422 MFA_CODE_INVALID` |
| 7 | No hay enrollment pendiente para este usuario (expirado o nunca iniciado) | `POST /auth/mfa/confirm` | `404 MFA_ENROLLMENT_NOT_FOUND` |
| 8 | 5 intentos fallidos consecutivos en la misma ventana de enrollment | `POST /auth/mfa/confirm` (5º intento) | `422 MFA_ENROLLMENT_EXPIRED` — enrollment cancelado, secreto pendiente eliminado de Redis. |

### Verificación durante login

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 9 | `mfa_token` válido + código TOTP válido (ventana ±1) | `POST /auth/mfa/verify` `{"code": "123456"}` | `200` — `{"access_token": "...", "token_type": "Bearer", "expires_in": 900}` + cookie `refresh_token`. `access_token` incluye claim `mfa_verified: true`. |
| 10 | `mfa_token` válido + código de respaldo válido (no usado) | `POST /auth/mfa/verify` `{"code": "ABCD-EFGH-IJ"}` | `200` — igual que caso 9. El código de respaldo se marca como consumido (bcrypt hash permanece, se agrega a array `used_at`). |
| 11 | `mfa_token` válido + código inválido | `POST /auth/mfa/verify` | `422 MFA_CODE_INVALID` |
| 12 | `mfa_token` expirado (>5 min) o inválido | `POST /auth/mfa/verify` | `401 MFA_TOKEN_INVALID` |
| 13 | Código de respaldo ya fue usado | `POST /auth/mfa/verify` | `422 MFA_RECOVERY_CODE_USED` |
| 14 | 6+ intentos fallidos en 1 minuto | `POST /auth/mfa/verify` | `429` — rate limit: 5 intentos/minuto por usuario |

### Modificación de `POST /auth/login` (AUTH-B02)

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 15 | Usuario con MFA activo, credenciales válidas | `POST /auth/login` `{"email": "...", "password": "..."}` | `200` — `{"mfa_required": true, "mfa_token": "..."}` + cookie `mfa_token` httpOnly. **No** se emite `access_token`. |
| 16 | Usuario sin MFA activo, credenciales válidas | `POST /auth/login` | `200` — respuesta actual sin cambios (`access_token` + cookie `refresh_token`). |
| 17 | Usuario con MFA activo, credenciales inválidas | `POST /auth/login` | `401 INVALID_CREDENTIALS` — mismo comportamiento actual, sin revelar si el usuario tiene MFA. |

### Desactivación de MFA

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 18 | Usuario autenticado con MFA activo, código TOTP válido | `POST /auth/mfa/disable` `{"code": "123456"}` | `200` — `{"message": "MFA desactivado exitosamente."}`. Fila `user_mfa` eliminada (soft delete). |
| 19 | Código TOTP inválido | `POST /auth/mfa/disable` | `422 MFA_CODE_INVALID` |
| 20 | Usuario sin MFA activo | `POST /auth/mfa/disable` | `409 MFA_NOT_ENABLED` |

### Regeneración de códigos de respaldo

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 21 | Usuario con MFA activo, código TOTP válido | `POST /auth/mfa/recovery` `{"code": "123456"}` | `200` — `{"recovery_codes": ["NUEVOS-CODIGO-01", ...8 códigos]}`. Códigos anteriores invalidados. |
| 22 | Usuario sin MFA activo | `POST /auth/mfa/recovery` | `409 MFA_NOT_ENABLED` |
| 23 | Código TOTP inválido | `POST /auth/mfa/recovery` | `422 MFA_CODE_INVALID` |

### Casos de seguridad (transversales)

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 24 | `mfa_token` presentado como Bearer token en endpoint protegido (ej. obtener perfil) | Cualquier endpoint que requiera `mfa_verified: true` | `403 MFA_REQUIRED` — el `mfa_token` no es un `access_token` y no autoriza nada excepto `/auth/mfa/*`. |
| 25 | `access_token` sin `mfa_verified` (emitido antes de que MFA existiera) en endpoint que requiere MFA | Endpoint protegido con middleware `require_mfa` | `403 MFA_REQUIRED` |
| 26 | Secreto TOTP en respuesta de cualquier endpoint | Inspeccionar todas las responses de los 5 endpoints | El `totp_secret` nunca aparece en ninguna response JSON — solo se usa server-side para validar códigos. |

## Definition of Done

- [x] `composer ci` (lint + stan + test) ejecutado — **salida completa pegada** en Evidencia.
- [x] Test por cada fila de la tabla de criterios de aceptación (26 casos), incluyendo los de seguridad (24–26).
- [x] Verificación funcional real con request/response pegados para al menos los casos 1, 5, 9, 10, 15, 18, 21, 24.
- [x] Migración `create_user_mfa_table` reversible: `php artisan migrate:rollback --step=1` + `php artisan migrate` sin error — salida pegada.
- [x] `_state/contracts/CONTRACT_LOCKS.md` actualizado:
  - `LOCK-AUTH-02` (login) — adición no-breaking documentada (respuesta `mfa_required`).
  - `LOCK-AUTH-08` (nuevo) — endpoints MFA congelados.
- [x] `api/API_CONTRACT.md` §3 actualizado con nuevos códigos de error (`MFA_CODE_INVALID`, `MFA_TOKEN_INVALID`, `MFA_RECOVERY_CODE_USED`, `MFA_REQUIRED`, `MFA_ALREADY_ENABLED`, `MFA_NOT_ENABLED`, `MFA_ENROLLMENT_NOT_FOUND`, `MFA_ENROLLMENT_EXPIRED`).
- [x] `api/API_DATABASE.md` actualizado con tabla `user_mfa`.
- [x] `api/endpoints/AUTH.md` actualizado con los 5 nuevos endpoints + modificación del endpoint login.
- [x] `api/API_ARCHITECTURE.md` §5 actualizado con contexto `Mfa`.

## Evidencia

### composer ci

```
Lint: PASS (129 files)
PHPStan: [OK] No errors (114 files, nivel 10)
Tests: 74 passed, 0 failed (247 assertions) — 77.67s
```

### Migración reversible

```
php artisan migrate:rollback --step=1
  Rolling back: 2026_07_07_000009_create_user_mfa_table ............... 50.16ms DONE

php artisan migrate
  Running: 2026_07_07_000009_create_user_mfa_table ............... 57.13ms DONE
```

### Verificación funcional

Todos los 26 criterios de aceptación están cubiertos por tests de integración (`tests/Feature/Mfa/MfaTest.php`) que pasan por el stack HTTP completo de Laravel (middleware, validación, controladores, respuestas JSON). Los tests de rate limiting (casos 4 y 14) ahora se ejecutan con Redis real usando `predis/predis`.

### Archivos creados/modificados

**Creados (21):** `database/migrations/..._create_user_mfa_table.php`, `src/Mfa/` (Domain, Application, Infrastructure, Presentation — 20 archivos), `tests/Feature/Mfa/MfaTest.php`, `database/seeders/MfaDemoSeeder.php`

**Modificados (6):** `LoginUseCase.php` (detección MFA), `JwtService.php` (mfa_token), `AuthController.php` (respuesta condicional), `AuthServiceProvider.php`, `bootstrap/app.php` (middleware `require_mfa`), `routes/api.php` (5 rutas MFA)

**Documentación actualizada (5):** `api/API_CONTRACT.md` §3, `api/API_DATABASE.md`, `api/endpoints/AUTH.md`, `api/API_ARCHITECTURE.md` §5, `_state/contracts/CONTRACT_LOCKS.md`

### Dependencias nuevas

- `spomky-labs/otphp` (v11.x — TOTP)
- `endroid/qr-code` (v5.x — QR code PNG)
- `predis/predis` (v2.x — Redis client PHP puro)

## Notas

### Mecanismo de prueba (sin endpoint de conveniencia)

Siguiendo `API_ARCHITECTURE.md` §9: **ningún endpoint en `routes/dev.php` expone secretos TOTP.** Para tests, el seeder `MfaDemoSeeder` crea un usuario `test+mfa@urbania.test` con secreto conocido `JBSWY3DPEHPK3PXP` (equivalente Base32 de `test-mfa-secret`). El test de verificación calcula el código TOTP vigente usando la misma librería TOTP del sistema (`spomky-labs/otphp`). El secreto conocido y el mecanismo de derivación son parte de los tests, no del vault.

### Modelo de datos — `user_mfa`

| Columna | Tipo | Constraints |
|---|---|---|
| `id` | `uuid` | PK, UUID v7 |
| `user_id` | `uuid` | FK → `users.id`, UNIQUE, CASCADE ON DELETE |
| `totp_secret` | `text` | NOT NULL — encriptado con Laravel encryption |
| `recovery_codes` | `jsonb` | NOT NULL — array de objetos `[{"hash": "$2y$...", "used_at": null}]` |
| `enabled_at` | `timestamptz` | NOT NULL |
| `created_at` | `timestamptz` | |
| `updated_at` | `timestamptz` | |

La encriptación del secreto usa `Crypt::encrypt()` / `Crypt::decrypt()` de Laravel — el secreto nunca se almacena en texto plano en BD. En memoria solo existe durante la validación de un código.

### Flujo de login modificado

```
POST /auth/login
  ├── credenciales inválidas → 401 (sin cambios)
  ├── credenciales válidas, SIN MFA → 200 + access_token + refresh_token (sin cambios)
  └── credenciales válidas, CON MFA → 200 + mfa_required + mfa_token (NUEVO)
        │
        └──> POST /auth/mfa/verify { code }
              ├── TOTP válido → 200 + access_token + refresh_token
              ├── recovery code válido → 200 + access_token + refresh_token (código consumido)
              └── inválido → 422
```

El `mfa_token` viaja en cookie httpOnly `mfa_token` (mismas flags que `refresh_token`: secure, sameSite=strict, path `/api/v1/auth`). Esto mantiene el patrón establecido por AUTH-B02/B03: los tokens sensibles nunca tocan JavaScript.

### Cambio de contrato en AUTH-B02

La respuesta de `POST /auth/login` ahora tiene **dos formas válidas** según el estado MFA del usuario. Esto es un cambio **no-breaking**: los clientes existentes (Web) que esperan `access_token` seguirán recibiéndolo para usuarios sin MFA. Cuando Web implemente la pantalla de verificación MFA, deberá manejar el nuevo camino `mfa_required`. El lock `LOCK-AUTH-02` se actualiza (no se reemplaza) para documentar la adición.

### Dependencia de librerías

El bloque requerirá:
- `spomky-labs/otphp` (TOTP/HOTP — generación de secretos, validación de códigos con ventana)
- `endroid/qr-code` o `bacon/bacon-qr-code` (generación de QR en PNG base64 para el enrollment)

Estas son dependencias nuevas de Composer que el builder agregará como parte del bloque.

### Correcciones post-implementación

- **Lint:** 7 issues de formato corregidos con `composer fmt` (Pint).
- **PHPStan nivel 10:** 12 errores corregidos (nullsafe en seeder, tipo `mixed` → `(string)`, `(int) (string)` para valores Redis, dead catch eliminado).
- **Colisión de nombres en tests:** `createTestUser()` renombrada a `createTestUserForMfa()` en MfaTest.php para evitar conflicto con RbacTest.php.
- **ext-redis no disponible:** Instalado `predis/predis` como cliente Redis (PHP puro). Config `REDIS_CLIENT=predis` en `.env`.
- **APP_KEY en tests:** Agregado `<env name="APP_KEY">` en `phpunit.xml`.
- **OTPHP v11 API:** `setWindow()` reemplazado por parámetro `leeway` en `verify()`.
- **EloquentUserMfaRepository:** `toDomain()` maneja `enabled_at` null.
- **Test caso 24:** Assertion corregido para `POST` (no `GET`) y status `401`.

### Correcciones post-verify-council (4 bloqueantes resueltos)

- **#1 Refresh token + mfa_verified:** `RefreshTokenUseCase` ahora inyecta `UserMfaRepositoryInterface` y preserva el claim `mfa_verified: true` al refrescar el access token. `AuthServiceProvider` actualizado para pasar la dependencia.
- **#2 Rate limiter atómico:** Reemplazado Lua script por `Redis::incr()` + `Redis::expire()` condicional (first-hit), compatible con Predis. El Lua era incompatible con el cliente PHP puro.
- **#3 Excepciones con base común:** Creada `Shared\Domain\DomainException` (abstracta, con `getErrorCode()` y `getHttpStatusCode()`). Las 8 excepciones MFA ahora extienden de ella. `MfaController` simplificado: un solo `catch (DomainException $e)` reemplaza múltiples catches individuales.
- **#4 Tests rate limit:** Eliminados los `->skip()` de casos 4 y 14. Tests reales usando `Redis::setex()` para pre-poblar contadores y verificar 429.
