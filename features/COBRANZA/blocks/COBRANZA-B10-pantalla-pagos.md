---
tipo: bloque
proyecto: web
feature: COBRANZA
id: COBRANZA-B10
proyectos: [web]
estado: backlog
depende_de: [COBRANZA-B05, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: false
actualizado: 2026-07-09
---

# COBRANZA-B10 — Pantalla de registro de pagos

## Objetivo

Construir el flujo de registro manual de pagos/abonos y su anulación — la pantalla de mayor
sensibilidad financiera del lado Web, sobre `LOCK-COBRANZA-05`. Extiende el detalle de factura
construido en `COBRANZA-B09`.

## Alcance

- **Incluye:**
  - Página `Pagos` (`/cobranza/pagos`): listado de `payment_receipts` del condominio con filtros.
  - Formulario "Registrar pago" (accesible desde el listado y desde el detalle de una factura en
    `COBRANZA-B09`): `contact_id` (buscador), `valor`, `fecha`, `medio` (`efectivo`/`banco`),
    `referencia`, adjunto de soporte (usa el endpoint de upload de `COBRANZA-B05`), y selector de
    una o varias facturas con distribución de `valor_aplicado` por factura.
  - **Validación en el cliente de distribución exacta** (espejo de R-COB-23, antes de enviar al API):
    la suma de `valor_aplicado` debe igualar `valor` del pago — el formulario muestra en tiempo real
    cuánto falta/sobra y deshabilita "Guardar" hasta que cuadre exacto. Esto es UX, no reemplaza la
    validación del API (que sigue siendo la fuente de verdad).
  - Envío de `Idempotency-Key` generado por el cliente en cada `POST /payment-receipts`, para que un
    reintento de red del propio navegador no duplique el registro (R-COB-25).
  - Manejo explícito del `429` de throttle (R-COB-27) con mensaje claro, no un error genérico.
  - Vista de detalle de un pago: facturas afectadas, adjunto de soporte.
  - Botón "Anular pago" (visible solo con `pagos.anular`) con diálogo de confirmación.
  - Integración con `LOCK-COBRANZA-05` (5 endpoints).
  - Documentación de pantalla en `web/features/cobranza/COBRANZA-pagos.md`.

- **No incluye (explícitamente fuera de este bloque):**
  - Paz y salvo — `COBRANZA-B11`.
  - Pagos online/pasarela — fuera de alcance de Fase 1 en todo el feature.
  - "Saldo a favor" — no existe en el modelo, el formulario no debe sugerir que es posible sobre-aplicar.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Admin con `pagos.registrar`, factura con `saldo = 100000` | Registrar pago de `100000` aplicado 100% a esa factura | POST exitoso, factura pasa a `pagada` en la vista |
| 2 | Formulario con `valor: 100000`, distribución que suma `90000` | Intentar guardar | Botón "Guardar" deshabilitado, mensaje "Faltan $10.000 por aplicar" |
| 3 | Formulario con `valor: 100000`, distribución que suma `110000` | Intentar guardar | Botón "Guardar" deshabilitado, mensaje "Sobran $10.000" |
| 4 | Formulario completo y válido | Click "Guardar" dos veces rápido (doble click accidental) | Solo un `payment_receipt` creado — la `Idempotency-Key` del cliente previene el duplicado |
| 5 | Adjunto de soporte tipo `.exe` | Intentar subir | Rechazado en el cliente antes de enviar (validación espejo), o error claro del API si llega a enviarse |
| 6 | Admin con `pagos.registrar` (sin `pagos.anular`) | Ver un pago | Sin botón "Anular" |
| 7 | Admin con `pagos.anular` | Click "Anular" → confirmar | DELETE exitoso, factura(s) afectadas actualizan `saldo`/`estado` en la vista |
| 8 | 61 registros de pago intentados en 1 minuto (test) | Enviar el intento 61 | Mensaje claro de límite alcanzado, no un error genérico |
| 9 | Registrar pago desde el detalle de una factura (`COBRANZA-B09`) | Click "Registrar pago" en el detalle | Formulario pre-carga esa factura en la distribución |

## Contrato

Este bloque **consume** el contrato `LOCK-COBRANZA-05` (producido por `COBRANZA-B05`). No puede pasar
a `ready` sin ese lock vigente.

## Definition of Done

- [ ] `pnpm ci` ejecutado — salida completa pegada.
- [ ] Verificación visual real (Playwright MCP o equivalente) recorriendo los 9 criterios.
- [ ] Confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que la integración respeta exactamente
      `LOCK-COBRANZA-05`, incluido el header `Idempotency-Key`.
- [ ] `web/features/cobranza/COBRANZA-pagos.md` creado.
- [ ] Componentes de la librería base de `WEB_BOOTSTRAP-B01`.
- [ ] `web/WEB_API_CLIENT.md` actualizado.

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> La validación de distribución exacta en el cliente (#2/#3) es UX pura — el criterio de aceptación
> real de integridad financiera vive en `COBRANZA-B05` (R-COB-23) y ya está verificado ahí. Este
> bloque no re-verifica la regla de negocio, solo confirma que la UI comunica el estado antes de que
> el usuario descubra el error por un `422`.
