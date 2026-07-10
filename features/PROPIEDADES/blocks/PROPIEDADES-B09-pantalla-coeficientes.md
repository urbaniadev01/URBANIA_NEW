---
tipo: bloque
proyecto: web
feature: PROPIEDADES
id: PROPIEDADES-B09
proyectos: [web]
estado: in_progress
depende_de: [PROPIEDADES-B05, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: false
actualizado: 2026-07-09
---

# PROPIEDADES-B09 — Pantalla de coeficientes (tabla editable en lote con suma en tiempo real)

## Objetivo

Construir el tab "Coeficientes" dentro de `DetalleCondominio`: una tabla editable en lote donde el
admin asigna coeficientes a cada unidad del condominio, con barra de suma en tiempo real y
validación visual. Integra con `LOCK-PROPIEDADES-04`.

## Alcance

- **Incluye:**
  - Tab "Coeficientes" en `DetalleCondominio` (`/condominios/{id}?tab=coeficientes`): tabla con
    columnas (unidad — código, tipo de coeficiente, valor editable, vigencia).
  - Fila por cada unidad del condominio × tipo de coeficiente. Carga inicial desde `GET
    /properties/{id}/coefficients` para cada unidad (o desde `GET /condominiums/{id}/tree` si
    incluye coeficientes).
  - Campo `valor` editable inline (input numérico con step 0.0001, rango 0–1).
  - Barra de suma en tiempo real: muestra la suma actual de todos los coeficientes de tipo
    "copropiedad". Se actualiza en cada cambio de input.
  - Indicador visual:
    - Verde si suma = 1.0 (100%).
    - Ámbar/amarillo con warning si suma ≠ 1.0 — no bloquea el guardado (R-06).
  - Botón "Guardar cambios" que dispara `PATCH /condominiums/{id}/coefficients` con el payload
    masivo atómico (solo las filas modificadas).
  - Selector de tipo de coeficiente para filtrar la tabla (ej. ver solo "copropiedad" o
    "parqueadero").
  - Indicador de coeficientes históricos: toggle "Ver historial" que muestra columnas adicionales
    (`vigente_desde`, `vigente_hasta`) y marca el coeficiente actualmente vigente.
  - Manejo de errores del API con toast (422, 403).
  - Documentación de pantalla en `web/features/propiedades/PROPIEDADES-coeficientes.md`.

- **No incluye (explícitamente fuera de este bloque):**
  - Pantalla de unidades (B08), condominios (B07), catálogos (B06).
  - Cálculo de cuotas de administración (COBRANZA).
  - Gráficos o visualizaciones avanzadas de distribución de coeficientes.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Admin en DetalleCondominio, tab "Coeficientes" | Click en tab | Tabla con filas unidad×tipo, barra de suma visible, botón "Guardar cambios" deshabilitado (sin cambios) |
| 2 | Tabla cargada | Editar un valor de coeficiente | Barra de suma se actualiza en tiempo real, botón "Guardar cambios" se habilita |
| 3 | Suma de copropiedad = 0.85 | Ver barra de suma | Indicador ámbar: "Suma actual: 85% — se requiere 100%" |
| 4 | Suma de copropiedad = 1.0 | Ver barra de suma | Indicador verde: "Suma: 100% ✓" |
| 5 | Cambios pendientes | Click en "Guardar cambios" | PATCH masivo enviado, toast de éxito, botón se deshabilita |
| 6 | API devuelve 422 (valor fuera de rango) | Click en "Guardar cambios" | Toast de error con el mensaje del API, cambios no se pierden en la UI |
| 7 | API devuelve error parcial (rollback atómico) | Click en "Guardar cambios" | Toast de error explicando que ningún cambio se aplicó, datos intactos |
| 8 | Cambios guardados exitosamente | Ver columna de vigencia | Coeficiente anterior marcado con `vigente_hasta`, nuevo coeficiente vigente |
| 9 | Tabla con coeficientes históricos | Activar toggle "Ver historial" | Columnas `vigente_desde` y `vigente_hasta` visibles, coeficiente vigente resaltado |
| 10 | Selector de tipo en "parqueadero" | Seleccionar tipo | Tabla filtrada a coeficientes de parqueadero, barra de suma se actualiza |
| 11 | Usuario con rol residente | Navegar al tab "Coeficientes" | No visible o redirigido — solo admin accede |
| 12 | API no disponible | Click en "Guardar cambios" | Toast de error, datos en UI no se pierden |

## Contrato

Este bloque **consume** el contrato `LOCK-PROPIEDADES-04` (producido por `PROPIEDADES-B05`), y
también depende de `LOCK-PROPIEDADES-03` (para obtener la lista de unidades del condominio). No
puede pasar a `ready` sin que ambos locks estén vigentes.

## Definition of Done

- [ ] `pnpm ci` ejecutado — salida completa pegada.
- [ ] Verificación visual real (Playwright) recorriendo: editar coeficientes inline, verificar barra
      de suma en tiempo real (verde 100%, ámbar ≠ 100%), guardar cambios masivos, toggle historial,
      selector de tipo, error del API con rollback.
- [ ] Confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que la integración respeta exactamente
      `LOCK-PROPIEDADES-04` (y `LOCK-PROPIEDADES-03` para unidades).
- [ ] `web/features/propiedades/PROPIEDADES-coeficientes.md` creado desde la plantilla
      `_system/templates/WEB_SCREEN.md`.
- [ ] Componentes usados provienen de la librería base instalada en `WEB_BOOTSTRAP-B01`.
- [ ] `web/WEB_API_CLIENT.md` actualizado con los hooks/clientes nuevos hacia los endpoints de
      coeficientes y tree.

## Evidencia

> Vacío hasta que el bloque se ejecute.

## Notas

> La barra de suma en tiempo real es el diferenciador UX de esta pantalla. Debe recalcularse en cada
> keystroke sin perder rendimiento — usar `useMemo` o `derived` state, no re-renderizar toda la
> tabla. Si el condominio tiene 200+ unidades, considerar virtualización de la tabla.

> **Auditoría 2026-07-09:** revertido de `done` a `in_progress` — la sección Evidencia no cumple
> `_system/05_DEFINITION_OF_DONE.md` (evidencia vacía). Requiere correr `pnpm ci` real y
> verificación visual Playwright de los criterios de aceptación antes de volver a `verifying`.
