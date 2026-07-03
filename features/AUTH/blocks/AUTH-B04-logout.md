---
tipo: bloque
proyecto: api
feature: AUTH
id: AUTH-B04
proyectos: [api]
estado: backlog
depende_de: [AUTH-B02]
contrato: produce
actualizado: 2026-07-03
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

- [ ] `composer ci` ejecutado — salida pegada.
- [ ] Test por cada fila (3 casos), especialmente el caso 3 (confirma revocación real contra
      `AUTH-B03`).
- [ ] Verificación funcional real del caso 1 y 3 pegada.
- [ ] `_state/contracts/CONTRACT_LOCKS.md` — entrada `LOCK-AUTH-04`.

## Evidencia

_Vacío._

## Notas

Depende de `AUTH-B02` (necesita sesiones emitidas para poder revocar algo) y en la práctica también
de que `AUTH-B03` exista para el caso 3 de verificación — si `AUTH-B03` todavía no está `done`
cuando se ejecuta este bloque, el caso 3 se verifica directamente contra la tabla de tokens en vez
de vía `/auth/refresh`, y se deja una nota aquí de que falta la verificación end-to-end.
