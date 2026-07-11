---
tipo: contrato
proyecto: shared
actualizado: 2026-07-10
---

# CONTRACT_LOCKS — Contratos de API congelados

> **Estado actual (2026-07-11):** 15 locks implementados (AUTH-01 a AUTH-05, AUTH-08, AUTH-09, AUTH-10, PROPIEDADES-01, PROPIEDADES-02, PROPIEDADES-03, PROPIEDADES-04, DIRECTORIO-01, DIRECTORIO-02, DIRECTORIO-03, COBRANZA-02, COBRANZA-03).
> Todos los productores en `done`, salvo `COBRANZA-B03` (`verifying` — `verificacion_critica: true`,
> requiere `verify-council`). Consumidores web: B10, B11, B12, B13 en `done`;
> DIRECTORIO-B05/B06/B07 en `done`. `PROPIEDADES-B06/B07/B08/B09` en `verifying` desde el 2026-07-10
> — DoD cerrado con `pnpm ci` limpio y tests de componente nuevos, pendiente solo de verificación
> visual Playwright bloqueada por un bug de entorno (ver `_state/RUNBOOK.md#E-005`). DASHBOARD-B02
> consume locks PROPIEDADES-02, PROPIEDADES-03, PROPIEDADES-04. `COBRANZA-B07` (web) ya está `ready`
> contra `LOCK-COBRANZA-02`; `COBRANZA-B08` queda pendiente de que `COBRANZA-B03` llegue a `done`.

> Registro de contratos de endpoint congelados para que un bloque de cliente pueda construir contra
> ellos. Formato y reglas completas en [[../../_system/04_CROSS_PROJECT]] §4–§5. Una entrada es
> inmutable mientras tenga un "Consumido por" activo — cambiarla es un bloque nuevo, no una edición
> (ver §5 de ese documento).
>
> **Regla mecánica:** un bloque de cliente con `proyectos: [web]` que depende de un endpoint no
> puede pasar a `ready` sin una entrada aquí que lo respalde.

## Locks activos

### LOCK-COBRANZA-03 — Endpoints de periodos y corridas de facturación {#LOCK-COBRANZA-03}

- **Bloque productor:** [[../../features/COBRANZA/blocks/COBRANZA-B03-periodos-facturacion]]
- **Estado:** Implementado (COBRANZA-B03 en `done` — `verificacion_critica: true`, `verify-council` corrido: encontró un crítico de doble facturación, corregido y re-verificado; ver la sección "Verificación" de la tarjeta).
- **Endpoints:**
  - `GET /api/v1/condominiums/{condominium}/billing-periods` — listar periodos del condominio
  - `POST /api/v1/condominiums/{condominium}/billing-periods` — abrir periodo (`anio`+`mes`)
  - `GET /api/v1/billing-periods/{billing_period}` — ver periodo
  - `PATCH /api/v1/billing-periods/{billing_period}` — cerrar periodo (`{estado: cerrado}`)
  - `POST /api/v1/billing-periods/{billing_period}/billing-runs` — **disparar corrida → `202`** (R-COB-22)
  - `GET /api/v1/billing-periods/{billing_period}/billing-runs` — listar corridas del periodo
  - `GET /api/v1/billing-runs/{billing_run}` — **detalle de corrida (endpoint de polling)**, incluye `resumen`
  - `GET /api/v1/condominiums/{condominium}/billing-periods/active/summary` — **panel de cartera del periodo activo** ← *este es el endpoint que `DASHBOARD` necesita para su widget "Cuotas Pendientes"*
  - `GET /api/v1/billing-periods/{billing_period}/summary` — panel de cartera de un periodo específico
