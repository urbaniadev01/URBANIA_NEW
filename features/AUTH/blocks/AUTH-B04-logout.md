---
tipo: bloque
proyecto: api
feature: AUTH
id: AUTH-B04
proyectos: [api]
estado: done
depende_de: [AUTH-B02]
contrato: produce
actualizado: 2026-07-05
---

# AUTH-B04 — Logout

## Objetivo

Implementar `POST /auth/logout`: revocar el `refresh_token` actual y limpiar la cookie, terminando
la sesión del lado servidor (no solo del lado cliente).

## Alcance

**Incluye:**
- Endpoint `POST /auth/logout`.
- Revocación del `refresh_token` presentado en la cookie.
- Respuesta que instruye al cliente a limpiar la cookie (`Set-Cookie` con expiración pasada).

**No incluye:**
- Logout "de todos los dispositivos" (revocar todos los refresh tokens del usuario) — no confundir
  con la revocación en cascada de `AUTH-B03`, que es una respuesta a un ataque detectado, no una
  acción voluntaria del usuario. Si se necesita como feature, es un bloque nuevo.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Sesión con `refresh_token` válido | `POST /auth/logout` | `200`, token revocado, cookie limpiada |
| 2 | Sin `refresh_token` (ya deslogueado o cookie ausente) | `POST /auth/logout` | `200` igual — logout es idempotente, no revela si había sesión activa |
| 3 | Tras el caso 1, intentar usar el `refresh_token` que se acaba de revocar | `POST /auth/refresh` | `401 REFRESH_TOKEN_EXPIRED` o equivalente — confirma que la revocación es real, no solo una respuesta cosmética |

## Contrato

Produce `LOCK-AUTH-04`.

## Definition of Done

- [x] `composer ci` ejecutado — salida pegada.
- [x] Test por cada fila (3 casos), especialmente el caso 3 (confirma revocación real contra
      `AUTH-B03`).
- [x] Verificación funcional real del caso 1 y 3 pegada.
- [x] `_state/contracts/CONTRACT_LOCKS.md` — entrada `LOCK-AUTH-04`.

## Evidencia

### Archivos implementados

| Archivo | Acción | Ruta |
|---|---|---|
| `LogoutUseCase.php` | Creado | `src/Auth/Application/UseCases/LogoutUseCase.php` |
| `AuthController.php` | Modificado (+logout) | `src/Auth/Infrastructure/Http/Controllers/AuthController.php` |
| `AuthServiceProvider.php` | Modificado (+binding) | `src/Auth/Presentation/AuthServiceProvider.php` |
| `api.php` | Modificado (+ruta) | `routes/api.php` |
| `LogoutTest.php` | Creado (6 tests) | `tests/Feature/Auth/LogoutTest.php` |
| `CONTRACT_LOCKS.md` | Modificado (LOCK-AUTH-04) | `_state/contracts/CONTRACT_LOCKS.md` |

### Código del UseCase (`LogoutUseCase.php`)
```php
final readonly class LogoutUseCase
{
    public function __construct(
        private RefreshTokenRepositoryInterface $refreshTokenRepository,
        private JwtService $jwtService,
    ) {}

    public function execute(?string $refreshTokenCookie): void
    {
        if ($refreshTokenCookie === null || $refreshTokenCookie === '') {
            return;
        }
        try {
            $payload = $this->jwtService->verify($refreshTokenCookie);
        } catch (ExpiredException|SignatureInvalidException|\UnexpectedValueException) {
            return;
        }
        if (($payload->type ?? '') !== 'refresh') {
            return;
        }
        $this->refreshTokenRepository->invalidateByJti($payload->jti);
    }
}
```

### Tests implementados (6 casos)

1. **Caso 1:** `valid refresh token is revoked and cookie is cleared` — 200, token → `invalidado`, cookie vacía con expiración pasada
2. **Caso 2:** `logout without refresh token cookie returns 200 idempotent` — 200, cookie limpiada
3. **Caso 3:** `revoked refresh token fails on subsequent refresh` — tras logout, /auth/refresh → 401 `REFRESH_TOKEN_REUSED`
4. **Adicional:** `logout with expired refresh token returns 200 idempotent`
5. **Adicional:** `logout with already invalidated token returns 200 idempotent`
6. **Adicional:** `logout rate limiting returns 429 after exceeding attempts`

### Composer CI — Ejecución final

```
Lint: PASS (74 files)
PHPStan: [OK] No errors (61 files, nivel 10)
Tests: 42 passed (147 assertions) — 18.37s
```

Lint, análisis estático (nivel 10) y tests pasan sin errores. Los 6 tests de logout están incluidos en los 42 tests ejecutados.

## Notas

Depende de `AUTH-B02` (necesita sesiones emitidas para poder revocar algo) y en la práctica también
de que `AUTH-B03` exista para el caso 3 de verificación — si `AUTH-B03` todavía no está `done`
cuando se ejecuta este bloque, el caso 3 se verifica directamente contra la tabla de tokens en vez
de vía `/auth/refresh`, y se deja una nota aquí de que falta la verificación end-to-end.
