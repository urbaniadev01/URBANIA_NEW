---
tipo: bloque
proyecto: api
feature: AUTH
id: AUTH-B03
proyectos: [api]
estado: backlog
depende_de: [AUTH-B02]
contrato: produce
actualizado: 2026-07-03
---

# AUTH-B03 — Refresh de sesión

## Objetivo

Implementar `POST /auth/refresh`: renovar `access_token` usando el `refresh_token` de la cookie
httpOnly, con rotación (cada uso invalida el token usado y emite uno nuevo) y detección de reuso.

## Alcance

**Incluye:**
- Endpoint `POST /auth/refresh`, lee el `refresh_token` de la cookie (no del body).
- Rotación: el `refresh_token` usado se invalida; se emite un `access_token` + `refresh_token`
  nuevos.
- Detección de reuso: si el `refresh_token` presentado ya fue invalidado antes (usado dos veces), se
  trata como señal de robo de sesión — se revocan **todos** los refresh tokens activos de ese
  usuario, no solo el presentado.

**No incluye:**
- Login (`AUTH-B02`, ya resuelto) ni logout (`AUTH-B04`).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | `refresh_token` válido y vigente | `POST /auth/refresh` | `200`, nuevo `access_token` + nuevo `Set-Cookie refresh_token`, el anterior queda invalidado |
| 2 | Sin cookie de `refresh_token` | `POST /auth/refresh` | `401 REFRESH_TOKEN_MISSING` |
| 3 | `refresh_token` expirado | `POST /auth/refresh` | `401 REFRESH_TOKEN_EXPIRED` |
| 4 | `refresh_token` ya usado anteriormente (reuso) | `POST /auth/refresh` | `401 REFRESH_TOKEN_REUSED`, y **todos** los refresh tokens del usuario quedan revocados |
| 5 | Tras el caso 4, intentar usar cualquier otro refresh token previamente válido de ese usuario | `POST /auth/refresh` | `401 REFRESH_TOKEN_EXPIRED` o equivalente — confirma que la revocación masiva ocurrió de verdad |

## Contrato

Produce `LOCK-AUTH-03`.

## Definition of Done

- [ ] `composer ci` ejecutado — salida pegada.
- [ ] Test por cada fila de la tabla (5 casos), especialmente el caso 5 (confirma la revocación en
      cascada, no solo la del token presentado).
- [ ] Verificación funcional real de los casos 1 y 4 pegada.
- [ ] `_state/contracts/CONTRACT_LOCKS.md` — entrada `LOCK-AUTH-03`.
- [ ] `api/API_CONTRACT.md` §3 — códigos `REFRESH_TOKEN_MISSING`, `REFRESH_TOKEN_EXPIRED`,
      `REFRESH_TOKEN_REUSED` agregados.

## Evidencia

_Vacío._

## Notas

Este bloque estará en `backlog` hasta que `AUTH-B02` esté `done` — el orquestador lo pasa a `ready`
al confirmar esa dependencia (no requiere lock de contrato porque es 100% API, solo dependencia de
bloque simple, no cross-project).