- **Request/Response:** Ver detalle en [[../../api/endpoints/COBRANZA]]
- **Errores documentados:** `BILLING_PERIOD_DUPLICATE` (409), `BILLING_PERIOD_NOT_FOUND` (404), `BILLING_PERIOD_ALREADY_CLOSED` (409), `BILLING_RUN_ALREADY_EXISTS` (409), `BILLING_RUN_NOT_FOUND` (404), `CONDOMINIUM_NOT_FOUND` (404), `PERMISSION_DENIED` (403), `VALIDATION_ERROR` (422)
- **Warnings documentados:** `BILLING_PERIOD_HAS_PENDING_INVOICES` (200 no bloqueante, R-COB-08-bis — cerrar un periodo con facturas pendientes **no** devuelve 409)
- **Patrón asíncrono (nuevo, convención general):** `POST .../billing-runs` responde `202` de inmediato con el run en `en_proceso` y encola `RunBillingPeriodJob`; el cliente hace polling sobre `GET /billing-runs/{id}` hasta `completado`/`fallido`. Documentado como convención reusable en [[../../api/API_CONTRACT]] §4-ter — no es una excepción de COBRANZA.
- **`resumen` (éxito parcial):** JSONB expuesto solo cuando `estado != en_proceso`: `{unidades_facturadas, unidades_omitidas, detalle_omitidas: [{property_id, motivo}], conceptos_omitidos: [{property_id, charge_concept_id, motivo}]}`. Sin él, `completado` no distinguiría "todas las unidades" de "completado, pero N unidades omitidas". `detalle_omitidas.motivo` ∈ {`sin coeficiente vigente`, `sin conceptos de cobro aplicables`}; `conceptos_omitidos.motivo` ∈ {`la unidad no tiene área registrada`, `el concepto no aplica a esta unidad`} — este último campo hace auditable la sub-facturación de una unidad que sí recibió factura pero a la que un concepto no se aplicó (antes era indistinguible por query).
- **`estado = fallido` garantiza cero facturas escritas.** El prorrateo, la transición de estado y el `resumen` viven en una sola transacción; un fallo revierte todo. El `error` del `resumen` es `{code, trace_id}` — nunca el mensaje crudo de la excepción (un `QueryException` incluiría el SQL con valores monetarios).
- **No-duplicación (garantía de BD, no solo de aplicación):** `UNIQUE(billing_period_id, property_id) WHERE deleted_at IS NULL` sobre `invoices` — **una unidad, una factura por periodo**. Es el invariante que cierra las tres rutas de doble facturación que encontró el `verify-council` (dos POST concurrentes; un fallo tras el commit; la redelivery del job por un worker muerto), y protege también a cualquier escritor futuro de `invoices` (`COBRANZA-B04` con ítems manuales, backfills, scripts de soporte), no solo a este Job.
- **Autorización:** `auth:api` + scope `organization`/`condominium` (R-COB-02, `tower`/`unit` nunca bastan para datos financieros). Permisos por acción:
  - `cobranza.periodos.ver` — listar/ver periodos, listar/ver corridas, summary de un periodo específico.
  - `cobranza.facturacion.ejecutar` — abrir periodo, cerrar periodo, **disparar corrida** (segregado de solo-ver: es la acción de mayor impacto del feature).
  - `billing.ver` — **solo** `.../billing-periods/active/summary`. Deliberadamente el permiso más laxo: es el que `DASHBOARD` ya usa para gatear su nav/widgets, y exigir `cobranza.periodos.ver` ahí bloquearía el widget de cartera para usuarios que sí deben verlo.
- **Reglas de negocio:**
  - R-COB-04 (Ley 675): prorrateo por `property_coefficients` vigente de tipo `copropiedad`. Una unidad sin coeficiente vigente **no se factura** — se omite y se registra el motivo en `resumen.detalle_omitidas`, nunca se le asigna un coeficiente por defecto.
  - R-COB-05: "unidad activa" = **no eliminada** (soft-delete). El `property_status` **no exime de facturación** — bajo Ley 675 el propietario paga su cuota aunque la unidad esté vacía, en remodelación o "fuera de servicio". El diseño nunca definió "activa"; el `verify-council` lo levantó como ambigüedad y el usuario fijó esta interpretación.
  - R-COB-06: `invoice_items.base_calculo` es snapshot inmutable del coeficiente usado (solo para conceptos con `metodo_calculo = coeficiente`).
  - R-COB-07: los conceptos `manual` **no** entran en la corrida — se agregan a mano vía `POST /invoices/{id}/items` (COBRANZA-B04). Los conceptos inactivos tampoco.
  - R-COB-09: un solo `billing_run` `completado` por periodo — validado en aplicación (409 `BILLING_RUN_ALREADY_EXISTS`, que además cubre el caso `en_proceso`) y reforzado por el UNIQUE parcial de BD de COBRANZA-B01.
  - R-COB-10: `abierto → facturado → cerrado`. La corrida completada deja el periodo en `facturado`.
  - R-COB-08: `invoices.estado` **no se almacena** — este bloque escribe `saldo = valor_total` al emitir; el `estado` derivado (`pendiente`/`parcial`/`pagada`/`vencida`) lo expone COBRANZA-B04. Los agregados de `summary` se calculan sobre los hechos subyacentes (`saldo`, `valor_total`, `fecha_vencimiento`), no sobre un campo almacenado.
