---
tipo: referencia
proyecto: web
actualizado: 2026-07-03
---

# WEB_API_CLIENT — Cliente HTTP central

> Convenciones del único punto de acceso a la API. Ningún feature implementa su propio cliente
> HTTP — todos pasan por lo que este documento describe.

## 1. Responsabilidades del cliente central (`src/services/`)

- Adjunta el `Authorization: Bearer <access_token>` leyendo el token del store de Zustand en
  memoria — nunca de `localStorage`.
- Interceptor de `401`: intenta **una** vez `POST /auth/refresh` (que usa la cookie `httpOnly`
  automáticamente) y reintenta la request original. Si el refresh también falla, limpia el store y
  redirige a login.
- **La propia llamada a `/auth/refresh` está excluida de este interceptor** — de lo contrario un
  refresh fallido dispararía un loop de reintento infinito.
- Traduce toda respuesta de error al formato único de [[../api/API_CONTRACT]] §2 — el código que
  consume la respuesta trabaja siempre con `{ code, message, trace_id }`, nunca con la forma cruda
  de Axios/fetch.

## 2. Convención por feature

Cada `features/<nombre>/api/` define:
- Las funciones de request (usan el cliente central, nunca `axios`/`fetch` directo).
- Los hooks de TanStack Query que las envuelven (`useLoginMutation`, `useUserQuery`, etc.).
- Los tipos de request/response — deben coincidir exactamente con el lock congelado en
  `_state/contracts/CONTRACT_LOCKS.md` para el endpoint que consumen. Si no coinciden, es una señal
  de que el lock cambió y el bloque de Web necesita actualizarse (ver
  [[../_system/04_CROSS_PROJECT]] §5).

## 3. staleTime / cache

Por defecto, `staleTime` conservador (los datos de un residente/condominio no cambian a cada
segundo) — cada hook de query documenta su propio `staleTime` si se aparta del default, con la
razón, en el propio archivo del hook (no aquí — este documento es de convención, no de catálogo).

## 4. Qué NO va aquí

El detalle de qué endpoints existen y su forma exacta vive en `api/endpoints/<FEATURE>.md` y en
`_state/contracts/CONTRACT_LOCKS.md` — este documento es solo la convención de cómo Web habla con la
API, no un índice de endpoints.
