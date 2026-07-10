---
tipo: bloque
proyecto: web
feature: COBRANZA
id: COBRANZA-B08
proyectos: [web]
estado: backlog
depende_de: [COBRANZA-B03, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: false
actualizado: 2026-07-09
---

# COBRANZA-B08 — Pantallas de periodos y corrida de facturación

## Objetivo

Construir la pantalla de periodos de facturación y el flujo de disparo/seguimiento de una corrida de
facturación asíncrona — primera vez que la Web maneja el patrón "202 + polling" del vault
(R-COB-22). Integra con `LOCK-COBRANZA-03`.

## Alcance

- **Incluye:**
  - Página `PeriodosFacturacion` (`/cobranza/periodos`): tabla de periodos (año, mes, estado), botón
    "Abrir periodo".
  - Vista de detalle de un periodo: resumen de cartera (`GET .../summary`), botón "Generar
    facturación" (visible solo con `cobranza.facturacion.ejecutar`), listado de corridas previas.
  - **Flujo de corrida de facturación:** al disparar `POST .../billing-runs`, la UI recibe `202` y
    muestra un **banner persistente no bloqueante** ("Facturación en curso...") — nunca un spinner de
    pantalla completa ni un modal que bloquee otras acciones (convergencia UX/Arquitectura del
    council, `PANORAMA.md` §9.2). La UI hace polling de `GET /billing-runs/{id}` hasta
    `completado`/`fallido`, y al terminar muestra el `resumen` (unidades facturadas/omitidas, con
    detalle de motivo por unidad omitida).
  - Botón "Cerrar periodo": si el API devuelve `warnings[]`
    (`BILLING_PERIOD_HAS_PENDING_INVOICES`), el diálogo de confirmación exige un **checkbox
    explícito** de "Entiendo que quedarán facturas pendientes abiertas" antes de habilitar el botón de
    confirmar cierre (R-COB-08-bis, mecanismo de UI explícitamente exigido por el panorama).
  - Widget de resumen de cartera del periodo activo, reutilizable (mismo componente que eventualmente
    consumirá `DASHBOARD` cuando se resuelva la acción pendiente cross-feature — este bloque no
    modifica `DASHBOARD`, solo construye el componente de forma que sea reusable).
  - Integración con `LOCK-COBRANZA-03` (8 endpoints).
  - Documentación de pantalla en `web/features/cobranza/COBRANZA-periodos-facturacion.md`.

- **No incluye (explícitamente fuera de este bloque):**
  - Listado/detalle de facturas individuales — `COBRANZA-B09`.
  - Modificar `features/DASHBOARD/PANORAMA.md` o el widget real de Dashboard — acción pendiente
    separada, ver `BLOCKS.md`.
  - Reintento automático de un `billing_run` fallido — el usuario debe disparar uno nuevo
    manualmente desde la misma pantalla.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Admin con `cobranza.facturacion.ejecutar` | Click "Abrir periodo", completar año/mes | POST exitoso, periodo `abierto` en tabla |
| 2 | Periodo `abierto`, admin con `cobranza.facturacion.ejecutar` | Click "Generar facturación" | `202` recibido, banner persistente "Facturación en curso" aparece, resto de la UI sigue usable |
| 3 | Banner activo, `billing_run` procesando | Esperar a que el Job complete | Polling detecta `completado`, banner se actualiza mostrando `resumen` (facturadas/omitidas) |
| 4 | `billing_run` completado con 2 unidades omitidas | Ver el resumen | Detalle visible de cada unidad omitida con su motivo (ej. "sin coeficiente vigente") |
| 5 | Admin con `cobranza.periodos.ver` (sin `.ejecutar`) | Ver periodo | Botón "Generar facturación" no visible o deshabilitado |
| 6 | Periodo `abierto` con facturas `pendiente` | Click "Cerrar periodo" | Diálogo muestra el warning y un checkbox obligatorio; el botón de confirmar permanece deshabilitado hasta marcarlo |
| 7 | Checkbox marcado en el diálogo del #6 | Click "Confirmar cierre" | PATCH exitoso, periodo pasa a `cerrado` |
| 8 | Periodo `abierto` sin facturas pendientes | Click "Cerrar periodo" | Diálogo de confirmación simple, sin checkbox obligatorio (no hay warning) |
| 9 | `billing_run` disparado dos veces mientras el primero sigue `en_proceso` | Click "Generar facturación" de nuevo | UI bloquea el botón mientras hay una corrida `en_proceso` visible, o muestra el error `409` del API de forma clara |
| 10 | Widget de resumen de cartera del periodo activo | Ver la vista de detalle | Datos coinciden con `GET .../active/summary` |

## Contrato

Este bloque **consume** el contrato `LOCK-COBRANZA-03` (producido por `COBRANZA-B03`). No puede pasar
a `ready` sin ese lock vigente.

## Definition of Done

- [ ] `pnpm ci` ejecutado — salida completa pegada.
- [ ] Verificación visual real (Playwright MCP o equivalente) recorriendo los 10 criterios,
      **incluido el ciclo completo de polling real** (no mockeado) desde `202` hasta `resumen`
      mostrado.
- [ ] Confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que la integración respeta exactamente
      `LOCK-COBRANZA-03`.
- [ ] `web/features/cobranza/COBRANZA-periodos-facturacion.md` creado.
- [ ] Componentes de la librería base de `WEB_BOOTSTRAP-B01`.
- [ ] `web/WEB_API_CLIENT.md` actualizado, incluyendo el hook de polling como patrón documentado
      (primer uso del patrón 202+polling en Web).

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> El hook/componente de polling que este bloque construye (`useBillingRunPolling` o equivalente) es
> candidato a reutilizarse en cualquier feature futura que adopte el patrón 202+polling — documentar
> en `web/WEB_API_CLIENT.md` de forma genérica, no acoplada solo a `billing_runs`.