- **Sin reintento automático:** `RunBillingPeriodJob` corre con `$tries = 1` — reintentar una corrida a medio camino podría duplicar facturas. Un `fallido` se resuelve disparando una corrida nueva.
- **Numeración de facturas:** `FAC-{anio}{mes}-{correlativo}` (`UNIQUE(condominium_id, numero)`), correlativo por condominio.
- **Rate limiting:** `POST .../billing-runs` lleva `throttle:10,1`.
- **Desviación documentada del criterio de aceptación original:** la tarjeta pedía `422` para periodo duplicado; se implementó `409 BILLING_PERIOD_DUPLICATE`, criterio confirmado por el usuario al cerrar `COBRANZA-B02` (409 para conflictos de unicidad de recurso, 422 reservado a formato/campos faltantes).
- **Detalle completo:** [[../../api/endpoints/COBRANZA]]
- **Congelado:** 2026-07-11
- **Consumido por:** [[../../features/COBRANZA/blocks/COBRANZA-B08-pantallas-periodos-facturacion]] y — vía `.../billing-periods/active/summary` — el widget de cartera de [[../../features/DASHBOARD/PANORAMA]] (ver la acción pendiente cross-feature en [[../../features/COBRANZA/BLOCKS]]: el panorama de DASHBOARD referencia hoy `GET /billing-periods/active/summary` sin condominio; la ruta real es la condominio-scoped de este lock).

### LOCK-COBRANZA-02 — Endpoints de conceptos de cobro {#LOCK-COBRANZA-02}

- **Bloque productor:** [[../../features/COBRANZA/blocks/COBRANZA-B02-crud-conceptos-cobro]]
- **Estado:** Implementado (COBRANZA-B02 en `verifying`).
- **Endpoints:**
  - `GET /api/v1/condominiums/{condominium}/charge-concepts` — listar conceptos de cobro del condominio
  - `POST /api/v1/condominiums/{condominium}/charge-concepts` — crear concepto de cobro
  - `GET /api/v1/charge-concepts/{charge_concept}` — ver concepto individual
  - `PATCH /api/v1/charge-concepts/{charge_concept}` — actualizar concepto
  - `DELETE /api/v1/charge-concepts/{charge_concept}` — desactivar (soft delete + `activo=false`)
