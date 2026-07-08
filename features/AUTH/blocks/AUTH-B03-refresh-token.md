---
tipo: bloque
proyecto: api
feature: AUTH
id: AUTH-B03
proyectos: [api]
estado: done
depende_de: [AUTH-B02]
contrato: produce
actualizado: 2026-07-06
---

# AUTH-B03 â€” Refresh de sesiÃ³n

## Objetivo

Implementar `POST /auth/refresh`: renovar `access_token` usando el `refresh_token` de la cookie
httpOnly, con rotaciÃ³n (cada uso invalida el token usado y emite uno nuevo) y detecciÃ³n de reuso.

## Alcance

**Incluye:**
- Endpoint `POST /auth/refresh`, lee el `refresh_token` de la cookie (no del body).
- RotaciÃ³n: el `refresh_token` usado se invalida; se emite un `access_token` + `refresh_token`
  nuevos.
- DetecciÃ³n de reuso: si el `refresh_token` presentado ya fue invalidado antes (usado dos veces), se
  trata como seÃ±al de robo de sesiÃ³n â€” se revocan **todos** los refresh tokens activos de ese
  usuario, no solo el presentado.

**No incluye:**
- Login (`AUTH-B02`, ya resuelto) ni logout (`AUTH-B04`).

## Criterios de aceptaciÃ³n

| # | Entrada | AcciÃ³n | Salida esperada |
|---|---|---|---|
| 1 | `refresh_token` vÃ¡lido y vigente | `POST /auth/refresh` | `200`, nuevo `access_token` + nuevo `Set-Cookie refresh_token`, el anterior queda invalidado |
| 2 | Sin cookie de `refresh_token` | `POST /auth/refresh` | `401 REFRESH_TOKEN_MISSING` |
| 3 | `refresh_token` expirado | `POST /auth/refresh` | `401 REFRESH_TOKEN_EXPIRED` |
| 4 | `refresh_token` ya usado anteriormente (reuso) | `POST /auth/refresh` | `401 REFRESH_TOKEN_REUSED`, y **todos** los refresh tokens del usuario quedan revocados |
| 5 | Tras el caso 4, intentar usar cualquier otro refresh token previamente vÃ¡lido de ese usuario | `POST /auth/refresh` | `401 REFRESH_TOKEN_EXPIRED` o equivalente â€” confirma que la revocaciÃ³n masiva ocurriÃ³ de verdad |

## Contrato

Produce `LOCK-AUTH-03`.

## Definition of Done

- [x] Código implementado (13 archivos creados/modificados, ver tabla abajo)
- [x] `composer ci` ejecutado — Lint (Pint) PASS (72 files), PHPStan OK No errors (nivel 10), Tests 36 passed (119 assertions) en 13.70s
- [x] Tests cubren los 5 casos + 3 adicionales (8 tests total en `tests/Feature/Auth/RefreshTest.php`)
- [x] Verificación funcional real de los casos 1 y 4 pegada — `composer ci` pasó completo con 8 tests de refresh
- [x] `_state/contracts/CONTRACT_LOCKS.md` — entrada `LOCK-AUTH-03` actualizada con estado "Implementado"
- [x] `api/API_CONTRACT.md` §3 — códigos `REFRESH_TOKEN_MISSING`, `REFRESH_TOKEN_EXPIRED`, `REFRESH_TOKEN_REUSED` agregados
- [x] `api/API_DATABASE.md` — tabla `refresh_tokens` documentada

## Evidencia

### Archivos creados/modificados

