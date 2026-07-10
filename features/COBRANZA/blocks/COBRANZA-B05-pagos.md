---
tipo: bloque
proyecto: api
feature: COBRANZA
id: COBRANZA-B05
proyectos: [api]
estado: backlog
depende_de: [COBRANZA-B04]
contrato: null
verificacion_critica: true
actualizado: 2026-07-09
---

# COBRANZA-B05 — Registro y anulación de pagos, con locking e idempotencia

## Objetivo

Exponer el registro manual de pagos/abonos (`payment_receipts`) y su distribución exacta sobre una o
varias facturas (`payment_allocations`), con las tres protecciones de integridad financiera que el
Design Council identificó como críticas: locking pesimista en el recálculo de `saldo` (R-COB-21),
idempotencia ante reintento de red (R-COB-25), y distribución exacta al 100% del recibo (R-COB-23).
`verificacion_critica: true` — es la superficie de mayor riesgo financiero del feature.

## Alcance

- **Incluye:**
  - `GET /condominiums/{id}/payment-receipts` — listado.
  - `GET /payment-receipts/{id}` — detalle.
  - `POST /payment-receipts` — registrar pago/abono. Body incluye `payment_allocations[]`
    (`invoice_id`, `valor_aplicado` por cada una). Dentro de una única transacción:
    1. `SELECT ... FOR UPDATE` sobre cada `invoice` referenciada en `payment_allocations[]`
       (R-COB-21).
    2. Valida que `SUM(valor_aplicado)` sea exactamente igual a `payment_receipts.valor` (R-COB-23) —
       si no, `422` con error accionable (indicar cuánto falta/sobra), sin persistir nada.
    3. Inserta `payment_receipts` + `payment_allocations` y recalcula `saldo` de cada `invoice`
       afectada, todo dentro de la transacción.
    - Idempotencia (R-COB-25): acepta header `Idempotency-Key`; si se reintenta la misma key, devuelve
      la respuesta original sin duplicar el registro. Sin header, rechaza un recibo idéntico en
      `contact_id`+`valor`+`fecha`+`property_id` dentro de una ventana corta (ej. 60s) como red de
      seguridad.
    - Throttle `throttle:60,1` por usuario (R-COB-27, segunda línea de defensa tras RBAC).
  - `DELETE /payment-receipts/{id}` — anular. Requiere `pagos.anular` (nunca el mismo rol de sistema
    que `pagos.registrar` por defecto, R-COB-13). Revierte `saldo` de las `invoices` afectadas dentro
    de la misma disciplina transaccional con lock.
  - `referencia` nunca se loguea en texto plano (R-COB-26) — redactar en logs de request/error.
  - Endpoint de upload real detrás de `soporte_url` (R-COB-27, punto ciego cerrado por el council en
    `PANORAMA.md` §9.4 punto 3): `POST /payment-receipts/attachments` (o ruta equivalente) valida tipo
    de archivo (imagen/PDF) y tamaño máximo, sirve desde storage propio del tenant — nunca acepta una
    URL de tercero tal cual (prevención SSRF).
  - Middleware RBAC: `pagos.registrar`, `pagos.anular`.

- **No incluye (explícitamente fuera de este bloque):**
  - "Saldo a favor"/crédito no aplicado — explícitamente fuera de Fase 1 (R-COB-23), el endpoint
    rechaza en vez de modelar crédito.
  - Paz y salvo — `COBRANZA-B06`, que sí depende del `saldo = 0` que este bloque mantiene correcto.
  - Pagos online/pasarela — `PAGOS_ONLINE`, Fase 2.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Usuario con `pagos.registrar`, factura con `saldo = 100000` | `POST /payment-receipts` con `valor: 100000`, `payment_allocations: [{invoice_id, valor_aplicado: 100000}]` | `201`, `invoice.saldo` recalculado a `0`, `estado` deriva a `pagada` |
