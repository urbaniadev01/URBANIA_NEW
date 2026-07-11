---
tipo: contrato
proyecto: api
actualizado: 2026-07-08
---

# API_CONTRACT — Convenciones REST (fuente de verdad de las reglas, no del catálogo)

> El catálogo de endpoints implementados vive distribuido en `api/endpoints/<FEATURE>.md` y se
> congela por bloque en `_state/contracts/CONTRACT_LOCKS.md` — este documento fija las **reglas**
> que todo endpoint, de cualquier feature, debe cumplir.

## 1. Versionado y base

Todas las rutas bajo `/api/v1/`. Un cambio incompatible de un endpoint ya congelado (lock activo)
requiere un bloque nuevo bajo el protocolo de [[../_system/04_CROSS_PROJECT]] §5 — nunca se
reversiona `/api/v1` completo por un solo endpoint.

## 2. Formato de error — único en todo el API

```json
{
  "error": {
    "code": "INVITATION_TOKEN_INVALID",
    "message": "El token de invitación no es válido o ya fue usado.",
    "trace_id": "01930000-0000-7000-8000-000000000000"
  }
}
```

- `code`: `SCREAMING_SNAKE_CASE`, estable — Web puede tomar decisiones de UI basadas en `code`, nunca
  en `message` (que es texto para humano y puede cambiar de redacción).
- `trace_id`: UUID v7 único de la request, presente en logs del servidor para correlación.
- Ningún endpoint devuelve un error con una forma distinta a esta — si un caso nuevo lo tienta, el
  código va aquí y el `code` se agrega a la tabla de §3.

## 3. Códigos de error — tabla maestra

> Se completa a medida que los bloques los introducen — cada `code` nuevo se agrega aquí como parte
> del DoD del bloque que lo creó (ver [[../_system/05_DEFINITION_OF_DONE]] §2).

| Código | HTTP | Significado |
|---|---|---|
| `VALIDATION_ERROR` | 422 | La request no pasó la validación (campos faltantes o inválidos) |
| `INVITATION_TOKEN_INVALID` | 403 | El token de invitación no existe, ya fue consumido, o está expirado |
| `EMAIL_ALREADY_REGISTERED` | 409 | El email de la invitación ya está asociado a un usuario existente |
| `INVALID_CREDENTIALS` | 401 | Email no existe o password incorrecta (mismo código en ambos casos — no distingue) |
| `ACCOUNT_NOT_ACTIVE` | 403 | El usuario existe pero su estado no es `active` |
| `REFRESH_TOKEN_MISSING` | 401 | No se envió la cookie `refresh_token` en la request |
| `REFRESH_TOKEN_EXPIRED` | 401 | El refresh token ha expirado — debe iniciar sesión de nuevo |
| `REFRESH_TOKEN_REUSED` | 401 | El refresh token ya fue usado — posible robo de sesión. Todas las sesiones del usuario fueron revocadas |
| `MFA_ALREADY_ENABLED` | 409 | MFA ya está activado para este usuario |
| `MFA_NOT_ENABLED` | 409 | MFA no está activado para este usuario |
| `MFA_CODE_INVALID` | 422 | El código MFA ingresado no es válido |
| `MFA_TOKEN_INVALID` | 401 | El token MFA no es válido o ha expirado |
| `MFA_RECOVERY_CODE_USED` | 422 | El código de respaldo ya fue utilizado |
| `MFA_ENROLLMENT_NOT_FOUND` | 404 | No hay un enrollment de MFA pendiente para este usuario |
| `MFA_ENROLLMENT_EXPIRED` | 422 | El enrollment de MFA ha expirado por demasiados intentos fallidos |
| `MFA_REQUIRED` | 403 | Se requiere verificación MFA para acceder a este recurso |
| `TOO_MANY_REQUESTS` | 429 | Rate limiting superado |
| `RESET_TOKEN_EXPIRED` | 422 | El token de recuperación de contraseña ha expirado |
| `RESET_TOKEN_INVALID` | 422 | El token de recuperación de contraseña no es válido o ya fue usado |
| `SYSTEM_CATALOG_READONLY` | 403 | Intento de modificar o eliminar un catálogo del sistema (`organization_id IS NULL`) — regla R-08 de PROPIEDADES |
| `PROPERTY_TYPE_IN_USE` | 409 | El tipo de propiedad está referenciado por propiedades activas y no puede eliminarse |
| `PROPERTY_STATUS_IN_USE` | 409 | El estado de propiedad está referenciado por propiedades activas y no puede eliminarse |
| `PROPERTY_TYPE_NAME_DUPLICATE` | 409 | Ya existe un tipo de propiedad con ese nombre en la misma organización |
| `PROPERTY_STATUS_NAME_DUPLICATE` | 409 | Ya existe un estado de propiedad con ese nombre en la misma organización |
| `CONDOMINIUM_NAME_DUPLICATE` | 409 | Ya existe un condominio con ese nombre en la misma organización |
| `TOWER_NAME_DUPLICATE` | 409 | Ya existe una torre con ese nombre en el mismo condominio |
| `CONDOMINIUM_HAS_TOWERS` | 409 | El condominio tiene torres activas y no puede eliminarse |
| `CONDOMINIUM_HAS_PROPERTIES` | 409 | El condominio tiene propiedades activas y no puede eliminarse |
| `TOWER_HAS_PROPERTIES` | 409 | La torre tiene propiedades activas y no puede eliminarse |
| `CONDOMINIUM_NOT_FOUND` | 404 | El condominio no existe o no pertenece a la organización del usuario |
| `TOWER_NOT_FOUND` | 404 | La torre no existe o no pertenece a la organización del usuario |
| `PROPERTY_NOT_FOUND` | 404 | La propiedad no existe, pertenece a otra organización, o está fuera del scope del usuario |
| `PROPERTY_CODE_DUPLICATE` | 409 | Ya existe una unidad con ese código en el mismo condominio |
| `TOWER_CONDOMINIUM_MISMATCH` | 422 | La torre no pertenece al condominio de la unidad |
| `PROPERTY_HAS_OCCUPANTS` | 409 | La unidad tiene ocupantes activos y no puede eliminarse |
| `FORBIDDEN` | 403 | El usuario no tiene permisos para acceder a este recurso |
| `COEFFICIENT_OUT_OF_RANGE` | 422 | El valor del coeficiente está fuera del rango permitido (0–1) |
| `COEFFICIENT_INVALID_TYPE` | 422 | El tipo de coeficiente no pertenece al set cerrado (R-06-bis: `copropiedad`, `parqueadero`, `deposito`, `mantenimiento`) |
| `PROPERTY_NOT_IN_CONDOMINIUM` | 422 | La unidad especificada no pertenece al condominio del path |
| `COEFFICIENT_SUM_MISMATCH` | — | Warning no bloqueante en `200`: la suma de coeficientes de copropiedad no es 1.0 (R-06). Ver §4-bis. |
| `OCCUPANT_TYPE_NAME_DUPLICATE` | 409 | Ya existe un tipo de ocupante con ese nombre en la misma organización |
| `OCCUPANT_TYPE_IN_USE` | 409 | El tipo de ocupante está referenciado por ocupantes (`property_occupants`) activos y no puede eliminarse |
| `OCCUPANT_TYPE_NOT_FOUND` | 404 | El tipo de ocupante no existe o pertenece a otra organización |
| `CONTACT_HAS_OCCUPATIONS` | 409 | El contacto tiene ocupaciones activas (`property_occupants`) y no puede eliminarse |
| `CONTACT_NOT_FOUND` | 404 | El contacto no existe, pertenece a otra organización, o está fuera del scope del actor |
| `OCCUPANT_ASSIGNMENT_DUPLICATE` | 409 | Ya existe una asignación activa para el mismo `(contact_id, property_id, occupant_type_id)` |