- **Request/Response:** Ver detalle en [[../../api/endpoints/COBRANZA]]
- **Errores documentados:** `CHARGE_CONCEPT_NAME_DUPLICATE` (409), `CHARGE_CONCEPT_NOT_FOUND` (404), `CONDOMINIUM_NOT_FOUND` (404), `PERMISSION_DENIED` (403), `VALIDATION_ERROR` (422)
- **Warnings documentados:** `FONDO_IMPREVISTOS_VALIDACION_PENDIENTE` (200/201 no bloqueante, API_CONTRACT §4-bis) — emitido cuando `tipo = fondo_imprevistos` (R-COB-18, validación real diferida)
- **Autorización:** `auth:api` + permisos `cobranza.conceptos.ver` (lectura) / `cobranza.conceptos.gestionar` (escritura), resueltos a mano en el controller (no vía middleware `require_permission`, ver nota abajo) contra scope `organization` o `condominium` (R-COB-02) — scope `tower`/`unit` nunca basta para datos financieros, a diferencia de `LOCK-PROPIEDADES-03`.
- **Nota de arquitectura:** no se usó el middleware genérico `require_permission` porque `CheckPermissionUseCase` exige coincidencia exacta de `scope_type` (no expande un scope `organization` a `condominium` pese a lo que su propio docblock afirma) — mismo motivo por el que `PropertyController` (`LOCK-PROPIEDADES-03`) ya resolvía su scope a mano. `ChargeConceptController` sigue ese mismo patrón, agregando el filtro por nombre de permiso.
- **Desviación documentada del criterio de aceptación original:** la tarjeta de `COBRANZA-B02` pedía `422` para nombre duplicado; se implementó `409 CHARGE_CONCEPT_NAME_DUPLICATE` por consistencia con el resto del API (`PROPERTY_TYPE_NAME_DUPLICATE`, `CONDOMINIUM_NAME_DUPLICATE`, etc. — 409 para conflictos de unicidad de recurso, 422 reservado para formato/campos faltantes). Ver evidencia de la tarjeta.
- **Reglas de negocio:**
  - R-COB-01: Tenant isolation vía `condominium_id`.
  - R-COB-02: Staff scoping — solo `organization`/`condominium` habilitan acceso a datos financieros.
  - R-COB-18: Warning no bloqueante `FONDO_IMPREVISTOS_VALIDACION_PENDIENTE` en creación/edición de conceptos `fondo_imprevistos`.
- **Detalle completo:** [[../../api/endpoints/COBRANZA]]
- **Congelado:** 2026-07-11
- **Consumido por:** [[../../features/COBRANZA/blocks/COBRANZA-B07-pantallas-conceptos-cobro]]

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

### LOCK-DIRECTORIO-01 — Endpoints de catálogo de tipos de ocupante {#LOCK-DIRECTORIO-01}

- **Bloque productor:** [[../../features/DIRECTORIO/blocks/DIRECTORIO-B02-crud-tipos-ocupante]]
- **Estado:** Implementado (DIRECTORIO-B02 en `verifying`).
- **Endpoints:**
  - `GET /api/v1/occupant-types` — listar tipos (sistema + tenant)
  - `POST /api/v1/occupant-types` — crear tipo (tenant)
  - `GET /api/v1/occupant-types/{occupant_type}` — ver tipo individual
  - `PATCH /api/v1/occupant-types/{occupant_type}` — actualizar tipo (solo tenant)
  - `DELETE /api/v1/occupant-types/{occupant_type}` — eliminar tipo (solo tenant, sin uso)
- **Request\Response:** Ver detalle en [[../../api/endpoints/DIRECTORIO]]
- **Errores documentados:** `SYSTEM_CATALOG_READONLY` (403, reutilizado de `LOCK-PROPIEDADES-01`), `OCCUPANT_TYPE_IN_USE` (409), `OCCUPANT_TYPE_NAME_DUPLICATE` (409), `OCCUPANT_TYPE_NOT_FOUND` (404)
- **Autorización:** `auth:api` — cualquier usuario autenticado puede leer (incluido rol `residente`). Escritura sujeta a tenant isolation (R-DIR-01) y protección de catálogos del sistema (R-DIR-09). El catálogo es a nivel organización, sin scope de condominio/torre.
- **Detalle completo:** [[../../api/endpoints/DIRECTORIO]]
- **Congelado:** 2026-07-11
- **Consumido por:** [[../../features/DIRECTORIO/blocks/DIRECTORIO-B05-pantalla-tipos-ocupante]]

### LOCK-DIRECTORIO-02 — Endpoints de contactos y autoservicio {#LOCK-DIRECTORIO-02}

- **Bloque productor:** [[../../features/DIRECTORIO/blocks/DIRECTORIO-B03-crud-contactos]]
- **Estado:** Implementado (DIRECTORIO-B03 en `verifying`).
- **Endpoints:**
  - `GET /api/v1/contacts` — listar contactos (paginado cursor, `?search=`)
  - `POST /api/v1/contacts` — crear contacto (siempre `user_id = NULL`)
  - `GET /api/v1/contacts/{contact}` — ver contacto (detalle completo)
  - `PATCH /api/v1/contacts/{contact}` — actualizar contacto
  - `DELETE /api/v1/contacts/{contact}` — eliminar contacto (sin ocupaciones activas)
  - `GET /api/v1/contacts/{contact}/properties` — unidades donde el contacto tiene una ocupación activa
  - `GET /api/v1/me/contact` — autoservicio: propio contacto (sin permisos especiales)
  - `PATCH /api/v1/me/contact` — autoservicio: editar propio contacto