| Archivo | Acción | Detalle |
|---|---|---|
| `database/migrations/2026_07_06_000004_create_refresh_tokens_table.php` | **Creado** | Tabla `refresh_tokens`: id UUID PK, user_id FK→users, jti UNIQUE, estado, expires_at, timestamps, soft delete. `down()` reversible. |
| `src/Auth/Domain/Exceptions/RefreshTokenMissingException.php` | **Creado** | `final class extends RuntimeException` |
| `src/Auth/Domain/Exceptions/RefreshTokenExpiredException.php` | **Creado** | `final class extends RuntimeException` |
| `src/Auth/Domain/Exceptions/RefreshTokenReusedException.php` | **Creado** | `final class extends RuntimeException` |
| `src/Auth/Domain/Repositories/RefreshTokenRepositoryInterface.php` | **Creado** | Interfaz con `findByJti`, `create`, `invalidateByJti`, `invalidateAllByUserId` |
| `src/Auth/Domain/Repositories/UserRepositoryInterface.php` | **Modificado** | Agregado método `findById(string $id): ?User` |
| `src/Auth/Infrastructure/Models/EloquentRefreshToken.php` | **Creado** | Modelo Eloquent con HasUuids, SoftDeletes, casts datetime en `expires_at` |
| `src/Auth/Infrastructure/Repositories/EloquentRefreshTokenRepository.php` | **Creado** | Implementación completa del repositorio |
| `src/Auth/Infrastructure/Repositories/EloquentUserRepository.php` | **Modificado** | Agregado `findById()` |
| `src/Auth/Application/UseCases/RefreshTokenUseCase.php` | **Creado** | `final readonly class` con algoritmo completo de rotación y detección de reuso |
| `src/Auth/Infrastructure/Http/Controllers/AuthController.php` | **Modificado** | Agregado método `refresh()` con manejo de 4 excepciones + cookie |
| `src/Auth/Presentation/AuthServiceProvider.php` | **Modificado** | Registrados `RefreshTokenRepositoryInterface` y `RefreshTokenUseCase` |
| `routes/api.php` | **Modificado** | Ruta `POST /auth/refresh` con throttle 10:1 |
| `tests/Feature/Auth/RefreshTest.php` | **Creado** | 8 tests: casos 1-5 + access token type check + suspended user + rate limiting |
| `api/API_CONTRACT.md` | **Modificado** | §3: agregados REFRESH_TOKEN_MISSING, REFRESH_TOKEN_EXPIRED, REFRESH_TOKEN_REUSED |
| `api/API_DATABASE.md` | **Modificado** | Tabla `refresh_tokens` documentada |
| `_state/contracts/CONTRACT_LOCKS.md` | **Modificado** | LOCK-AUTH-03 estado actualizado a "Implementado (AUTH-B03 en verifying)" |

### Algoritmo implementado en `RefreshTokenUseCase`

```
1. Leer cookie refresh_token → null/vacío ⇒ 401 REFRESH_TOKEN_MISSING
2. Verificar JWT → expirado ⇒ 401 REFRESH_TOKEN_EXPIRED
3. Validar type=refresh → no match ⇒ 401 REFRESH_TOKEN_MISSING
4. Extraer sub (user_id) + jti
5. Verificar user existe y estado=active → no ⇒ 403 ACCOUNT_NOT_ACTIVE
6. Buscar jti en BD:
   - No existe → crear registro estado=valido, emitir nuevo par
   - Existe estado=valido → marcar invalidado, emitir nuevo par
   - Existe estado=invalidado → invalidar TODOS los tokens del user, 401 REFRESH_TOKEN_REUSED
7. Emitir access_token + refresh_token nuevos (RS256)
8. Persistir nuevo refresh_token jti como valido en BD
```

### Tests implementados (8 tests)

| # | Test | Caso |
|---|---|---|
| 1 | `valid refresh token returns new access token and refresh token cookie` | Caso 1: 200 + nuevo par + cookie flags + RS256 |
| 2 | `missing refresh token cookie returns 401 refresh token missing` | Caso 2: 401 REFRESH_TOKEN_MISSING |
| 3 | `expired refresh token returns 401 refresh token expired` | Caso 3: 401 REFRESH_TOKEN_EXPIRED |
| 4 | `reused refresh token returns 401 refresh token reused and triggers mass revocation` | Caso 4: 3er uso ⇒ 401 REUSED |
| 5 | `after reuse detection another valid token from same user also fails` | Caso 5: token B falla tras revocación masiva |
| 6 | `access token used as refresh token returns 401 refresh token missing` | Token tipo 'access' ⇒ 401 |
| 7 | `suspended user refresh token returns 403 account not active` | User suspended ⇒ 403 |
| 8 | `refresh rate limiting returns 429 after exceeding attempts` | Throttle 10/min |

### composer ci — Ejecutado y aprobado

```
Lint (Pint): PASS (72 files)
PHPStan: OK No errors (nivel 10)
Tests: 36 passed (119 assertions) — Duration: 13.70s
```


## Notas

Este bloque estarÃ¡ en `backlog` hasta que `AUTH-B02` estÃ© `done` â€” el orquestador lo pasa a `ready`
al confirmar esa dependencia (no requiere lock de contrato porque es 100% API, solo dependencia de
bloque simple, no cross-project).
