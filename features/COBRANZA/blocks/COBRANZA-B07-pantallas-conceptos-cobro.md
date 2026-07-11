---
tipo: bloque
proyecto: web
feature: COBRANZA
id: COBRANZA-B07
proyectos: [web]
estado: ready
depende_de: [COBRANZA-B02, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: false
actualizado: 2026-07-09
---

# COBRANZA-B07 — Pantalla de conceptos de cobro

## Objetivo

Construir la pantalla de administración de `charge_concepts`: tabla con CRUD completo, primer punto
de entrada del módulo Cobranza en la Web. Integra con `LOCK-COBRANZA-02`.

## Alcance

- **Incluye:**
  - Página `ConceptosCobro` (`/cobranza/conceptos`): tabla con columnas (nombre, tipo, método de
    cálculo, valor base, activo/inactivo), botón "Nuevo concepto", acciones de editar/desactivar por
    fila.
  - Sheet/Dialog de crear/editar con campos `nombre`, `tipo` (select del set cerrado), `metodo_calculo`
    (select del set cerrado), `valor_base`.
  - Al seleccionar `tipo = fondo_imprevistos`, mostrar el `warnings[]` que devuelve el API
    (`FONDO_IMPREVISTOS_VALIDACION_PENDIENTE`) como aviso visible no bloqueante en el formulario
    (banner o texto de ayuda, no un toast que desaparece) — el usuario debe poder verlo sin perderlo,
    consistente con R-COB-18.
  - Al seleccionar `tipo = extraordinaria`, mostrar los conceptos del mismo tipo ya activos en el
    condominio como lista de referencia dentro del formulario, para que el usuario confirme a ojo que
    no está duplicando por error (R-COB-29 — control de UI, el API no impone unicidad).
  - Diálogo de confirmación antes de desactivar.
  - Integración con `LOCK-COBRANZA-02` (5 endpoints).
  - Validación Zod en formularios.
  - Manejo de errores del API con toast (422, 403).
  - Documentación de pantalla en `web/features/cobranza/COBRANZA-conceptos-cobro.md`.
  - Punto de entrada de navegación al módulo Cobranza (nav item o sección), gateado por `billing.ver`
    (permiso ya existente, reutilizado — no se crea uno nuevo).

- **No incluye (explícitamente fuera de este bloque):**
  - Pantallas de periodos/facturación (B08), facturas (B09), pagos (B10), paz y salvo (B11).
  - Validación real del mínimo legal de 1% para fondo de imprevistos — el warning es solo
    informativo, no hay cálculo real detrás en Fase 1.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Admin con `cobranza.conceptos.ver`, condominio con conceptos | Navegar a `/cobranza/conceptos` | Tabla con conceptos del condominio |
| 2 | Admin con `cobranza.conceptos.gestionar` | Click "Nuevo concepto", completar con `tipo=administracion` | POST exitoso, aparece en tabla, toast de éxito |
| 3 | Formulario abierto | Seleccionar `tipo=fondo_imprevistos` y guardar | Concepto creado, banner visible con el aviso de validación pendiente (no un toast efímero) |
| 4 | Formulario abierto | Seleccionar `tipo=extraordinaria` con conceptos del mismo tipo ya activos | Lista de conceptos existentes de ese tipo visible en el formulario antes de guardar |
| 5 | Formulario abierto, `nombre` vacío | Click "Guardar" | Error de validación Zod, no se hace POST |
| 6 | Nombre duplicado en el condominio | Click "Guardar" | Toast de error 422 con mensaje claro de duplicado |
| 7 | Admin con `cobranza.conceptos.ver` (sin `.gestionar`) | Navegar a `/cobranza/conceptos` | Tabla visible, sin botones "Nuevo"/"Editar"/"Desactivar" |
| 8 | Usuario sin `billing.ver` | Buscar el nav item de Cobranza | No aparece en la navegación |
| 9 | Concepto activo | Click "Desactivar" → confirmar | DELETE exitoso, concepto desaparece del listado activo, toast de éxito |
| 10 | API no disponible | Cualquier acción | Toast de error genérico, datos no se pierden |

## Contrato

Este bloque **consume** el contrato `LOCK-COBRANZA-02` (producido por `COBRANZA-B02`). No puede pasar
a `ready` sin ese lock vigente.

## Definition of Done

- [ ] `pnpm ci` ejecutado — salida completa pegada.
- [ ] Verificación visual real (Playwright MCP o equivalente) recorriendo los 10 criterios.
- [ ] Confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que la integración respeta exactamente
      `LOCK-COBRANZA-02`.
- [ ] `web/features/cobranza/COBRANZA-conceptos-cobro.md` creado desde
      `_system/templates/WEB_SCREEN.md`.
- [ ] Componentes usados provienen de la librería base instalada en `WEB_BOOTSTRAP-B01`.
- [ ] `web/WEB_API_CLIENT.md` actualizado con los hooks/clientes nuevos.

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> Este bloque crea el punto de entrada de navegación al módulo Cobranza completo — los bloques
> `COBRANZA-B08` a `B11` agregan sub-rutas bajo la misma sección, no nav items nuevos independientes.
