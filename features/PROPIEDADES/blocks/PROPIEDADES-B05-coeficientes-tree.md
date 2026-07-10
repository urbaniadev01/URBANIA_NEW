---
tipo: bloque
proyecto: api
feature: PROPIEDADES
id: PROPIEDADES-B05
proyectos: [api]
estado: done
depende_de: [PROPIEDADES-B01]
contrato: produce
verificacion_critica: true
actualizado: 2026-07-08
---

# PROPIEDADES-B05 — Coeficientes y endpoint tree

## Objetivo

Implementar la gestión de coeficientes de propiedad (con temporalidad) y el endpoint de conveniencia
`tree` que expone la estructura completa de un condominio. Los coeficientes son datos financieros
sensibles: impactan directamente a COBRANZA (facturación) — por eso este bloque tiene
`verificacion_critica: true`.

## Alcance

- **Incluye:**
  - `PropertyCoefficientController` — `index` (`GET /properties/{id}/coefficients`), gestión masiva
    (`PATCH /condominiums/{id}/coefficients` con body `[{property_id, tipo, valor}]`, atómico:
    todas las operaciones dentro de una transacción).
  - `CondominiumTreeController` — `GET /condominiums/{id}/tree` (devuelve estructura jerárquica:
    condominio → torres → unidades con conteos).
  - Validación de `valor` en rango 0–1 (fracción decimal) → 422 `COEFFICIENT_OUT_OF_RANGE`.
  - Validación de `tipo` contra el set cerrado de R-06-bis (`copropiedad`, `parqueadero`,
    `deposito`, `mantenimiento`) → 422 `COEFFICIENT_INVALID_TYPE`. El CHECK constraint de BD
    (creado en B01) es la defensa de último nivel; esta validación en el FormRequest es la que
    produce el `code` de error legible para Web.
  - Validación de que `property_id` pertenece al `condominium_id` del path → 422
    `PROPERTY_NOT_IN_CONDOMINIUM`.
  - Validación de unicidad de coeficiente vigente: solo uno por `property_id + tipo` con
    `vigente_hasta IS NULL` (R-05). Al crear uno nuevo, se cierra automáticamente el anterior
    (`vigente_hasta = hoy - 1 día`).
  - Validación de suma de coeficientes de copropiedad = 1.0 (100%) en el PATCH masivo (R-06,
    R-06-bis: solo aplica a `tipo = copropiedad`): si la suma no es 1.0, la respuesta sigue siendo
    `200` pero incluye `warnings: [{ code: "COEFFICIENT_SUM_MISMATCH", detail: { condominium_id,
    sum } }]` — formato fijado en `api/API_CONTRACT.md` §4-bis. COBRANZA usará valores
    normalizados.
  - PATCH masivo atómico: si cualquier operación falla, se hace rollback de toda la transacción.
  - Autorización: solo admin puede gestionar coeficientes. Residente solo ve los coeficientes de su
    propia unidad. Staff con `role_assignment.scope_type = condominium`/`tower` (R-09-bis) solo
    gestiona coeficientes y tree dentro de su scope asignado.
  - `created_by`/`updated_by` seteados con el `user_id` del actor autenticado al crear/cerrar
    coeficientes (R-11).
  - Anti-enumeración (R-10): 403 y 404 unificados para accesos no autorizados al tree y
    coeficientes.
  - Tests de feature para todos los endpoints — con énfasis en casos de concurrencia y atomicidad.

- **No incluye (explícitamente fuera de este bloque):**
  - CRUD de unidades (B04), condominios (B03), catálogos (B02).
  - Lógica de facturación o cálculo de cuotas — pertenece a COBRANZA.
  - UI web de coeficientes (B09).
  - Optimistic locking (punto ciego PANORAMA §X.2) — se deja como deuda técnica documentada.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Admin, unidad con coeficientes | `GET /properties/{id}/coefficients` | 200 + lista de coeficientes (vigentes + históricos) |
