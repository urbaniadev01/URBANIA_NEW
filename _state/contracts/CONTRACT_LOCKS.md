---
tipo: contrato
proyecto: shared
actualizado: 2026-07-09
---

# CONTRACT_LOCKS — Contratos de API congelados

> **Estado actual (2026-07-09):** 10 locks implementados (AUTH-01 a AUTH-05, AUTH-08, AUTH-09, AUTH-10, PROPIEDADES-01, PROPIEDADES-02, PROPIEDADES-03, PROPIEDADES-04).
> Todos los productores en `done`. Consumidores web: B10, B11, B13 en `done`; **B06, B07, B12 en
> `in_progress`** (revertidos por auditoría 2026-07-09 — evidencia vacía o contradictoria, ver
> `_state/CHANGELOG.md#SHIP-013`). DASHBOARD-B02 consume locks PROPIEDADES-02, PROPIEDADES-03, PROPIEDADES-04.

> Registro de contratos de endpoint congelados para que un bloque de cliente pueda construir contra
> ellos. Formato y reglas completas en [[../../_system/04_CROSS_PROJECT]] §4–§5. Una entrada es
> inmutable mientras tenga un "Consumido por" activo — cambiarla es un bloque nuevo, no una edición
> (ver §5 de ese documento).
>
> **Regla mecánica:** un bloque de cliente con `proyectos: [web]` que depende de un endpoint no
> puede pasar a `ready` sin una entrada aquí que lo respalde.

## Locks activos

### LOCK-PROPIEDADES-04 — Endpoints de coeficientes y tree {#LOCK-PROPIEDADES-04}

- **Bloque productor:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B05-coeficientes-tree]]
- **Estado:** Implementado (PROPIEDADES-B05 en done).
- **Endpoints:**
  - `GET /api/v1/properties/{property}/coefficients` — listar coeficientes de unidad (activos + históricos)
  - `PATCH /api/v1/condominiums/{condominium}/coefficients` — gestión masiva atómica de coeficientes
  - `GET /api/v1/condominiums/{condominium}/tree` — estructura jerárquica del condominio
- **Request/Response:** Ver detalle en [[../../api/endpoints/PROPIEDADES]]
- **Errores documentados:** `COEFFICIENT_OUT_OF_RANGE` (422), `COEFFICIENT_INVALID_TYPE` (422), `PROPERTY_NOT_IN_CONDOMINIUM` (422), `PROPERTY_NOT_FOUND` (404), `CONDOMINIUM_NOT_FOUND` (404)
- **Warnings documentados:** `COEFFICIENT_SUM_MISMATCH` (200 no bloqueante, API_CONTRACT §4-bis)
- **Autorización:** `auth:api` + tenant isolation (R-09) + staff scoping (R-09-bis) + anti-enumeración (R-10). Gestión de coeficientes y tree requieren scope `organization` o `condominium` — scope `tower` es insuficiente (datos financieros). Residentes solo ven coeficientes de su propia unidad.
- **Reglas de negocio:**
  - R-05: Coeficiente vigente único — crear uno nuevo cierra automáticamente el anterior (`vigente_hasta = hoy - 1 día`).
  - R-06: Suma de coeficientes de copropiedad = 1.0 — validación no bloqueante con warning `COEFFICIENT_SUM_MISMATCH`.
  - R-06-bis: Set cerrado de `tipo` — `copropiedad`, `parqueadero`, `deposito`, `mantenimiento`.
  - R-09: Tenant isolation — solo datos de la organización del usuario.
  - R-09-bis: Staff scoping — usuarios con scope `condominium` gestionan solo su condominio asignado. Scope `tower` no permite gestionar coeficientes ni ver tree.
  - R-10: Anti-enumeración — 403/404 unificados para recursos fuera del scope.
  - R-11: Auditoría — `created_by`/`updated_by`.
- **Atomicidad:** El PATCH masivo es atómico — todas las operaciones en una transacción DB. Si cualquier item falla, rollback completo.
- **Detalle completo:** [[../../api/endpoints/PROPIEDADES]]
- **Congelado:** 2026-07-08
- **Consumido por:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B09-pantalla-coeficientes]], [[../../features/DASHBOARD/blocks/DASHBOARD-B02-propiedades-widgets]]

### LOCK-PROPIEDADES-03 — Endpoints de unidades (properties) {#LOCK-PROPIEDADES-03}

