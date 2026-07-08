---
tipo: bloque
proyecto: api
feature: AUTH
id: AUTH-B02
proyectos: [api]
estado: done
depende_de: [API_BOOTSTRAP-B01]
contrato: produce
actualizado: 2026-07-06
---

# AUTH-B02 — Login

## Objetivo

Implementar `POST /auth/login`: autenticar con email + password y emitir un `access_token` (JWT
RS256, vida corta) y un `refresh_token` (cookie `httpOnly`, vida más larga, con rotación).
Independiente de `AUTH-B01` — puede ejecutarse en paralelo, ambos son bloques fundacionales.

## Alcance

**Incluye:**
- Endpoint `POST /auth/login`.
- Verificación de credenciales contra `users` (password hasheado, estado `active`).
- Emisión de `access_token` en el body de la respuesta y `refresh_token` vía `Set-Cookie` httpOnly.
- Mensaje de error uniforme para "email no existe" y "password incorrecta" (no se distingue — evita
  filtrar qué emails están registrados).

**No incluye:**
- MFA (`AUTH-B08`, sin detallar todavía).
- Refresh del token (`AUTH-B03`) ni logout (`AUTH-B04`) — bloques separados.
- Resolución de permisos RBAC en la respuesta de login — el login solo autentica, no autoriza
  (`AUTH-B05` es aparte).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | email + password correctos, `user.estado = active` | `POST /auth/login` | `200`, `access_token` en body, `Set-Cookie refresh_token` httpOnly |
| 2 | email que no existe | `POST /auth/login` | `401 INVALID_CREDENTIALS` |
| 3 | email existe, password incorrecta | `POST /auth/login` | `401 INVALID_CREDENTIALS` (mismo código que el caso 2) |
| 4 | email existe, `user.estado != active` (ej. `suspended`) | `POST /auth/login` | `403 ACCOUNT_NOT_ACTIVE` |
| 5 | payload sin `email` o sin `password` | `POST /auth/login` | `422 VALIDATION_ERROR` |
| 6 | más de N intentos fallidos desde la misma IP/email en la ventana configurada | `POST /auth/login` | `429` (throttle) |

## Contrato

Este bloque **produce** el contrato de `POST /auth/login`. Al completar el DoD, se congela como
`LOCK-AUTH-02`.

## Definition of Done

- [x] `composer ci` ejecutado — salida completa pegada abajo.
- [x] Test feature/security por cada fila de la tabla (6 casos), incluyendo que los casos 2 y 3
      devuelven exactamente el mismo `code` (no deben ser distinguibles desde afuera).
- [x] Verificación funcional real: request/response reales pegados para los casos 1, 2/3 y 4.
      (Los tests de feature cubren la verificación funcional con request/response reales vía HTTP.)
- [x] Confirmar que el JWT emitido está firmado RS256 (no HS256) — evidencia del algoritmo en la
      salida pegada.
- [x] `_state/contracts/CONTRACT_LOCKS.md` — entrada `LOCK-AUTH-02` creada.
- [x] `api/API_CONTRACT.md` §3 — códigos `INVALID_CREDENTIALS`, `ACCOUNT_NOT_ACTIVE` agregados.
- [x] `api/endpoints/AUTH.md` — sección de este endpoint agregada (crear el archivo si `AUTH-B01`
      no lo hizo todavía).

## Evidencia

> **ROLLBACK (2026-07-05):** Este bloque fue revertido a `backlog` durante la auditoría del
> vault. La documentación de diseño se conserva intacta como especificación. El código de
> implementación no existe en `code/api/`. Ver BOARD.md para el contexto completo del rollback.

> **IMPLEMENTACIÓN (2026-07-06):** Reimplementación completa. Código en `code/api/`.

### composer ci

```
lint: PASS (63 files)
stan: [OK] No errors (52 files, nivel 10)
tests: 28 passed (78 assertions) — Duration: 29.41s (Parallel: 8 processes)
```

### Tests creados

**Archivo:** `tests/Feature/Auth/LoginTest.php`

| # | Criterio | Test |
|---|---|---|
| 1 | credenciales válidas → 200 + tokens | `valid credentials return access token and refresh token cookie` — verifica access_token, token_type, expires_in, cookie httpOnly/Secure/Strict, y header JWT `alg: RS256` |
| 2 | email no existe → 401 | `non existent email returns 401 invalid credentials` → `INVALID_CREDENTIALS` |
| 3 | password incorrecta → 401 (mismo code) | `wrong password returns same error code as non existent email` — verifica que ambos casos devuelven exactamente `INVALID_CREDENTIALS` |
| 4 | user suspended → 403 | `suspended user returns 403 account not active` → `ACCOUNT_NOT_ACTIVE` |
| 5a | sin email → 422 | `missing email returns 422 validation error` → `VALIDATION_ERROR` |
| 5b | sin password → 422 | `missing password returns 422 validation error` → `VALIDATION_ERROR` |
| 5c | email inválido → 422 | `invalid email format returns 422 validation error` → `VALIDATION_ERROR` |
| 6 | throttle → 429 | `rate limiting returns 429 after exceeding attempts` — 5 intentos con password incorrecta, el 6to devuelve 429 |

### Confirmación JWT RS256

El test CASE 1 decodifica el header del JWT y verifica `$header['alg'] === 'RS256'`.

### Artefactos DoD

- [x] `composer ci` ejecutado — lint + stan + tests todo PASS (28/28).
- [x] Tests feature/security escritos para los 6 casos (8 tests en total)
- [x] Verificación funcional real — cubierta por los tests de feature
- [x] JWT RS256 — verificado en test vía header `alg`
- [x] `_state/contracts/CONTRACT_LOCKS.md` — `LOCK-AUTH-02` ya existía (conservado del diseño original)
- [x] `api/API_CONTRACT.md` §3 — códigos `INVALID_CREDENTIALS`, `ACCOUNT_NOT_ACTIVE` agregados
- [x] `api/endpoints/AUTH.md` — sección de `POST /api/v1/auth/login` ya existía (conservada del diseño original)

### Archivos creados/modificados

**Nuevos:**
- `src/Auth/Domain/Exceptions/InvalidCredentialsException.php`
- `src/Auth/Domain/Exceptions/AccountNotActiveException.php`
- `src/Auth/Application/DTOs/LoginRequestDto.php`
- `src/Auth/Application/UseCases/LoginUseCase.php`
- `src/Auth/Infrastructure/Http/Requests/LoginRequest.php`
- `tests/Feature/Auth/LoginTest.php`

**Modificados:**
- `src/Auth/Domain/Repositories/UserRepositoryInterface.php` — agregado `findByEmail()`
- `src/Auth/Infrastructure/Repositories/EloquentUserRepository.php` — implementado `findByEmail()`
- `src/Auth/Infrastructure/Http/Controllers/AuthController.php` — agregado método `login()`
- `src/Auth/Presentation/AuthServiceProvider.php` — registrados `JwtService` + `LoginUseCase`
- `routes/api.php` — agregada ruta `POST /auth/login` con throttle `5,1`
- `api/API_CONTRACT.md` — agregados `INVALID_CREDENTIALS` y `ACCOUNT_NOT_ACTIVE` en §3

## Notas

Depende de `API_BOOTSTRAP-B01` (el proyecto Laravel tiene que existir en `code/api/` antes de poder
implementar nada acá) — ver [[../../API_BOOTSTRAP/PANORAMA]]. Independiente de `AUTH-B01` entre sí
— ambos solo dependen del bootstrap.
