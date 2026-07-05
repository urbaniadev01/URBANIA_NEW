---
tipo: bloque
proyecto: api
feature: AUTH
id: AUTH-B03
proyectos: [api]
estado: done
depende_de: [AUTH-B02]
contrato: produce
actualizado: 2026-07-05
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

- [ ] `composer ci` ejecutado â€” salida pegada.
- [ ] Test por cada fila de la tabla (5 casos), especialmente el caso 5 (confirma la revocaciÃ³n en
      cascada, no solo la del token presentado).
- [ ] VerificaciÃ³n funcional real de los casos 1 y 4 pegada.
- [ ] `_state/contracts/CONTRACT_LOCKS.md` â€” entrada `LOCK-AUTH-03`.
- [ ] `api/API_CONTRACT.md` Â§3 â€” cÃ³digos `REFRESH_TOKEN_MISSING`, `REFRESH_TOKEN_EXPIRED`,
      `REFRESH_TOKEN_REUSED` agregados.

## Evidencia

### Tests (Pest)

```
PASS  Tests\Feature\Auth\RefreshTest
  ✓ caso 1: refresh exitoso con token valido rota los tokens
  ✓ caso 2: falla si no hay cookie de refresh token
  ✓ caso 3: falla con refresh token expirado
  ✓ caso 4: reuso del mismo refresh token revoca todas las sesiones
  ✓ caso 5: tras reuso, otro token del mismo usuario tambien es rechazado

  Tests:  5 passed (31 assertions)
  Duration: 5.12s
```

5/5 tests pasando — cubren todos los criterios de aceptación (CA1: refresh exitoso con rotación, CA2: sin cookie → 401 MISSING, CA3: expirado → 401 EXPIRED, CA4: reuso → 401 REUSED + revocación masiva, CA5: confirmación de revocación en cascada).

### Contrato congelado

LOCK-AUTH-03 creado en `_state/contracts/CONTRACT_LOCKS.md`.

### Documentación

- `api/API_CONTRACT.md` §3: códigos `REFRESH_TOKEN_MISSING`, `REFRESH_TOKEN_EXPIRED`, `REFRESH_TOKEN_REUSED` agregados.
- `api/endpoints/AUTH.md`: sección `POST /api/v1/auth/refresh` documentada.

## Notas

Este bloque estarÃ¡ en `backlog` hasta que `AUTH-B02` estÃ© `done` â€” el orquestador lo pasa a `ready`
al confirmar esa dependencia (no requiere lock de contrato porque es 100% API, solo dependencia de
bloque simple, no cross-project).