- **Bloque productor:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B04-crud-unidades]]
- **Estado:** Implementado (PROPIEDADES-B04 en `done`).
- **Endpoints:**
  - `GET /api/v1/condominiums/{condominium}/properties` — listar unidades (cursor-based + filtros)
  - `POST /api/v1/condominiums/{condominium}/properties` — crear unidad
  - `GET /api/v1/properties/{property}` — ver unidad individual (con `area_m2`)
  - `PATCH /api/v1/properties/{property}` — actualizar unidad
  - `DELETE /api/v1/properties/{property}` — eliminar unidad (sin ocupantes)
- **Request/Response:** Ver detalle en [[../../api/endpoints/PROPIEDADES]]
- **Errores documentados:** `PROPERTY_CODE_DUPLICATE` (409), `TOWER_CONDOMINIUM_MISMATCH` (422), `PROPERTY_HAS_OCCUPANTS` (409), `PROPERTY_NOT_FOUND` (404), `CONDOMINIUM_NOT_FOUND` (404), `FORBIDDEN` (403)
- **Autorización:** `auth:api` + tenant isolation (R-09) + staff scoping (R-09-bis) + anti-enumeración (R-10). Residentes solo ven su propia unidad; index denegado para residentes.
- **Reglas de negocio:**
  - R-02: `codigo` único por `condominium_id` → 409 `PROPERTY_CODE_DUPLICATE`.
  - R-07: `condominium_id` inmutable — no expuesto en PATCH.
  - R-03: No eliminar con ocupantes activos → 409 `PROPERTY_HAS_OCCUPANTS`. Con guard clause si la tabla `property_occupants` aún no existe.
  - R-09: Tenant isolation — solo datos de la organización del usuario.
  - R-09-bis: Staff scoping — usuarios con scope `condominium` o `tower` solo ven/gestionan su scope asignado.
  - R-10: Exposición diferenciada — `area_m2` solo en detalle (PropertyResource), no en listado (PropertyListResource). Anti-enumeración 403/404 unificados.
  - R-11: Auditoría — `created_by`/`updated_by`.
- **Paginación:** Cursor-based (`?cursor=...&limit=...`), envelope `{ data, meta.next_cursor }` (API_CONTRACT §4).
- **Filtros:** `tower_id`, `type_id`, `status_id`, `search` (query params combinables).
- **Detalle completo:** [[../../api/endpoints/PROPIEDADES]]
- **Congelado:** 2026-07-08
- **Consumido por:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B08-pantalla-unidades]], [[../../features/DASHBOARD/blocks/DASHBOARD-B02-propiedades-widgets]]

### LOCK-PROPIEDADES-01 — Endpoints de catálogos de propiedad {#LOCK-PROPIEDADES-01}

- **Bloque productor:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B02-crud-catalogos]]
- **Estado:** Implementado (PROPIEDADES-B02 en `done`).
- **Endpoints:**
  - `GET /api/v1/property-types` — listar tipos (sistema + tenant)
  - `POST /api/v1/property-types` — crear tipo (tenant)
  - `GET /api/v1/property-types/{property_type}` — ver tipo individual
  - `PATCH /api/v1/property-types/{property_type}` — actualizar tipo (solo tenant)
  - `DELETE /api/v1/property-types/{property_type}` — eliminar tipo (solo tenant, sin uso)
  - `GET /api/v1/property-statuses` — listar estados (sistema + tenant)
  - `POST /api/v1/property-statuses` — crear estado (tenant)
  - `GET /api/v1/property-statuses/{property_status}` — ver estado individual
  - `PATCH /api/v1/property-statuses/{property_status}` — actualizar estado (solo tenant)
  - `DELETE /api/v1/property-statuses/{property_status}` — eliminar estado (solo tenant, sin uso)
- **Request/Response:** Ver detalle en [[../../api/endpoints/PROPIEDADES]]
- **Errores documentados:** `SYSTEM_CATALOG_READONLY` (403), `PROPERTY_TYPE_IN_USE` (409), `PROPERTY_STATUS_IN_USE` (409), `PROPERTY_TYPE_NAME_DUPLICATE` (409), `PROPERTY_STATUS_NAME_DUPLICATE` (409), `PROPERTY_TYPE_NOT_FOUND` (404), `PROPERTY_STATUS_NOT_FOUND` (404)
- **Autorización:** `auth:api` — cualquier usuario autenticado puede leer. Escritura sujeta a tenant isolation (R-09) y protección de catálogos del sistema (R-08).
- **Detalle completo:** [[../../api/endpoints/PROPIEDADES]]
- **Congelado:** 2026-07-08
- **Consumido por:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B06-pantallas-catalogos]]