- **Request\Response:** Ver detalle en [[../../api/endpoints/DIRECTORIO]]
- **Errores documentados:** `CONTACT_HAS_OCCUPATIONS` (409), `CONTACT_NOT_FOUND` (404), `VALIDATION_ERROR` (422)
- **Autorización:** `auth:api`. `/contacts/*` requiere scope de gestión (`organization`, `condominium` o `tower` vía `role_assignments`) — usuarios sin ese scope (ej. `residente`) reciben `403`. `/me/contact` no requiere ningún scope — cualquier usuario autenticado accede a su propio contacto (R-DIR-04).
- **Reglas de negocio:**
  - R-DIR-01: Tenant isolation.
  - R-DIR-03: Staff scoping (condominium/tower) vía ocupaciones activas del contacto; anti-enumeración (404 unificado).
  - R-DIR-06: Habeas data — `email`/`telefono` solo visibles en el listado para actores con scope `organization`; el detalle (`show`) siempre los incluye.
  - R-DIR-08: No eliminar contacto con ocupaciones activas.
  - R-DIR-10: Auditoría — `created_by`/`updated_by`.
- **Detalle completo:** [[../../api/endpoints/DIRECTORIO]]
- **Congelado:** 2026-07-11
- **Consumido por:** [[../../features/DIRECTORIO/blocks/DIRECTORIO-B06-pantalla-directorio-contactos]]

### LOCK-DIRECTORIO-03 — Endpoints de asignación de ocupantes {#LOCK-DIRECTORIO-03}

- **Bloque productor:** [[../../features/DIRECTORIO/blocks/DIRECTORIO-B04-asignacion-ocupantes]]
- **Estado:** Implementado (DIRECTORIO-B04 en `verifying`).
- **Endpoints:**
  - `GET /api/v1/properties/{property}/occupants` — listar ocupantes activos de una unidad
  - `POST /api/v1/properties/{property}/occupants` — asignar un contacto a una unidad
  - `PATCH /api/v1/property-occupants/{property_occupant}` — actualizar una asignación (tipo/principal)
  - `DELETE /api/v1/property-occupants/{property_occupant}` — des-asignar (soft delete)
- **Request\Response:** Ver detalle en [[../../api/endpoints/DIRECTORIO]]
- **Errores documentados:** `OCCUPANT_ASSIGNMENT_DUPLICATE` (409), `PROPERTY_NOT_FOUND` (404, reutilizado de `PROPIEDADES-B04`), `FORBIDDEN` (403), `VALIDATION_ERROR` (422)
- **Autorización:** `auth:api`. Escritura (`POST`/`PATCH`/`DELETE`) requiere scope de gestión (`organization`/`condominium`/`tower`) — `403` si el actor no tiene ninguno, `404` (anti-enumeración) si tiene scope pero no cubre la unidad. Lectura (`GET`) además permite scope `unit` (residente viendo su propia unidad, CA 13).
- **Reglas de negocio:**
  - R-DIR-07: marcar `es_principal: true` desmarca automáticamente cualquier otro principal activo para el mismo `property_id` + `occupant_type_id` (transacción).
  - R-DIR-11: unicidad `(contact_id, property_id, occupant_type_id)` entre registros activos.
  - R-DIR-06: el listado nunca incluye `email`/`telefono` del contacto (solo `id`/`nombre` anidado) — a diferencia de `/contacts`, aquí no hay caso en que se exponga el dato sensible.
  - R-DIR-10: Auditoría — `created_by`/`updated_by`.
- **Detalle completo:** [[../../api/endpoints/DIRECTORIO]]
- **Congelado:** 2026-07-11
- **Consumido por:** [[../../features/DIRECTORIO/blocks/DIRECTORIO-B07-pantalla-asignacion-ocupantes]]

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