| 2 | Admin, condominio con unidades | `PATCH /condominiums/{id}/coefficients` con body válido `[{property_id, tipo: "copropiedad", valor: 0.25}, ...]` | 200 + coeficientes creados/actualizados, `created_by`/`updated_by` seteados |
| 3 | Coeficiente vigente existente para misma `property_id + tipo` | `PATCH .../coefficients` con nuevo valor | Coeficiente anterior cerrado (`vigente_hasta` seteado), nuevo creado vigente (R-05) |
| 4 | `valor` fuera de rango (ej. 1.5 o -0.1) | `PATCH .../coefficients` | 422 `COEFFICIENT_OUT_OF_RANGE` |
| 5 | `tipo` no reconocido (ej. "jardin") | `PATCH .../coefficients` | 422 `COEFFICIENT_INVALID_TYPE` (R-06-bis) |
| 6 | Suma de coeficientes de copropiedad ≠ 1.0 | `PATCH .../coefficients` | 200 + `warnings: [{ code: "COEFFICIENT_SUM_MISMATCH", detail: {...} }]` (API_CONTRACT §4-bis, R-06) |
| 7 | `property_id` que no pertenece al condominio | `PATCH /condominiums/{id}/coefficients` | 422 `PROPERTY_NOT_IN_CONDOMINIUM` |
| 8 | PATCH con múltiples items, uno inválido | `PATCH .../coefficients` | 422 — rollback completo, ningún coeficiente modificado (atomicidad) |
| 9 | Admin autenticado | `GET /condominiums/{id}/tree` | 200 + estructura jerárquica: condominio → torres → conteo de unidades |
| 10 | Usuario no autenticado | Cualquier endpoint | 401 |
| 11 | Usuario con rol `residente` | `PATCH /condominiums/{id}/coefficients` | 403 — solo admin gestiona coeficientes |
| 12 | Usuario con rol `residente` | `GET /properties/{id}/coefficients` (su unidad) | 200 — ve sus coeficientes |
| 13 | Usuario con rol `residente` | `GET /properties/{id}/coefficients` (unidad ajena) | 404 — unificado con 403 (R-10) |
| 14 | Usuario de otra org | `GET /condominiums/{id}/tree` | 404 — unificado con 403 (R-10) |
| 15 | Residente | `GET /condominiums/{id}/tree` | 403 — solo admin ve tree completo |
| 16 | Staff con `role_assignment.scope_type=condominium` en condominio A | `PATCH /condominiums/{B_id}/coefficients` (otro condominio, misma org) | 404 — unificado con 403, fuera de su scope (R-09-bis) |
| 17 | Staff con `role_assignment.scope_type=tower` (ej. vigilante) en torre X | `GET /condominiums/{id}/tree` | 403 — el tree completo (con coeficientes/conteos) requiere scope `condominium` u `organization`; scope `tower` es insuficiente, igual que `residente` (criterio 15) |

## Contrato

Este bloque **produce** el contrato de los endpoints `/condominiums/{id}/coefficients`,
`/properties/{id}/coefficients` y `/condominiums/{id}/tree`. Al completar el DoD, se congela en
`_state/contracts/CONTRACT_LOCKS.md` como `LOCK-PROPIEDADES-04`.

## Definition of Done

- [ ] `composer ci` ejecutado — salida completa pegada.
- [ ] Verificación funcional real con request/response para **todos** los casos de la tabla de
      criterios (17 casos) — con énfasis en atomicidad del PATCH masivo (caso 8), cierre automático
      de vigencia (caso 3), warning de suma ≠ 1.0 (caso 6), y scope de staff (casos 16-17).
- [ ] `_state/contracts/CONTRACT_LOCKS.md` — entrada `LOCK-PROPIEDADES-04` creada.
- [ ] `api/API_CONTRACT.md` §3 — agregar `COEFFICIENT_OUT_OF_RANGE` (422), `COEFFICIENT_INVALID_TYPE`
      (422), `PROPERTY_NOT_IN_CONDOMINIUM` (422), `COEFFICIENT_SUM_MISMATCH` (warning, no error —
      documentar en la fila con una nota de que no es un `code` de §3 sino de §4-bis) a la
      documentación correspondiente.
- [ ] `api/endpoints/PROPIEDADES.md` actualizado con el detalle de request/response de los endpoints
      de coeficientes y tree, incluyendo el ejemplo completo del `warnings[]` del caso 6.

## Notas sobre `verificacion_critica`

> Este bloque toca lógica financiera (coeficientes impactan COBRANZA) y un endpoint de modificación
> masiva atómica. El `verify-council` es obligatorio antes de que el verifier pueda marcar `done`.
> Ver [[../../_system/05_DEFINITION_OF_DONE#6. Flag verificacion_critica]].

## Evidencia

- `composer ci` ejecutado 2026-07-08: Pint (205 files ✅), PHPStan level 10 (180/180 ✅), Pest (206 passed, 716 assertions ✅)
- Verify-council: 5 blockers corregidos (N+1 tree, índice property_id, DRY traits, excepción tipada, dead $wrap)
- LOCK-PROPIEDADES-04 creado en CONTRACT_LOCKS.md

## Notas

> El optimistic locking para concurrencia en coeficientes (PANORAMA §X.2) queda como deuda técnica
> explícita. El PATCH masivo atómico mitiga parcialmente el riesgo: si dos admins modifican el mismo
> condominio simultáneamente, la segunda transacción verá los datos actualizados por la primera (nivel
> de aislamiento de BD). Si se detectan problemas en producción, se implementa optimistic locking en
> un bloque futuro.