### LOCK-PROPIEDADES-02 — Endpoints de condominios y torres {#LOCK-PROPIEDADES-02}

- **Bloque productor:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B03-crud-condominios-torres]]
- **Estado:** Implementado (PROPIEDADES-B03 en `done`).
- **Endpoints:**
  - `GET /api/v1/condominiums` — listar condominios (tenant + scope)
  - `POST /api/v1/condominiums` — crear condominio
  - `GET /api/v1/condominiums/{condominium}` — ver condominio con torres
  - `PATCH /api/v1/condominiums/{condominium}` — actualizar condominio
  - `DELETE /api/v1/condominiums/{condominium}` — eliminar condominio (sin torres ni propiedades)
  - `GET /api/v1/condominiums/{condominium}/towers` — listar torres de un condominio
  - `POST /api/v1/condominiums/{condominium}/towers` — crear torre bajo condominio
  - `GET /api/v1/towers/{tower}` — ver torre individual
  - `PATCH /api/v1/towers/{tower}` — actualizar torre (condominium_id inmutable)
  - `DELETE /api/v1/towers/{tower}` — eliminar torre (sin propiedades)
- **Request/Response:** Ver detalle en [[../../api/endpoints/PROPIEDADES]]
- **Errores documentados:** `CONDOMINIUM_NAME_DUPLICATE` (409), `TOWER_NAME_DUPLICATE` (409), `CONDOMINIUM_HAS_TOWERS` (409), `CONDOMINIUM_HAS_PROPERTIES` (409), `TOWER_HAS_PROPERTIES` (409), `CONDOMINIUM_NOT_FOUND` (404), `TOWER_NOT_FOUND` (404), `FORBIDDEN` (403)
- **Autorización:** `auth:api` + scope por tenant (R-09) + staff scoping (R-09-bis) + anti-enumeración (R-10). Solo usuarios con scope `organization` o `condominium` pueden listar condominios.
- **Reglas de negocio:**
  - R-01: Jerarquía condominio → torres (anidadas). Torres bajo `/condominiums/{id}/towers`.
  - R-03: No eliminar con hijos activos (409).
  - R-04: Soft delete en ambas entidades.
  - R-07: `condominium_id` en torres es inmutable — se ignora en PATCH.
  - R-09: Tenant isolation — solo datos de la organización del usuario.
  - R-09-bis: Staff scoping — usuarios con scope `condominium` o `tower` solo ven/gestionan su scope asignado.
  - R-10: Anti-enumeración — 403/404 unificados para recursos fuera del scope del usuario.
  - R-11: Auditoría — `created_by`/`updated_by`.
- **Detalle completo:** [[../../api/endpoints/PROPIEDADES]]
- **Congelado:** 2026-07-08
- **Consumido por:** [[../../features/PROPIEDADES/blocks/PROPIEDADES-B07-pantallas-condominios]], [[../../features/DASHBOARD/blocks/DASHBOARD-B02-propiedades-widgets]]

### LOCK-AUTH-01 — `POST /auth/register` {#LOCK-AUTH-01}

