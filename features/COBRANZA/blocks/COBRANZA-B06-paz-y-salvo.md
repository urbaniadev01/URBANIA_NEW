---
tipo: bloque
proyecto: api
feature: COBRANZA
id: COBRANZA-B06
proyectos: [api]
estado: backlog
depende_de: [COBRANZA-B05]
contrato: null
verificacion_critica: false
actualizado: 2026-07-09
---

# COBRANZA-B06 — Generación y revocación de paz y salvo

## Objetivo

Cierra la cadena API del feature: emitir el certificado de paz y salvo de una unidad (condicionado a
saldo cero, R-COB-11) y su revocación (R-COB-28), más el endpoint de solo-lectura del residente.
Último bloque de API — depende de que `COBRANZA-B05` mantenga `invoices.saldo` correcto.

## Alcance

- **Incluye:**
  - `POST /properties/{id}/peace-certificates` — genera el certificado. Valida que la unidad no tenga
    ninguna `invoice` con `saldo > 0` (R-COB-11) — si tiene, `422` con el detalle de facturas
    pendientes. **Síncrono** (decisión 9 del panorama): no responde hasta tener `pdf_url` poblado, sin
    estado intermedio ni `pdf_status`.
  - `GET /properties/{id}/peace-certificates` — listado histórico de una unidad.
  - `GET /peace-certificates/{id}` — ver/descargar un certificado puntual.
  - `DELETE /peace-certificates/{id}` — revocar (requiere `cobranza.paz_salvo.revocar`, distinto de
    `cobranza.paz_salvo.generar`, R-COB-28). Reutiliza el soft-delete existente (`deleted_at`,
    `updated_by`) — no se agrega un campo de estado nuevo.
  - `GET /me/peace-certificates` — certificados de las unidades del residente autenticado. **404
    uniforme** (nunca `403`) al pedir uno que no le pertenece (R-COB-20).
  - Throttle moderado en la generación (`throttle:60,1`, R-COB-27, misma segunda línea de defensa que
    `COBRANZA-B05`).
  - Middleware RBAC: `cobranza.paz_salvo.generar`, `cobranza.paz_salvo.revocar`, más
    `cobranza.facturas.ver` para el listado (reutilizado, sin permiso nuevo redundante — mismo
    permiso que ya gatea ver facturas, consistente con `PANORAMA.md` §5).

- **No incluye (explícitamente fuera de este bloque):**
  - Generación de PDF vía servicio externo — se asume una librería local (ej. rendering
    HTML→PDF ya disponible en `API_BOOTSTRAP`); si no existe, es un prerequisito de infraestructura a
    resolver antes de este bloque, no una extensión de su alcance.
  - Cualquier regla de vencimiento automático del certificado — `vigente_hasta` es un campo
    informativo (`NULL` = sin vencimiento definido), no hay job que lo evalúe.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Usuario con `cobranza.paz_salvo.generar`, unidad con todas sus `invoices` en `saldo = 0` | `POST /properties/{id}/peace-certificates` | `201` (síncrono), `pdf_url` poblado en la misma respuesta |
| 2 | Igual, unidad con al menos una `invoice` con `saldo > 0` | `POST /properties/{id}/peace-certificates` | `422` con el detalle de facturas pendientes que impiden la emisión |
| 3 | Usuario con `cobranza.paz_salvo.generar` (sin `.revocar`) | `DELETE /peace-certificates/{id}` | `403` — segregación (R-COB-28) |
| 4 | Usuario con `cobranza.paz_salvo.revocar` | `DELETE /peace-certificates/{id}` | `204`, `deleted_at` poblado, certificado ya no aparece en listado activo |
| 5 | Residente autenticado, certificado de su propia unidad | `GET /me/peace-certificates` | `200`, incluye el certificado |
| 6 | Residente autenticado | `GET /peace-certificates/{id}` de un certificado de **otra** unidad vía la ruta `/me/...` | `404` (nunca `403`) — R-COB-20 |
| 7 | Usuario sin `cobranza.paz_salvo.generar`, 61 requests en 1 minuto a `POST .../peace-certificates` | Ejecutar la request 61 | `429` (R-COB-27) |
| 8 | Certificado revocado | `GET /properties/{id}/peace-certificates` (listado activo) | El certificado revocado no aparece por defecto |

## Contrato

Este bloque **produce** contrato — al llegar a `done`, se crea `LOCK-COBRANZA-06` en
`_state/contracts/CONTRACT_LOCKS.md` para los 5 endpoints, consumido por `COBRANZA-B11` (y, a futuro,
por `PORTAL_RESIDENTE` para `/me/peace-certificates`).

## Definition of Done

- [ ] `composer ci` ejecutado — salida completa pegada.
- [ ] Verificación funcional real cubriendo los 8 criterios, incluida la respuesta síncrona con
      `pdf_url` poblado (#1) y el 404 uniforme de residente (#6) — request/response reales pegados.
- [ ] `LOCK-COBRANZA-06` creado en `_state/contracts/CONTRACT_LOCKS.md`.
- [ ] `api/API_CONTRACT.md` actualizado.
- [ ] `api/endpoints/COBRANZA.md` actualizado — cierra la documentación de detalle de toda la cadena
      API del feature (B02-B06).

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> Con este bloque `done` y verificado, la cadena API completa (B01-B06) queda cerrada — `COBRANZA-B07`
> a `B11` (Web) pueden empezar en cuanto cada uno tenga su lock correspondiente vigente.
