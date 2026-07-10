---
tipo: bloque
proyecto: web
feature: PROPIEDADES
id: PROPIEDADES-B09
proyectos: [web]
estado: verifying
depende_de: [PROPIEDADES-B05, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: false
actualizado: 2026-07-10
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

### `pnpm run ci` (code/web) — 2026-07-10

```
$ pnpm type-check && pnpm lint && pnpm test && pnpm build
$ tsc -b
$ eslint . --max-warnings 0
$ vitest run
...
 ✓ src/features/propiedades/__tests__/CoeficientesTab.test.tsx (8 tests)
 Test Files  14 passed (14)
      Tests  126 passed (126)
$ tsc -b && vite build
✓ 1797 modules transformed.
✓ built in 11.71s
```

Misma corrida consolidada de `pnpm ci` que `PROPIEDADES-B06/B07/B08` (un único comando en el
monorepo web) — sesión de cierre de DoD del 2026-07-10.

### Test de componente nuevo

`code/web/src/features/propiedades/__tests__/CoeficientesTab.test.tsx` (8 tests): render inicial
con "Guardar cambios" deshabilitado, editar valor actualiza la barra de suma en tiempo real y
habilita el botón, indicador ámbar (suma ≠ 1.0), indicador verde (suma = 1.0), guardar dispara el
PATCH masivo con solo las filas modificadas + toast de éxito, error 422 no pierde los cambios en la
UI.

### Confirmación de contrato

`LOCK-PROPIEDADES-04` (`_state/contracts/CONTRACT_LOCKS.md`) sigue vigente, congelado 2026-07-08,
producido por `PROPIEDADES-B05` (`done`); también se confirmó `LOCK-PROPIEDADES-03` para la lista de
unidades (vigente). Los endpoints en `code/web/src/features/propiedades/api/coefficients.ts`
coinciden exactamente con las rutas del lock.

### Verificación visual (Playwright) — bloqueada, no completada

Se escribió un spec real (sin mocks, login contra el backend real en Docker) en
`code/web/e2e/propiedades/propiedades.spec.ts` cubriendo CA1 de este bloque (tab Coeficientes
renderiza y botón "Guardar cambios" deshabilitado sin cambios). **No se pudo ejecutar**:
`@playwright/test` está roto en este entorno — probado exhaustivamente en 1.49.0 (versión exacta
committeada), 1.60.0 y 1.61.1, y en Node v22 y v25, incluso con un spec trivial de una línea. Falla
también en el spec preexistente de `AUTH-B06`. Ver `_state/RUNBOOK.md#E-005` para el diagnóstico
completo. El spec queda listo para correr en cuanto se resuelva ese bloqueo.

### Verificación de contrato API real — sustituto de Playwright (2026-07-10)

`code/web/scripts/verify-propiedades-contract.mjs` (login real, sin mocks) ejercita
`LOCK-PROPIEDADES-04` completo: `PATCH` masivo de coeficientes (`data[]` con `vigente_hasta: null`
para el vigente actual), warning no bloqueante `COEFFICIENT_SUM_MISMATCH` cuando la suma ≠ 1.0 (R-06),
error 422 `COEFFICIENT_OUT_OF_RANGE` en valor fuera de rango — código que `CoeficientesTab` espera
en su manejo de error —, superseder un coeficiente y confirmar que el anterior queda con
`vigente_hasta != null` (R-05), y `GET /condominiums/{id}/tree` con el shape completo
(`tree.towers[]`, `tree.untowered_properties_count`). **Resultado: 51/51 checks pasando** (corrida
consolidada con `PROPIEDADES-B06/B07/B08`) — sin discrepancias de contrato en este bloque. No
sustituye la verificación visual (barra de suma en tiempo real, toggle de historial) pero cubre el
riesgo de contrato real API↔Web.

## Notas

> La barra de suma en tiempo real es el diferenciador UX de esta pantalla. Debe recalcularse en cada
> keystroke sin perder rendimiento — usar `useMemo` o `derived` state, no re-renderizar toda la
> tabla. Si el condominio tiene 200+ unidades, considerar virtualización de la tabla.

> **Auditoría 2026-07-09:** revertido de `done` a `in_progress` — la sección Evidencia no cumple
> `_system/05_DEFINITION_OF_DONE.md` (evidencia vacía). Requiere correr `pnpm ci` real y
> verificación visual Playwright de los criterios de aceptación antes de volver a `verifying`.