- **Bloque productor:** [[../../features/AUTH/blocks/AUTH-B01-registro-por-invitacion]]
- **Estado:** Implementado (AUTH-B01 en `done`). Reimplementación completa del endpoint.
- **Endpoint:** `POST /api/v1/auth/register`
- **Request body:** `invitation_token` (string, required), `password` (string, required), `name` (string, required), `phone` (string, optional)
- **Response (201):** `{ "message": "Registro exitoso", "user": { "id", "email", "name", "estado", "organization_id", "created_at" } }`
- **Errores documentados:** `403 INVITATION_TOKEN_INVALID`, `409 EMAIL_ALREADY_REGISTERED`, `422 VALIDATION_ERROR`, `429` (throttle)
- **Rate limiting:** 10 intentos por minuto por IP
- **Detalle completo:** [[../../api/endpoints/AUTH#post-apiv1authregister]]
- **Congelado:** 2026-07-04
- **Consumido por:** [[../../features/AUTH/blocks/AUTH-B07-pantalla-registro]]

### LOCK-AUTH-02 — `POST /auth/login` {#LOCK-AUTH-02}

- **Bloque productor:** [[../../features/AUTH/blocks/AUTH-B02-login]]
- **Modificado por:** [[../../features/AUTH/blocks/AUTH-B08-mfa-enrollment]] (adición no-breaking: respuesta `mfa_required` cuando el usuario tiene MFA activo)
- **Estado:** Implementado (AUTH-B02 en `done`). Reimplementación completa del endpoint.
- **Endpoint:** `POST /api/v1/auth/login`
- **Request body:** `email` (string, required), `password` (string, required)
- **Response (200) — usuario sin MFA:** `{ "access_token": "<JWT RS256>", "token_type": "Bearer", "expires_in": 900 }`
- **Response (200) — usuario con MFA:** `{ "mfa_required": true, "mfa_token": "<JWT RS256 tipo mfa>" }`
- **Cookie:** `refresh_token` (httpOnly, secure, sameSite=strict, path=/api/v1/auth) — solo cuando se emite `access_token`. `mfa_token` (httpOnly, secure, sameSite=strict, path=/api/v1/auth) — cuando `mfa_required: true`.
- **Errores documentados:** `401 INVALID_CREDENTIALS`, `403 ACCOUNT_NOT_ACTIVE`, `422 VALIDATION_ERROR`, `429` (throttle)
- **Rate limiting:** 5 intentos por minuto por IP
- **Detalle completo:** [[../../api/endpoints/AUTH#post-apiv1authlogin]]
- **Congelado:** 2026-07-04
- **Actualización (no-breaking):** 2026-07-07 — adición de respuesta `mfa_required` para usuarios con MFA activo
- **Consumido por:** [[../../features/AUTH/blocks/AUTH-B06-pantalla-login]]

### LOCK-AUTH-03 — `POST /auth/refresh` {#LOCK-AUTH-03}

- **Bloque productor:** [[../../features/AUTH/blocks/AUTH-B03-refresh-token]]
- **Estado:** Implementado (AUTH-B03 en `done`). Endpoint de refresh con rotación y detección de reuso.
- **Endpoint:** `POST /api/v1/auth/refresh`
- **Request:** Sin body. Cookie `refresh_token` (httpOnly, secure, sameSite=strict, path=/api/v1/auth)
- **Response (200):** `{ "access_token": "<JWT RS256>", "token_type": "Bearer", "expires_in": 900 }`
- **Cookie:** nuevo `refresh_token` (httpOnly, secure, sameSite=strict, path=/api/v1/auth)
- **Errores documentados:** `401 REFRESH_TOKEN_MISSING`, `401 REFRESH_TOKEN_EXPIRED`, `401 REFRESH_TOKEN_REUSED`
- **Detalle completo:** [[../../api/endpoints/AUTH#post-apiv1authrefresh]]
- **Congelado:** 2026-07-05
- **Consumido por:** _ninguno todavía_

### LOCK-AUTH-04 — `POST /auth/logout` {#LOCK-AUTH-04}

- **Bloque productor:** [[../../features/AUTH/blocks/AUTH-B04-logout]]
- **Estado:** Implementado (AUTH-B04 en `done`). Reimplementación completa del endpoint.
- **Endpoint:** `POST /api/v1/auth/logout`
- **Request:** Sin body. Cookie `refresh_token` (httpOnly, secure, sameSite=strict, path=/api/v1/auth) — opcional.
- **Response (200):** `{ "message": "Sesión cerrada exitosamente." }`
- **Cookie:** `refresh_token` se limpia (Set-Cookie con valor vacío y expiración pasada). Mismo path y flags que la cookie original.
- **Errores documentados:** Ninguno — logout es siempre `200` (idempotente). `429` por rate limiting (10 intentos/minuto por IP).
- **Idempotencia:** Si no hay cookie o el token ya está revocado/expirado, igual responde `200` — no revela si había sesión activa.
- **Detalle completo:** [[../../api/endpoints/AUTH#post-apiv1authlogout]]
- **Congelado:** 2026-07-05
- **Consumido por:** _ninguno todavía_

## Locks reemplazados

_Vacío._

### LOCK-AUTH-08 — Endpoints MFA {#LOCK-AUTH-08}

- **Bloque productor:** [[../../features/AUTH/blocks/AUTH-B08-mfa-enrollment]]
- **Estado:** Implementado. Endpoints de enrollment, verificación, desactivación y regeneración de códigos MFA.
- **Endpoints:**
  - `POST /api/v1/auth/mfa/enroll` — iniciar enrollment MFA (TOTP + recovery codes)
  - `POST /api/v1/auth/mfa/confirm` — confirmar enrollment con código TOTP
  - `POST /api/v1/auth/mfa/verify` — verificar MFA durante login (usa `mfa_token`)
  - `POST /api/v1/auth/mfa/disable` — desactivar MFA
  - `POST /api/v1/auth/mfa/recovery` — regenerar códigos de respaldo
- **Request/Response:** Ver detalle en [[../../api/endpoints/AUTH]]
- **Errores documentados:** `MFA_ALREADY_ENABLED` (409), `MFA_NOT_ENABLED` (409), `MFA_CODE_INVALID` (422), `MFA_TOKEN_INVALID` (401), `MFA_RECOVERY_CODE_USED` (422), `MFA_ENROLLMENT_NOT_FOUND` (404), `MFA_ENROLLMENT_EXPIRED` (422), `MFA_REQUIRED` (403), `MFA_RATE_LIMIT` (429)
- **Rate limiting:** Enroll: 3/hora/usuario. Verify: 5/minuto/usuario. Ambos implementados vía Redis (no middleware throttle).
- **Detalle completo:** [[../../api/endpoints/AUTH]]
- **Congelado:** 2026-07-07
- **Consumido por:** [[../../features/AUTH/blocks/AUTH-B10-mfa-verify-web]], [[../../features/AUTH/blocks/AUTH-B11-mfa-enroll-web]]

### LOCK-AUTH-09 — `POST /auth/forgot-password` y `POST /auth/reset-password` {#LOCK-AUTH-09}

- **Bloque productor:** [[../../features/AUTH/blocks/AUTH-B09-recuperacion-password]]
- **Estado:** Implementado. Endpoints de recuperación de contraseña: solicitud de reset y aplicación de nueva contraseña.
- **Endpoints:**
  - `POST /api/v1/auth/forgot-password` — solicitar recuperación (siempre 200 genérico)
  - `POST /api/v1/auth/reset-password` — aplicar nueva contraseña con token
  - `GET /dev/password-resets/last?email=...` — dev endpoint (solo local/testing)
- **Request/Response:** Ver detalle en [[../../api/endpoints/AUTH]]
- **Errores documentados:** `RESET_TOKEN_EXPIRED` (422), `RESET_TOKEN_INVALID` (422), `TOO_MANY_REQUESTS` (429), `VALIDATION_ERROR` (422)
- **Rate limiting:** Forgot: 3/hora/email. Reset: 5/15min/IP. Ambos implementados vía Redis (no middleware throttle).
- **Seguridad:** Respuesta genérica en forgot-password (mismo status/body/tiempo exista o no el email). Token hasheado con SHA-256 en BD. Token de un solo uso.
- **Detalle completo:** [[../../api/endpoints/AUTH]]
- **Congelado:** 2026-07-07
- **Consumido por:** [[../../features/AUTH/blocks/AUTH-B12-forgot-password-web]], [[../../features/AUTH/blocks/AUTH-B13-reset-password-web]]

### LOCK-AUTH-10 — `GET /auth/me` {#LOCK-AUTH-10}

- **Bloque productor:** [[../../features/AUTH/blocks/AUTH-B15-endpoint-me-dashboard]]
- **Estado:** Implementado (AUTH-B15 Fase API).
- **Endpoint:** `GET /api/v1/auth/me`
- **Request:** Sin body. Header `Authorization: Bearer <access_token>` (JWT RS256).
- **Response (200):** `{ "user": { "id": "<uuid>", "email": "user@example.com", "name": "John Doe", "role": "admin", "permissions": ["admin.access", "condominiums.read"] } }`
- **Errores documentados:** `401 UNAUTHENTICATED` (token faltante, inválido o expirado), `429` (throttle: 30 req/min por IP)
- **Autorización:** `auth:api` — solo usuarios autenticados. No requiere scope específico.
- **Rate limiting:** 30 requests/minuto por IP.
- **Detalle completo:** [[../../api/endpoints/AUTH#get-apiv1authme]]
- **Congelado:** 2026-07-09
- **Consumido por:** [[../../features/AUTH/blocks/AUTH-B15-endpoint-me-dashboard]] (Fase Web — `useUserQuery` en `features/dashboard/hooks/useUserQuery.ts`)
