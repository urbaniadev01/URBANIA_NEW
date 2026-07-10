---
tipo: bloque
proyecto: api
feature: COBRANZA
id: COBRANZA-B02
proyectos: [api]
estado: backlog
depende_de: [COBRANZA-B01]
contrato: null
verificacion_critica: false
actualizado: 2026-07-09
---

# COBRANZA-B02 — CRUD de conceptos de cobro

## Objetivo

Exponer el CRUD completo de `charge_concepts` (crear, listar, ver, editar, desactivar), primer
endpoint del feature y base sobre la que corre la facturación (`COBRANZA-B03`). Establece el patrón
de autorización (`cobranza.conceptos.ver`/`cobranza.conceptos.gestionar`) y el `warnings[]` de
R-COB-18 que bloques posteriores reutilizan.

## Alcance

- **Incluye:**
  - `GET /condominiums/{id}/charge-concepts` — listado, scope `condominium_id` (R-COB-01).
  - `GET /charge-concepts/{id}` — detalle.
  - `POST /condominiums/{id}/charge-concepts` — creación. Valida `UNIQUE(condominium_id, nombre)
    WHERE deleted_at IS NULL`, `tipo`/`metodo_calculo` dentro del set cerrado (defensa de aplicación,
    la BD ya lo garantiza vía CHECK de `COBRANZA-B01`). Si `tipo = fondo_imprevistos`, la respuesta
    incluye `warnings: [{code: "FONDO_IMPREVISTOS_VALIDACION_PENDIENTE", ...}]` (R-COB-18).
  - `PATCH /charge-concepts/{id}` — edición.
  - `DELETE /charge-concepts/{id}` — desactivación (soft delete; `activo = false` se setea junto con
    el borrado para que quede claro en queries sin `withTrashed`).
  - Middleware RBAC con los permisos `cobranza.conceptos.ver`/`cobranza.conceptos.gestionar` — primer
    uso real de los 11 permisos seedeados en `COBRANZA-B01`. Asignar `cobranza.conceptos.*` al rol de
    sistema "Administrador de conjunto" (mismo criterio de asignación que `PROPIEDADES-B02` usó para
    sus permisos de catálogo).
  - `api/endpoints/COBRANZA.md` — creación del documento de detalle request/response (no existe
    todavía; ver `api/endpoints/PROPIEDADES.md` como formato de referencia).

- **No incluye (explícitamente fuera de este bloque):**
  - `billing_periods`, `billing_runs` — `COBRANZA-B03`.
  - Validación real del mínimo legal de 1% para `fondo_imprevistos` — diferida (R-COB-18), este
    bloque solo emite el warning.
  - R-COB-29 (advertencia de conceptos `extraordinaria` duplicados) — es responsabilidad de la
    pantalla Web (`COBRANZA-B07`), no de este endpoint: el API no impone unicidad, solo la UI muestra
    los existentes al crear.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Usuario con `cobranza.conceptos.ver`, condominio con conceptos | `GET /condominiums/{id}/charge-concepts` | `200`, lista scopeada al condominio |
| 2 | Usuario con `cobranza.conceptos.gestionar` | `POST .../charge-concepts` con `tipo=administracion`, `metodo_calculo=coeficiente` | `201`, concepto creado |
| 3 | Igual que #2 pero `tipo=fondo_imprevistos` | `POST .../charge-concepts` | `201` con `warnings: [{code: "FONDO_IMPREVISTOS_VALIDACION_PENDIENTE"}]` en el body |
| 4 | Usuario con `cobranza.conceptos.gestionar` | `POST .../charge-concepts` con `nombre` duplicado en el mismo condominio | `422` con error de unicidad |
| 5 | Usuario con `cobranza.conceptos.gestionar` | `POST .../charge-concepts` con `tipo=interes` (fuera del set cerrado) | `422` — validación de aplicación, nunca llega a la BD |
| 6 | Usuario **sin** `cobranza.conceptos.ver` | `GET /condominiums/{id}/charge-concepts` | `403` |
| 7 | Usuario con `cobranza.conceptos.ver` (sin `.gestionar`) | `POST .../charge-concepts` | `403` — segregación ver/gestionar |
| 8 | Usuario de otro condominio con `cobranza.conceptos.ver` | `GET /condominiums/{otro-id}/charge-concepts` | `403` — R-COB-02, scope de staff no cubre ese condominio |
| 9 | Usuario con `cobranza.conceptos.gestionar` | `DELETE /charge-concepts/{id}` | `204`, `deleted_at` poblado, `activo = false` |
| 10 | Concepto desactivado | `GET /condominiums/{id}/charge-concepts` (sin `?incluir_inactivos`) | El concepto desactivado no aparece en el listado por defecto |

## Contrato

Este bloque **produce** contrato — al llegar a `done`, se crea `LOCK-COBRANZA-02` en
`_state/contracts/CONTRACT_LOCKS.md` para los 5 endpoints de conceptos de cobro, consumido por
`COBRANZA-B07`.

## Definition of Done

- [ ] `composer ci` ejecutado — salida completa pegada.
- [ ] Verificación funcional real (curl/httpie) cubriendo los 10 criterios de aceptación, incluidos
      los negativos (#4-8) — request/response reales pegados.
- [ ] `LOCK-COBRANZA-02` creado en `_state/contracts/CONTRACT_LOCKS.md`.
- [ ] `api/API_CONTRACT.md` actualizado con los 5 endpoints nuevos.
- [ ] `api/endpoints/COBRANZA.md` creado con el detalle request/response de estos 5 endpoints.

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> Este bloque decide el formato exacto de `warnings[]` en el body de respuesta (R-COB-18) — los
> bloques `COBRANZA-B03` (R-COB-08-bis) y `COBRANZA-B05`/`B06` reutilizan el mismo mecanismo, no
> inventan uno nuevo. Documentar el formato en `api/API_CONTRACT.md` como convención general, mismo
> criterio que R-COB-22 pide para el patrón 202+polling.
