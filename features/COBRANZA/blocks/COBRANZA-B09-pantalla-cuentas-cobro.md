---
tipo: bloque
proyecto: web
feature: COBRANZA
id: COBRANZA-B09
proyectos: [web]
estado: backlog
depende_de: [COBRANZA-B04, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: false
actualizado: 2026-07-09
---

# COBRANZA-B09 — Pantalla de cuentas de cobro (facturas)

## Objetivo

Construir el listado y detalle de `invoices` para staff administrativo, con la gestión de ítems
manuales. Integra con `LOCK-COBRANZA-04`.

## Alcance

- **Incluye:**
  - Página `Facturas` (`/cobranza/facturas`): tabla con filtros (`property_id`, `billing_period_id`,
    `estado`, búsqueda), columnas (unidad, periodo, valor total, saldo, estado con badge de color por
    estado — `pendiente`/`parcial`/`pagada`/`vencida`).
  - Vista de detalle de una factura: ítems (`invoice_items`, distinguiendo automáticos de manuales),
    `payment_allocations[]` aplicadas (vacío hasta que `COBRANZA-B10` exista, pero el detalle ya debe
    poder mostrarlas cuando existan).
  - Formulario "Agregar ítem manual" — solo conceptos con `metodo_calculo = manual` seleccionables.
  - Edición/eliminación de ítem manual, **deshabilitada visualmente** (no solo por error del API)
    cuando la factura ya tiene `payment_allocations` aplicada — mensaje explicando por qué
    (R-COB-24), para que el usuario no descubra la restricción solo al fallar el submit.
  - Integración con `LOCK-COBRANZA-04` (6 endpoints).
  - Documentación de pantalla en `web/features/cobranza/COBRANZA-facturas.md`.

- **No incluye (explícitamente fuera de este bloque):**
  - Registro de pagos — `COBRANZA-B10`. Este bloque solo lee/muestra `payment_allocations`
    existentes, no las crea.
  - Vista de estado de cuenta del residente (`/me/invoices`) — pertenece a `PORTAL_RESIDENTE` (Fase
    1.6, feature futura), no a esta pantalla de administración de staff.
  - Paz y salvo — `COBRANZA-B11`.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Admin con `cobranza.facturas.ver` | Navegar a `/cobranza/facturas` | Tabla con facturas del condominio, badges de estado coherentes con la derivación del API |
| 2 | Tabla cargada | Filtrar por `estado=vencida` | Solo facturas vencidas visibles |
| 3 | Admin con `cobranza.facturas.ver` | Click en una factura | Detalle con ítems y `payment_allocations[]` (vacío o poblado según el caso) |
| 4 | Admin con `cobranza.facturas.gestionar`, factura sin pagos aplicados | Agregar ítem manual | POST exitoso, `valor_total` de la factura se actualiza en la vista |
| 5 | Igual, ítem manual existente | Editar valor | PATCH exitoso, total recalculado |
| 6 | Factura **con** `payment_allocations` aplicada | Ver ítems manuales | Botones de editar/eliminar deshabilitados con mensaje explicando la inmutabilidad (R-COB-24) |
| 7 | Admin con `cobranza.facturas.ver` (sin `.gestionar`) | Ver detalle de factura | Sin botón "Agregar ítem manual" |
| 8 | Formulario de ítem manual | Seleccionar concepto con `metodo_calculo != manual` | No aparece como opción seleccionable |
| 9 | API no disponible | Cualquier acción | Toast de error genérico |

## Contrato

Este bloque **consume** el contrato `LOCK-COBRANZA-04` (producido por `COBRANZA-B04`). No puede pasar
a `ready` sin ese lock vigente.

## Definition of Done

- [ ] `pnpm ci` ejecutado — salida completa pegada.
- [ ] Verificación visual real (Playwright MCP o equivalente) recorriendo los 9 criterios.
- [ ] Confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que la integración respeta exactamente
      `LOCK-COBRANZA-04`.
- [ ] `web/features/cobranza/COBRANZA-facturas.md` creado.
- [ ] Componentes de la librería base de `WEB_BOOTSTRAP-B01`.
- [ ] `web/WEB_API_CLIENT.md` actualizado.

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> El detalle de factura construido aquí es el mismo componente que `COBRANZA-B10` extiende para
> mostrar el formulario de registrar pago — no se duplica la vista de detalle, se agrega una sección
> nueva sobre esta base.
