---
tipo: bloque
proyecto: api
feature: COBRANZA
id: COBRANZA-B03
proyectos: [api]
estado: backlog
depende_de: [COBRANZA-B02]
contrato: null
verificacion_critica: true
actualizado: 2026-07-09
---

# COBRANZA-B03 — Periodos de facturación y corrida de facturación asíncrona

## Objetivo

Exponer el ciclo de vida de `billing_periods` (`abierto → facturado → cerrado`) y la corrida de
facturación (`billing_runs`) que prorratea gastos comunes por coeficiente de copropiedad — el motor
de cálculo financiero del feature y la primera vez que el contrato de API necesita el patrón
"202 + polling" (R-COB-22). `verificacion_critica: true` porque es lógica de cálculo financiero que
genera las `invoices` reales que se cobran a cada unidad.

## Alcance

- **Incluye:**
  - `GET /condominiums/{id}/billing-periods` — listado.
  - `POST /condominiums/{id}/billing-periods` — abrir periodo (`anio`+`mes`, `UNIQUE` por condominio).
  - `GET /billing-periods/{id}` — detalle.
  - `PATCH /billing-periods/{id}` — cerrar periodo (`estado: cerrado`). Si hay facturas
    `pendiente`/`parcial`, responde `200` con `warnings: [{code:
    "BILLING_PERIOD_HAS_PENDING_INVOICES"}]` en vez de `409` (R-COB-08-bis) — reutiliza el mecanismo
    de `warnings[]` fijado en `COBRANZA-B02`.
  - `POST /billing-periods/{id}/billing-runs` — dispara la corrida. Responde `202` de inmediato con
    `billing_runs.estado = en_proceso` (R-COB-22). El prorrateo real corre en un Job encolado
    (`jobs`/`job_batches`, ya disponibles desde `API_BOOTSTRAP`): por cada unidad activa del
    condominio (R-COB-05), lee `property_coefficients` vigentes de tipo `copropiedad` (lectura
    cross-context de `Properties`, ver
    [[../../../shared/adr/ADR-002-lectura-cross-context-modulo-monolito]]), genera `invoices` +
    `invoice_items` con `base_calculo` como snapshot inmutable (R-COB-06), y omite unidades sin
    coeficiente vigente registrando el motivo en `billing_runs.resumen` (decisión 8:
    `{unidades_facturadas, unidades_omitidas, detalle_omitidas: [{property_id, motivo}]}`).
  - `GET /billing-periods/{id}/billing-runs` — listado de corridas del periodo.
  - `GET /billing-runs/{id}` — detalle para polling (incluye `resumen` cuando `estado != en_proceso`).
  - `GET /condominiums/{id}/billing-periods/active/summary` — panel de cartera del periodo activo
    (endpoint que `DASHBOARD` espera consumir eventualmente, ver `BLOCKS.md` "Acción pendiente
    cross-feature").
  - `GET /billing-periods/{id}/summary` — panel de cartera de un periodo específico.
  - Documentar el patrón "202 + polling" como convención general nueva en `api/API_CONTRACT.md`
    (R-COB-22), no como excepción puntual de esta feature.
  - Middleware RBAC: `cobranza.periodos.ver`, `cobranza.facturacion.ejecutar`.

- **No incluye (explícitamente fuera de este bloque):**
  - Listado/detalle de `invoices` individuales fuera del `resumen` agregado — `COBRANZA-B04`.
  - Registro de pagos — `COBRANZA-B05`.
  - `invoices.estado` derivado (`vencida`/`pagada`/etc.) — se define y expone en `COBRANZA-B04`, este
    bloque solo escribe filas de `invoices` con `saldo = valor_total` al crearlas.
  - Reintentar automáticamente un `billing_run` que llegó a `fallido` — el usuario dispara uno nuevo
    manualmente; no hay reintento automático en Fase 1.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Usuario con `cobranza.facturacion.ejecutar` | `POST .../billing-periods` con `anio=2026, mes=7` | `201`, `estado: abierto` |
| 2 | Periodo ya existe para ese `anio`+`mes` | `POST .../billing-periods` con los mismos valores | `422` — `UNIQUE(condominium_id, anio, mes)` |
| 3 | Periodo `abierto`, condominio con 10 unidades activas (8 con coeficiente vigente, 2 sin) | `POST /billing-periods/{id}/billing-runs` | `202` inmediato, `billing_runs.estado = en_proceso` |
| 4 | `billing_run` del #3 procesado por el Job | `GET /billing-runs/{id}` (polling tras completar) | `estado: completado`, `resumen: {unidades_facturadas: 8, unidades_omitidas: 2, detalle_omitidas: [...]}` |
| 5 | `billing_run` completado del #3 | `Invoice::where('billing_run_id', $id)->count()` | `8` — una por unidad con coeficiente vigente, ninguna para las 2 omitidas |
| 6 | `billing_run` `en_proceso` para un periodo | Disparar `POST .../billing-runs` de nuevo para el mismo periodo | `409` o error de negocio — no se permite un segundo `billing_run` concurrente completado (R-COB-09), verificado también a nivel BD por el `UNIQUE` parcial de `COBRANZA-B01` |
| 7 | Periodo `abierto` con facturas `pendiente` | `PATCH /billing-periods/{id}` con `{estado: cerrado}` | `200` con `warnings: [{code: "BILLING_PERIOD_HAS_PENDING_INVOICES"}]`, periodo pasa a `cerrado` de todas formas (no bloqueante) |
| 8 | Periodo `abierto` con todas las facturas `pagada` | `PATCH /billing-periods/{id}` con `{estado: cerrado}` | `200` sin `warnings[]` |
| 9 | Usuario con `cobranza.periodos.ver` (sin `.ejecutar`) | `POST .../billing-runs` | `403` — segregación ver/ejecutar (la acción de mayor impacto del feature) |
| 10 | Usuario con `billing.ver` únicamente | `GET /condominiums/{id}/billing-periods/active/summary` | `200` — este endpoint usa `billing.ver`, no `cobranza.periodos.ver`, para no bloquear el widget de `DASHBOARD` |
| 11 | Unidad sin `property_coefficients` vigente de tipo `copropiedad` | Corrida de facturación sobre esa unidad | La unidad se omite (no genera `invoice`), aparece en `detalle_omitidas` con motivo `"sin coeficiente vigente"` |

## Contrato

Este bloque **produce** contrato — al llegar a `done`, se crea `LOCK-COBRANZA-03` en
`_state/contracts/CONTRACT_LOCKS.md` para los 8 endpoints de periodos/facturación, consumido por
`COBRANZA-B08`. **Este es también el lock que `DASHBOARD` necesita** para su widget de cartera —
documentar explícitamente el endpoint `GET /condominiums/{id}/billing-periods/active/summary` en el
lock para que quien resuelva la acción pendiente cross-feature (ver `BLOCKS.md`) lo encuentre.

## Definition of Done

- [ ] `composer ci` ejecutado — salida completa pegada.
- [ ] Verificación funcional real cubriendo los 11 criterios, incluido el ciclo completo `202` →
      polling → `completado` con `resumen` real — request/response reales pegados.
- [ ] Verificación de que el Job de facturación corre en cola real (no síncrono simulando 202) —
      salida de `queue:work` o equivalente pegada.
- [ ] `LOCK-COBRANZA-03` creado en `_state/contracts/CONTRACT_LOCKS.md`, incluyendo el endpoint que
      `DASHBOARD` consumirá.
- [ ] `api/API_CONTRACT.md` actualizado con los 8 endpoints nuevos y la convención general "202 +
      polling" documentada como patrón reusable.
- [ ] `api/endpoints/COBRANZA.md` actualizado con el detalle de estos 8 endpoints.
- [ ] Dado que `verificacion_critica: true`, el `verify-council` debe emitir veredicto antes de que
      este bloque pase a `done` — no basta el verificador solo.

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> Este bloque es el más sensible de la cadena API: si el prorrateo tiene un error de redondeo o
> selección de unidades, todas las facturas de un periodo quedan mal desde el origen. El
> `verify-council` debe incluir al menos un caso con coeficientes que no sumen exactamente 1.0000
> (mismo espíritu que `COEFFICIENT_SUM_MISMATCH` de `PROPIEDADES`) para confirmar que el prorrateo no
> asume silenciosamente que la suma es perfecta.
