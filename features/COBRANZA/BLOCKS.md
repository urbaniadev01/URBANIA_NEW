---
tipo: feature
proyecto: shared
feature: COBRANZA
actualizado: 2026-07-09
---

# COBRANZA — Plan de bloques

> Orden de ejecución, dependencias y gates. Este documento es el índice de bloques del feature — el
> estado real de cada uno vive en su propia tarjeta (ver [[../../_system/01_PRINCIPLES#1. Un dato, un dueño]]);
> `_state/BOARD.md` es el rollup global que agrega esto junto con el resto del vault.

## Orden

```
PROPIEDADES-B01 (done ✅) ──┐
DIRECTORIO-B01 (ready)    ──┼──> COBRANZA-B01 (api, fundacional — 8 tablas + permisos RBAC)
                             │
                             └──> COBRANZA-B02 (api, conceptos de cobro)
                                       └──> COBRANZA-B03 (api, periodos + facturación, async)
                                                 └──> COBRANZA-B04 (api, cuentas de cobro + /me/invoices)
                                                           └──> COBRANZA-B05 (api, pagos + upload soporte)
                                                                     └──> COBRANZA-B06 (api, paz y salvo + /me/peace-certificates)

WEB_BOOTSTRAP-B01 (done ✅) ──┐
COBRANZA-B02 ──lock──> COBRANZA-B07 (web, pantallas conceptos de cobro)
COBRANZA-B03 ──lock──> COBRANZA-B08 (web, pantallas periodos y facturación)
COBRANZA-B04 ──lock──> COBRANZA-B09 (web, pantalla cuentas de cobro)
COBRANZA-B05 ──lock──> COBRANZA-B10 (web, pantalla registro de pagos)
COBRANZA-B06 ──lock──> COBRANZA-B11 (web, pantalla paz y salvo)
```

La cadena API es estrictamente secuencial (B01→B06) porque cada dominio depende financieramente del
anterior: sin conceptos no hay facturación, sin facturación no hay facturas, sin facturas no hay
pagos, sin pagos (saldo cero) no hay paz y salvo. No se paraleliza dentro de API — a diferencia de
`PROPIEDADES`, donde los 4 CRUDs de catálogo eran independientes entre sí.

## Tabla

| ID | Proyecto | Depende de | Estado | Tarjeta |
|---|---|---|---|---|
| COBRANZA-B01 | api | PROPIEDADES-B01, DIRECTORIO-B01 | backlog | [[blocks/COBRANZA-B01-migraciones-modelos-seeders]] |
| COBRANZA-B02 | api | COBRANZA-B01 | backlog | [[blocks/COBRANZA-B02-crud-conceptos-cobro]] |
| COBRANZA-B03 | api | COBRANZA-B02 | backlog | [[blocks/COBRANZA-B03-periodos-facturacion]] |
| COBRANZA-B04 | api | COBRANZA-B03 | backlog | [[blocks/COBRANZA-B04-cuentas-cobro]] |
| COBRANZA-B05 | api | COBRANZA-B04 | backlog | [[blocks/COBRANZA-B05-pagos]] |
| COBRANZA-B06 | api | COBRANZA-B05 | backlog | [[blocks/COBRANZA-B06-paz-y-salvo]] |
| COBRANZA-B07 | web | COBRANZA-B02 (lock), WEB_BOOTSTRAP-B01 | backlog | [[blocks/COBRANZA-B07-pantallas-conceptos-cobro]] |
| COBRANZA-B08 | web | COBRANZA-B03 (lock), WEB_BOOTSTRAP-B01 | backlog | [[blocks/COBRANZA-B08-pantallas-periodos-facturacion]] |
| COBRANZA-B09 | web | COBRANZA-B04 (lock), WEB_BOOTSTRAP-B01 | backlog | [[blocks/COBRANZA-B09-pantalla-cuentas-cobro]] |
| COBRANZA-B10 | web | COBRANZA-B05 (lock), WEB_BOOTSTRAP-B01 | backlog | [[blocks/COBRANZA-B10-pantalla-pagos]] |
| COBRANZA-B11 | web | COBRANZA-B06 (lock), WEB_BOOTSTRAP-B01 | backlog | [[blocks/COBRANZA-B11-pantalla-paz-y-salvo]] |

> Ningún bloque arranca en `ready` todavía: `COBRANZA-B01` depende de `DIRECTORIO-B01`, que hoy está
> `ready` pero no `done`. En cuanto `DIRECTORIO-B01` llegue a `done`, `COBRANZA-B01` pasa a `ready`
> (mecánico — actualizar su fila aquí y en `_state/BOARD.md`).
>
> Los bloques API (B02-B06) **producen** contrato — al llegar a `done`, se crea un lock en
> `_state/contracts/CONTRACT_LOCKS.md`. Los bloques Web (B07-B11) **consumen** contrato — no pueden
> pasar a `ready` sin el lock vigente de su bloque API correspondiente.

## Prerrequisito de diseño no mecánico

Antes de que `COBRANZA-B01` pueda ejecutarse, debe existir `[[../../shared/adr/ADR-002-lectura-cross-context-modulo-monolito]]`
(ya creado, `Aceptada`) — el Design Council dejó la lectura de `Billing` sobre `Properties` como
postura tentativa, no bloqueante para aprobar el panorama, pero sí para el bloque fundacional. Ya
resuelto: lectura read-only de modelos Eloquent de `Properties` desde la capa de aplicación de
`Billing`, mismo scope de `condominium_id` de la request.

## Acción pendiente cross-feature (no bloquea esta cadena)

`COBRANZA-B03` produce `GET /condominiums/{id}/billing-periods/active/summary`. El widget "Cuotas
Pendientes" de `[[../DASHBOARD/PANORAMA]]` referencia hoy `GET /billing-periods/active/summary` (sin
condominio) — gap de contrato documentado en `[[../COBRANZA/PANORAMA#3. Relación con otras features]]`
y `#9.4`. Cuando el bloque de Web de `DASHBOARD` que construye ese widget se tome, debe ajustar su
referencia al endpoint real (o se agrega un alias sin condominio — decisión pendiente del humano, no
de este documento).