## 4. Paginación (para endpoints de listado)

Cursor-based por defecto (`?cursor=...&limit=...`), no offset — evita resultados inconsistentes
cuando la tabla cambia entre páginas. Respuesta envuelta:

```json
{ "data": [...], "meta": { "next_cursor": "..." } }
```

## 4-bis. Warnings no bloqueantes (para respuestas `200` con advertencias)

Cuando una operación se completa con éxito pero hay una condición de negocio que el cliente debe
mostrarle al usuario sin bloquear el guardado (ej. R-06 de `PROPIEDADES`: suma de coeficientes ≠
100%), la respuesta `200` normal se extiende con un arreglo `warnings`:

```json
{
  "data": { ... },
  "warnings": [
    { "code": "COEFFICIENT_SUM_MISMATCH", "detail": { "condominium_id": "...", "sum": 0.97 } }
  ]
}
```

- `warnings` es siempre un array, vacío u omitido si no hay advertencias — nunca cambia la forma de
  `data`.
- `code`: mismo formato que los errores de §2 (`SCREAMING_SNAKE_CASE`, estable), pero **no** implica
  fallo — el HTTP status sigue siendo `2xx`.
- `detail`: objeto libre por `code`, documentado en `api/endpoints/<FEATURE>.md` junto con el
  endpoint que lo emite.
- Un endpoint que nunca tiene advertencias no necesita incluir el campo `warnings` en absoluto.

## 5. Rate limiting

Todo endpoint de autenticación (`login`, `register`, `forgot-password`) lleva throttle explícito por
IP + identificador (email/token) — se documenta el límite exacto en la tarjeta del bloque que lo
crea y en `api/endpoints/<FEATURE>.md`.

## 6. Cómo se agrega un endpoint nuevo

1. Se define en la tarjeta del bloque que lo produce (criterios de aceptación = casos de
   request/response, incluidos los negativos).
2. Al implementarlo, se documenta el detalle completo en `api/endpoints/<FEATURE>.md` (usar
   `_system/templates/API_ENDPOINT.md`).
3. Se congela en `_state/contracts/CONTRACT_LOCKS.md` como parte del DoD del bloque.
4. Si introduce un `code` de error nuevo, se agrega a la tabla de §3.
