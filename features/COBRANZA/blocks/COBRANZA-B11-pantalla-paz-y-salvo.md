---
tipo: bloque
proyecto: web
feature: COBRANZA
id: COBRANZA-B11
proyectos: [web]
estado: backlog
depende_de: [COBRANZA-B06, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: true
actualizado: 2026-07-09
---

# COBRANZA-B11 — Pantalla de paz y salvo

## Objetivo

Construir la generación y revocación de certificados de paz y salvo — último bloque del feature
`COBRANZA`. Cierra el feature completo (API + Web), por lo que `verificacion_critica: true` (criterio
"features nuevos completos, su último bloque" de `_system/05_DEFINITION_OF_DONE.md` §6). Integra con
`LOCK-COBRANZA-06`.

## Alcance

- **Incluye:**
  - Página `PazYSalvo` (`/cobranza/paz-y-salvo` o accesible desde el detalle de unidad): listado de
    certificados emitidos por unidad, botón "Generar paz y salvo".
  - Flujo de generación: dado que es **síncrono** (decisión 9 del panorama), la UI muestra un estado
    de carga acotado (no un banner persistente como `COBRANZA-B08` — aquí sí es apropiado un spinner
    de corta duración porque la operación es de bajo volumen y responde con `pdf_url` de una vez).
  - Si la unidad tiene `saldo > 0` en alguna factura, la UI **deshabilita** el botón "Generar" con un
    tooltip/mensaje mostrando qué facturas bloquean la emisión — no espera a que el usuario dispare el
    `422` del API para enterarse.
  - Botón "Revocar" (visible solo con `cobranza.paz_salvo.revocar`) con diálogo de confirmación,
    dado que es un artefacto legal (mensaje explícito de "Esta acción revoca un documento legal
    emitido").
  - Visor/descarga del PDF (`pdf_url`).
  - Integración con `LOCK-COBRANZA-06` (5 endpoints).
  - Documentación de pantalla en `web/features/cobranza/COBRANZA-paz-y-salvo.md`.

- **No incluye (explícitamente fuera de este bloque):**
  - Vista `/me/peace-certificates` del residente — pertenece a `PORTAL_RESIDENTE` (Fase 1.6), no a
    esta pantalla de administración de staff.
  - Regeneración/edición de un certificado ya emitido — no existe esa operación, se revoca y se
    genera uno nuevo.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Admin con `cobranza.paz_salvo.generar`, unidad con `saldo = 0` en todas sus facturas | Click "Generar paz y salvo" | Estado de carga breve, luego certificado con `pdf_url` visible/descargable |
| 2 | Unidad con al menos una factura `saldo > 0` | Ver el botón "Generar" | Deshabilitado, con detalle de qué facturas bloquean (sin necesidad de intentar el submit) |
| 3 | Admin con `cobranza.paz_salvo.generar` (sin `.revocar`) | Ver un certificado emitido | Sin botón "Revocar" |
| 4 | Admin con `cobranza.paz_salvo.revocar` | Click "Revocar" → confirmar | DELETE exitoso, certificado desaparece del listado activo, mensaje de confirmación explícito de que es una acción legal |
| 5 | Certificado emitido | Click en descargar/ver | PDF se abre/descarga desde `pdf_url` |
| 6 | Admin sin `cobranza.facturas.ver` ni `cobranza.paz_salvo.*` | Buscar acceso a la pantalla | Sin acceso — ruta protegida por permiso |
| 7 | API responde `429` (throttle) al generar | Click "Generar" | Mensaje claro de límite alcanzado |

## Contrato

Este bloque **consume** el contrato `LOCK-COBRANZA-06` (producido por `COBRANZA-B06`). No puede pasar
a `ready` sin ese lock vigente.

## Definition of Done

- [ ] `pnpm ci` ejecutado — salida completa pegada.
- [ ] Verificación visual real (Playwright MCP o equivalente) recorriendo los 7 criterios.
- [ ] Confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que la integración respeta exactamente
      `LOCK-COBRANZA-06`.
- [ ] `web/features/cobranza/COBRANZA-paz-y-salvo.md` creado.
- [ ] Componentes de la librería base de `WEB_BOOTSTRAP-B01`.
- [ ] `web/WEB_API_CLIENT.md` actualizado.
- [ ] `_state/CHANGELOG.md` — entrada de cierre agregada cuando este bloque llega a `done` (regla
      cross-project de `_system/05_DEFINITION_OF_DONE.md` §4, ya que este es el último bloque de la
      última cadena cross-project del feature).
- [ ] Dado que `verificacion_critica: true`, el `verify-council` debe emitir veredicto antes de que
      este bloque pase a `done` — confirmando además, como parte de cerrar el feature completo, que
      la cadena de 11 bloques (`COBRANZA-B01` a `B11`) cumple integralmente `PANORAMA.md` §1-§6 sin
      huecos.

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> Al llegar este bloque a `done`, `COBRANZA` queda `SHIPPED` (mismo criterio que `AUTH`) — actualizar
> `_state/BOARD.md` §Features de `approved` a `SHIPPED`. La acción pendiente cross-feature con
> `DASHBOARD` (ver `BLOCKS.md`) sigue abierta de forma independiente — no bloquea el `SHIPPED` de
> `COBRANZA`, es responsabilidad del bloque de Web de `DASHBOARD` que construya ese widget.
