---
tipo: contrato
proyecto: shared
actualizado: 2026-07-03
---

# CONTRACT_LOCKS — Contratos de API congelados

> Registro de contratos de endpoint congelados para que un bloque de cliente pueda construir contra
> ellos. Formato y reglas completas en [[../../_system/04_CROSS_PROJECT]] §4–§5. Una entrada es
> inmutable mientras tenga un "Consumido por" activo — cambiarla es un bloque nuevo, no una edición
> (ver §5 de ese documento).
>
> **Regla mecánica:** un bloque de cliente con `proyectos: [web]` que depende de un endpoint no
> puede pasar a `ready` sin una entrada aquí que lo respalde.

## Locks activos

_Vacío — ningún bloque de API ha llegado a `done` todavía. La primera entrada se crea cuando
`AUTH-B01` o `AUTH-B02` complete su Definition of Done._

## Locks reemplazados

_Vacío._