| 2 | Igual, pero `payment_allocations` distribuido sobre 2 facturas | `POST /payment-receipts` con suma exacta de `valor_aplicado` = `valor` del recibo | `201`, ambas facturas actualizan `saldo` |
| 3 | `valor: 100000`, `payment_allocations` suma `90000` | `POST /payment-receipts` | `422`, error accionable indicando que faltan `10000` — no se persiste nada |
| 4 | `valor: 100000`, `payment_allocations` suma `110000` | `POST /payment-receipts` | `422` — no se permite sobre-aplicar (no hay crédito a favor en Fase 1) |
| 5 | Dos requests concurrentes de `POST /payment-receipts` sobre la misma `invoice` con `saldo` suficiente para solo una | Ejecutar en paralelo (test de concurrencia real, no mockeado) | Una tiene éxito, la otra recibe error de saldo insuficiente tras el lock — `saldo` final nunca queda negativo ni inconsistente (R-COB-21) |
| 6 | `POST /payment-receipts` con `Idempotency-Key: abc123` | Reenviar la misma request con la misma key | Segunda respuesta idéntica a la primera, **sin** crear un segundo `payment_receipt` (R-COB-25) |
| 7 | `POST /payment-receipts` sin `Idempotency-Key`, mismo `contact_id`+`valor`+`fecha`+`property_id` que uno registrado hace 5 segundos | Reenviar | Rechazado como probable duplicado por reintento de red |
| 8 | Usuario con `pagos.registrar` (sin `pagos.anular`) | `DELETE /payment-receipts/{id}` | `403` — segregación de funciones (R-COB-13) |
| 9 | Usuario con `pagos.anular` | `DELETE /payment-receipts/{id}` de un pago existente | `204`, `saldo` de las `invoices` afectadas revertido correctamente |
| 10 | Log de aplicación tras un `POST /payment-receipts` con `referencia` presente | Inspeccionar el log generado | `referencia`, `valor`, `valor_aplicado` no aparecen en texto plano (R-COB-26) |
| 11 | Usuario autenticado, 61 requests a `POST /payment-receipts` en 1 minuto | Ejecutar la request 61 | `429` — throttle activo (R-COB-27) |
| 12 | Archivo `.exe` enviado a `POST /payment-receipts/attachments` | Subir | `422` — tipo de archivo rechazado |
| 13 | URL externa (`https://evil.example/file`) enviada como `soporte_url` directamente en `POST /payment-receipts` sin pasar por el endpoint de upload | `POST /payment-receipts` con `soporte_url` apuntando a dominio externo | Rechazado o ignorado — `soporte_url` solo acepta rutas de storage propio del tenant, nunca una URL arbitraria de input directo (prevención SSRF) |

## Contrato

Este bloque **produce** contrato — al llegar a `done`, se crea `LOCK-COBRANZA-05` en
`_state/contracts/CONTRACT_LOCKS.md` para los 5 endpoints (incluido el de upload), consumido por
`COBRANZA-B10`.

## Definition of Done

- [ ] `composer ci` ejecutado — salida completa pegada.
- [ ] Verificación funcional real cubriendo los 13 criterios — request/response reales pegados,
      **incluido el test de concurrencia real** (#5, no un mock de lock) y la verificación de logs
      (#10).
- [ ] `LOCK-COBRANZA-05` creado en `_state/contracts/CONTRACT_LOCKS.md`.
- [ ] `api/API_CONTRACT.md` actualizado, incluyendo la convención de `Idempotency-Key` como patrón
      reusable (primera vez que se usa en el vault).
- [ ] `api/endpoints/COBRANZA.md` actualizado.
- [ ] Dado que `verificacion_critica: true`, el `verify-council` debe emitir veredicto antes de que
      este bloque pase a `done` — con foco explícito en la lente de seguridad (idempotencia, SSRF,
      logs) y la lente de arquitectura (locking, consistencia de `saldo` bajo concurrencia).

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> Bloque de mayor riesgo del feature junto con `COBRANZA-B03`. El criterio 5 (concurrencia real) no
> es opcional ni sustituible por una prueba unitaria del lock aislada — el `verify-council` debe
> confirmar que el test dispara requests HTTP concurrentes reales, no solo llama al método del
> servicio dos veces en el mismo hilo.
