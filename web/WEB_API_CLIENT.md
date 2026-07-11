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

## 5. Hooks de TanStack Query por feature

Cada feature expone sus hooks en `features/<nombre>/api/`. A continuación el índice de hooks
implementados:

### Auth (`features/auth/api/`)

| Archivo | Hooks exportados | Endpoint |
|---|---|---|
| `login.ts` | `useLoginMutation` | `POST /auth/login` (LOCK-AUTH-02) |
| `register.ts` | `useRegisterMutation` | `POST /auth/register` (LOCK-AUTH-01) |
| `mfa-verify.ts` | `useMfaVerifyMutation` | `POST /auth/mfa/verify` (LOCK-AUTH-08) |
| `mfa-enroll.ts` | `useMfaEnrollMutation`, `useMfaConfirmMutation`, `useMfaDisableMutation`, `useMfaRecoveryMutation` | `POST /auth/mfa/enroll`, `/auth/mfa/confirm`, `/auth/mfa/disable`, `/auth/mfa/recovery` (LOCK-AUTH-08) |
| `forgot-password.ts` | `useForgotPasswordMutation` | `POST /auth/forgot-password` (LOCK-AUTH-09) |
| `reset-password.ts` | `useResetPasswordMutation` | `POST /auth/reset-password` (LOCK-AUTH-09) |

### Dashboard (`features/dashboard/hooks/`)

| Archivo | Hooks exportados | Endpoint |
|---|---|---|
| `useUserQuery.ts` | `useUserQuery` | `GET /auth/me` (LOCK-AUTH-10) |

### Propiedades — Catálogos (`features/propiedades/api/`)

| Archivo | Hooks exportados | Endpoint |
|---|---|---|
| `property-types.ts` | `usePropertyTypesQuery`, `useCreatePropertyTypeMutation`, `useUpdatePropertyTypeMutation`, `useDeletePropertyTypeMutation` | `GET/POST/PATCH/DELETE /property-types` (LOCK-PROPIEDADES-01) |
| `property-statuses.ts` | `usePropertyStatusesQuery`, `useCreatePropertyStatusMutation`, `useUpdatePropertyStatusMutation`, `useDeletePropertyStatusMutation` | `GET/POST/PATCH/DELETE /property-statuses` (LOCK-PROPIEDADES-01) |
| `condominiums.ts` | `useCondominiumsQuery`, `useCondominioQuery`, `useCreateCondominioMutation`, `useUpdateCondominioMutation`, `useDeleteCondominioMutation` | `GET/POST /condominiums`, `GET/PATCH/DELETE /condominiums/{id}` (LOCK-PROPIEDADES-02) |
| `towers.ts` | `useTorresQuery`, `useTorreQuery`, `useCreateTorreMutation`, `useUpdateTorreMutation`, `useDeleteTorreMutation` | `GET/POST /condominiums/{id}/towers`, `GET/PATCH/DELETE /towers/{id}` (LOCK-PROPIEDADES-02) |
| `properties.ts` | `usePropertiesInfiniteQuery`, `useCreatePropertyMutation`, `useUpdatePropertyMutation`, `useDeletePropertyMutation`, `useBatchUpdateStatusMutation`, `useBatchDeleteMutation`, `flattenProperties` | `GET /condominiums/{id}/properties`, `POST /condominiums/{id}/properties`, `PATCH/DELETE /properties/{id}` (LOCK-PROPIEDADES-03) |
| `coefficients.ts` | `useCondominioTreeQuery`, `usePropertyCoefficientsQuery`, `useBatchPropertyCoefficientsQueries`, `useUpdateCoefficientsMutation` | `GET /condominiums/{id}/tree`, `GET /properties/{id}/coefficients`, `PATCH /condominiums/{id}/coefficients` (LOCK-PROPIEDADES-04) |

### Directorio (`features/directorio/api/`)

| Archivo | Hooks exportados | Endpoint |
|---|---|---|
| `occupant-types.ts` | `useOccupantTypesQuery`, `useCreateOccupantTypeMutation`, `useUpdateOccupantTypeMutation`, `useDeleteOccupantTypeMutation` | `GET/POST/PATCH/DELETE /occupant-types` (LOCK-DIRECTORIO-01) |
| `contacts.ts` | `useContactsQuery`, `useContactPropertiesQuery`, `useCreateContactMutation`, `useUpdateContactMutation`, `useDeleteContactMutation` | `GET/POST/PATCH/DELETE /contacts`, `GET /contacts/{id}/properties` (LOCK-DIRECTORIO-02) |
| `me-contact.ts` | `useMeContactQuery`, `useUpdateMeContactMutation` | `GET/PATCH /me/contact` (LOCK-DIRECTORIO-02) |
| `property-occupants.ts` | `usePropertyOccupantsQuery`, `useAssignOccupantMutation`, `useUpdatePropertyOccupantMutation`, `useUnassignOccupantMutation` | `GET/POST /properties/{id}/occupants`, `PATCH/DELETE /property-occupants/{id}` (LOCK-DIRECTORIO-03) |

El detalle de qué endpoints existen y su forma exacta vive en `api/endpoints/<FEATURE>.md` y en
`_state/contracts/CONTRACT_LOCKS.md` — este documento es solo la convención de cómo Web habla con la
API, no un índice de endpoints.
