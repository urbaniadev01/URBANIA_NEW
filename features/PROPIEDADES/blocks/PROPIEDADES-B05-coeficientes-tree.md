---
tipo: bloque
proyecto: api
feature: PROPIEDADES
id: PROPIEDADES-B05
proyectos: [api]
estado: backlog
depende_de: [PROPIEDADES-B01]
contrato: produce
verificacion_critica: true
actualizado: 2026-07-06
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
  - Validación de `valor` en rango 0–1 (fracción decimal).
  - Validación de unicidad de coeficiente vigente: solo uno por `property_id + tipo` con
    `vigente_hasta IS NULL` (R-05). Al crear uno nuevo, se cierra automáticamente el anterior
    (`vigente_hasta = hoy - 1 día`).
  - Validación de suma de coeficientes de copropiedad = 1.0 (100%) en el PATCH masivo: si la suma
    no es 1.0, se emite un **warning** en la respuesta (no un error — R-06). COBRANZA usará
    valores normalizados.
  - PATCH masivo atómico: si cualquier operación falla, se hace rollback de toda la transacción.
  - Autorización: solo admin puede gestionar coeficientes. Residente solo ve los coeficientes de su
    propia unidad.
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
| 2 | Admin, condominio con unidades | `PATCH /condominiums/{id}/coefficients` con body válido `[{property_id, tipo: "copropiedad", valor: 0.25}, ...]` | 200 + coeficientes creados/actualizados |
| 3 | Coeficiente vigente existente para misma `property_id + tipo` | `PATCH .../coefficients` con nuevo valor | Coeficiente anterior cerrado (`vigente_hasta` seteado), nuevo creado vigente (R-05) |
| 4 | `valor` fuera de rango (ej. 1.5 o -0.1) | `PATCH .../coefficients` | 422 — valor fuera de rango 0–1 |
| 5 | `tipo` no reconocido (ej. "jardin") | `PATCH .../coefficients` | 422 — tipo no válido |
| 6 | Suma de coeficientes de copropiedad ≠ 1.0 | `PATCH .../coefficients` | 200 + warning en response body (no 422, R-06) |
| 7 | `property_id` que no pertenece al condominio | `PATCH /condominiums/{id}/coefficients` | 422 — propiedad no pertenece a este condominio |
| 8 | PATCH con múltiples items, uno inválido | `PATCH .../coefficients` | 422 — rollback completo, ningún coeficiente modificado (atomicidad) |
| 9 | Admin autenticado | `GET /condominiums/{id}/tree` | 200 + estructura jerárquica: condominio → torres → conteo de unidades |
| 10 | Usuario no autenticado | Cualquier endpoint | 401 |
| 11 | Usuario con rol `residente` | `PATCH /condominiums/{id}/coefficients` | 403 — solo admin gestiona coeficientes |
| 12 | Usuario con rol `residente` | `GET /properties/{id}/coefficients` (su unidad) | 200 — ve sus coeficientes |
| 13 | Usuario con rol `residente` | `GET /properties/{id}/coefficients` (unidad ajena) | 404 — unificado con 403 (R-10) |
| 14 | Usuario de otra org | `GET /condominiums/{id}/tree` | 404 — unificado con 403 (R-10) |
| 15 | Residente | `GET /condominiums/{id}/tree` | 403 — solo admin ve tree completo |

## Contrato

Este bloque **produce** el contrato de los endpoints `/condominiums/{id}/coefficients`,
`/properties/{id}/coefficients` y `/condominiums/{id}/tree`. Al completar el DoD, se congela en
`_state/contracts/CONTRACT_LOCKS.md` como `LOCK-PROPIEDADES-04`.

## Definition of Done

- [ ] `composer ci` ejecutado — salida completa pegada.
- [ ] Verificación funcional real con request/response para **todos** los casos de la tabla de
      criterios (15 casos) — con énfasis en atomicidad del PATCH masivo (caso 8), cierre automático
      de vigencia (caso 3), y warning de suma ≠ 1.0 (caso 6).
- [ ] `_state/contracts/CONTRACT_LOCKS.md` — entrada `LOCK-PROPIEDADES-04` creada.
- [ ] `api/API_CONTRACT.md` actualizado con los nuevos endpoints y códigos de error (incluyendo el
      código de warning para suma ≠ 100%).
- [ ] `api/endpoints/PROPIEDADES.md` actualizado con el detalle de request/response de los endpoints
      de coeficientes y tree.

## Notas sobre `verificacion_critica`

> Este bloque toca lógica financiera (coeficientes impactan COBRANZA) y un endpoint de modificación
> masiva atómica. El `verify-council` es obligatorio antes de que el verifier pueda marcar `done`.
> Ver [[../../_system/05_DEFINITION_OF_DONE#6. Flag verificacion_critica]].

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> El optimistic locking para concurrencia en coeficientes (PANORAMA §X.2) queda como deuda técnica
> explícita. El PATCH masivo atómico mitiga parcialmente el riesgo: si dos admins modifican el mismo
> condominio simultáneamente, la segunda transacción verá los datos actualizados por la primera (nivel
> de aislamiento de BD). Si se detectan problemas en producción, se implementa optimistic locking en
> un bloque futuro.
