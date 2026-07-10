---
tipo: bloque
proyecto: api
feature: COBRANZA
id: COBRANZA-B04
proyectos: [api]
estado: backlog
depende_de: [COBRANZA-B03]
contrato: null
verificacion_critica: false
actualizado: 2026-07-09
---

# COBRANZA-B04 — Cuentas de cobro, ítems manuales y estado de cuenta del residente

## Objetivo

Exponer lectura de `invoices` generadas por `COBRANZA-B03`, la edición controlada de ítems manuales
(R-COB-24), y los endpoints de solo-lectura para el residente (`/me/invoices`) que
`PORTAL_RESIDENTE` consumirá en el futuro sin rediseño (`PANORAMA.md` §2).

## Alcance

- **Incluye:**
  - `GET /condominiums/{id}/invoices` — listado con filtros `?property_id=&billing_period_id=&estado=&search=`.
  - `GET /invoices/{id}` — detalle, incluye `payment_allocations[]` anidado (vacío hasta
    `COBRANZA-B05`).
  - **Derivación de `invoices.estado` en lectura** (R-COB-08, revisada): calculado en el backend al
    serializar — `CASE WHEN saldo = 0 THEN 'pagada' WHEN fecha_vencimiento < CURRENT_DATE AND saldo >
    0 THEN 'vencida' WHEN saldo < valor_total THEN 'parcial' ELSE 'pendiente' END` — expuesto igual en
    todos los endpoints que devuelven una `invoice` (listado, detalle, `/me/invoices`), para que Web y
    el futuro Portal/App no dupliquen la lógica de fecha.
  - `POST /invoices/{id}/items` — agregar ítem manual (`charge_concept.metodo_calculo = manual`).
  - `PATCH` / `DELETE /invoice-items/{id}` — corregir/eliminar ítem manual, **solo si** la `invoice`
    padre no tiene `payment_allocations` aplicada (R-COB-24). Recalcula `invoices.valor_total` al
    editar/eliminar.
  - `GET /me/invoices` — estado de cuenta del residente autenticado, scopeado por
    `property_occupants` activo del usuario (R-COB-03, lectura cross-context de `Directorio`).
  - `GET /me/invoices/{id}` — detalle. **404 uniforme** (nunca `403`) cuando la factura no pertenece a
    ninguna unidad del residente autenticado (R-COB-20, mismo patrón anti-enumeración de
    `PROPIEDADES`).
  - Middleware RBAC: `cobranza.facturas.ver`, `cobranza.facturas.gestionar`.

- **No incluye (explícitamente fuera de este bloque):**
  - Generación de `invoices` — ya ocurrió en `COBRANZA-B03`.
  - Registro de pagos que afecten `payment_allocations`/`saldo` — `COBRANZA-B05`. Este bloque solo
    lee y expone el arreglo (vacío hasta entonces).
  - Paz y salvo — `COBRANZA-B06`.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Usuario con `cobranza.facturas.ver`, factura con `saldo = valor_total`, `fecha_vencimiento` futura | `GET /invoices/{id}` | `estado: pendiente` |
| 2 | Igual, `fecha_vencimiento` pasada, `saldo > 0` | `GET /invoices/{id}` | `estado: vencida` |
| 3 | Igual, `saldo = 0` (aunque esté vencida) | `GET /invoices/{id}` | `estado: pagada` — `saldo = 0` tiene prioridad sobre fecha vencida |
| 4 | Usuario con `cobranza.facturas.gestionar` | `POST /invoices/{id}/items` con `charge_concept.metodo_calculo = manual` | `201`, `invoices.valor_total` recalculado |
| 5 | Usuario con `cobranza.facturas.gestionar` | `POST /invoices/{id}/items` con `charge_concept.metodo_calculo = coeficiente` | `422` — solo conceptos `manual` se agregan por esta vía |
| 6 | Ítem manual sin `payment_allocations` sobre su `invoice` | `PATCH /invoice-items/{id}` | `200`, editado, `valor_total` recalculado |
| 7 | Ítem manual cuya `invoice` **sí** tiene `payment_allocations` aplicada | `PATCH /invoice-items/{id}` | `409` — inmutabilidad progresiva (R-COB-24) |
| 8 | Residente autenticado, unidad propia con facturas | `GET /me/invoices` | `200`, solo facturas de sus unidades (`property_occupants` activo) |
| 9 | Residente autenticado | `GET /me/invoices/{id}` de una factura de **otra** unidad | `404` (nunca `403`) — R-COB-20 |
| 10 | Residente autenticado, unidad sin ocupación activa (mudanza) | `GET /me/invoices` | Lista vacía o excluye esa unidad — R-COB-03 exige ocupación activa |
| 11 | Usuario **sin** `cobranza.facturas.ver` | `GET /condominiums/{id}/invoices` | `403` |

## Contrato

Este bloque **produce** contrato — al llegar a `done`, se crea `LOCK-COBRANZA-04` en
`_state/contracts/CONTRACT_LOCKS.md` para los 6 endpoints, consumido por `COBRANZA-B09` (y, a futuro,
por `PORTAL_RESIDENTE` para `/me/invoices*`, sin bloque nuevo de API cuando eso ocurra).

## Definition of Done

- [ ] `composer ci` ejecutado — salida completa pegada.
- [ ] Verificación funcional real cubriendo los 11 criterios, incluido el caso de prioridad `saldo=0`
      sobre fecha vencida (#3) y el 404 uniforme de residente (#9) — request/response reales pegados.
- [ ] `LOCK-COBRANZA-04` creado en `_state/contracts/CONTRACT_LOCKS.md`.
- [ ] `api/API_CONTRACT.md` actualizado.
- [ ] `api/endpoints/COBRANZA.md` actualizado con el detalle de estos 6 endpoints, incluyendo la
      expresión exacta de derivación de `estado`.

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> No existe scheduler en la infraestructura (`API_BOOTSTRAP` no lo define) — `estado: vencida` es una
> expresión de consulta con índice (`fecha_vencimiento`, `COBRANZA-B01`), no un campo que un job
> actualiza periódicamente. Si un futuro requisito necesita notificar vencimientos proactivamente
> (no solo mostrarlos en lectura), eso es un feature nuevo (`REPORTES` o notificaciones), no una
> extensión de este bloque.
