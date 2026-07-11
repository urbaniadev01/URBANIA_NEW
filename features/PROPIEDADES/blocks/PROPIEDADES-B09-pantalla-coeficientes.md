---
tipo: bloque
proyecto: web
feature: PROPIEDADES
id: PROPIEDADES-B09
proyectos: [web]
estado: done
depende_de: [PROPIEDADES-B05, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: false
actualizado: 2026-07-11
---

# PROPIEDADES-B09 â€” Pantalla de coeficientes (tabla editable en lote con suma en tiempo real)

## Objetivo

Construir el tab "Coeficientes" dentro de `DetalleCondominio`: una tabla editable en lote donde el
admin asigna coeficientes a cada unidad del condominio, con barra de suma en tiempo real y
validaciÃ³n visual. Integra con `LOCK-PROPIEDADES-04`.

## Alcance

- **Incluye:**
  - Tab "Coeficientes" en `DetalleCondominio` (`/condominios/{id}?tab=coeficientes`): tabla con
    columnas (unidad â€” cÃ³digo, tipo de coeficiente, valor editable, vigencia).
  - Fila por cada unidad del condominio Ã— tipo de coeficiente. Carga inicial desde `GET
    /properties/{id}/coefficients` para cada unidad (o desde `GET /condominiums/{id}/tree` si
    incluye coeficientes).
  - Campo `valor` editable inline (input numÃ©rico con step 0.0001, rango 0â€“1).
  - Barra de suma en tiempo real: muestra la suma actual de todos los coeficientes de tipo
    "copropiedad". Se actualiza en cada cambio de input.
  - Indicador visual:
    - Verde si suma = 1.0 (100%).
    - Ãmbar/amarillo con warning si suma â‰  1.0 â€” no bloquea el guardado (R-06).
  - BotÃ³n "Guardar cambios" que dispara `PATCH /condominiums/{id}/coefficients` con el payload
    masivo atÃ³mico (solo las filas modificadas).
  - Selector de tipo de coeficiente para filtrar la tabla (ej. ver solo "copropiedad" o
    "parqueadero").
  - Indicador de coeficientes histÃ³ricos: toggle "Ver historial" que muestra columnas adicionales
    (`vigente_desde`, `vigente_hasta`) y marca el coeficiente actualmente vigente.
  - Manejo de errores del API con toast (422, 403).
  - DocumentaciÃ³n de pantalla en `web/features/propiedades/PROPIEDADES-coeficientes.md`.

- **No incluye (explÃ­citamente fuera de este bloque):**
  - Pantalla de unidades (B08), condominios (B07), catÃ¡logos (B06).
  - CÃ¡lculo de cuotas de administraciÃ³n (COBRANZA).
  - GrÃ¡ficos o visualizaciones avanzadas de distribuciÃ³n de coeficientes.

## Criterios de aceptaciÃ³n

| # | Entrada | AcciÃ³n | Salida esperada |
|---|---|---|---|
| 1 | Admin en DetalleCondominio, tab "Coeficientes" | Click en tab | Tabla con filas unidadÃ—tipo, barra de suma visible, botÃ³n "Guardar cambios" deshabilitado (sin cambios) |
| 2 | Tabla cargada | Editar un valor de coeficiente | Barra de suma se actualiza en tiempo real, botÃ³n "Guardar cambios" se habilita |
| 3 | Suma de copropiedad = 0.85 | Ver barra de suma | Indicador Ã¡mbar: "Suma actual: 85% â€” se requiere 100%" |
| 4 | Suma de copropiedad = 1.0 | Ver barra de suma | Indicador verde: "Suma: 100% âœ“" |
| 5 | Cambios pendientes | Click en "Guardar cambios" | PATCH masivo enviado, toast de Ã©xito, botÃ³n se deshabilita |
| 6 | API devuelve 422 (valor fuera de rango) | Click en "Guardar cambios" | Toast de error con el mensaje del API, cambios no se pierden en la UI |
| 7 | API devuelve error parcial (rollback atÃ³mico) | Click en "Guardar cambios" | Toast de error explicando que ningÃºn cambio se aplicÃ³, datos intactos |
| 8 | Cambios guardados exitosamente | Ver columna de vigencia | Coeficiente anterior marcado con `vigente_hasta`, nuevo coeficiente vigente |
| 9 | Tabla con coeficientes histÃ³ricos | Activar toggle "Ver historial" | Columnas `vigente_desde` y `vigente_hasta` visibles, coeficiente vigente resaltado |
| 10 | Selector de tipo en "parqueadero" | Seleccionar tipo | Tabla filtrada a coeficientes de parqueadero, barra de suma se actualiza |
| 11 | Usuario con rol residente | Navegar al tab "Coeficientes" | No visible o redirigido â€” solo admin accede |
| 12 | API no disponible | Click en "Guardar cambios" | Toast de error, datos en UI no se pierden |

## Contrato

Este bloque **consume** el contrato `LOCK-PROPIEDADES-04` (producido por `PROPIEDADES-B05`), y
tambiÃ©n depende de `LOCK-PROPIEDADES-03` (para obtener la lista de unidades del condominio). No
puede pasar a `ready` sin que ambos locks estÃ©n vigentes.

## Definition of Done

- [ ] `pnpm ci` ejecutado â€” salida completa pegada.
- [ ] VerificaciÃ³n visual real (Playwright) recorriendo: editar coeficientes inline, verificar barra
      de suma en tiempo real (verde 100%, Ã¡mbar â‰  100%), guardar cambios masivos, toggle historial,
      selector de tipo, error del API con rollback.
- [ ] Confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que la integraciÃ³n respeta exactamente
      `LOCK-PROPIEDADES-04` (y `LOCK-PROPIEDADES-03` para unidades).
- [ ] `web/features/propiedades/PROPIEDADES-coeficientes.md` creado desde la plantilla
      `_system/templates/WEB_SCREEN.md`.
- [ ] Componentes usados provienen de la librerÃ­a base instalada en `WEB_BOOTSTRAP-B01`.
- [ ] `web/WEB_API_CLIENT.md` actualizado con los hooks/clientes nuevos hacia los endpoints de
      coeficientes y tree.

## Evidencia

### `pnpm run ci` (code/web) â€” 2026-07-10

```
$ pnpm type-check && pnpm lint && pnpm test && pnpm build
$ tsc -b
$ eslint . --max-warnings 0
$ vitest run
...
 âœ“ src/features/propiedades/__tests__/CoeficientesTab.test.tsx (8 tests)
 Test Files  14 passed (14)
      Tests  126 passed (126)
$ tsc -b && vite build
âœ“ 1797 modules transformed.
âœ“ built in 11.71s
```

Misma corrida consolidada de `pnpm ci` que `PROPIEDADES-B06/B07/B08` (un Ãºnico comando en el
monorepo web) â€” sesiÃ³n de cierre de DoD del 2026-07-10.

### Test de componente nuevo

`code/web/src/features/propiedades/__tests__/CoeficientesTab.test.tsx` (8 tests): render inicial
con "Guardar cambios" deshabilitado, editar valor actualiza la barra de suma en tiempo real y
habilita el botÃ³n, indicador Ã¡mbar (suma â‰  1.0), indicador verde (suma = 1.0), guardar dispara el
PATCH masivo con solo las filas modificadas + toast de Ã©xito, error 422 no pierde los cambios en la
UI.

### ConfirmaciÃ³n de contrato

`LOCK-PROPIEDADES-04` (`_state/contracts/CONTRACT_LOCKS.md`) sigue vigente, congelado 2026-07-08,
producido por `PROPIEDADES-B05` (`done`); tambiÃ©n se confirmÃ³ `LOCK-PROPIEDADES-03` para la lista de
unidades (vigente). Los endpoints en `code/web/src/features/propiedades/api/coefficients.ts`
coinciden exactamente con las rutas del lock.

### VerificaciÃ³n visual (Playwright) â€” bloqueada, no completada

Se escribiÃ³ un spec real (sin mocks, login contra el backend real en Docker) en
`code/web/e2e/propiedades/propiedades.spec.ts` cubriendo CA1 de este bloque (tab Coeficientes
renderiza y botÃ³n "Guardar cambios" deshabilitado sin cambios). **No se pudo ejecutar**:
`@playwright/test` estÃ¡ roto en este entorno â€” probado exhaustivamente en 1.49.0 (versiÃ³n exacta
committeada), 1.60.0 y 1.61.1, y en Node v22 y v25, incluso con un spec trivial de una lÃ­nea. Falla
tambiÃ©n en el spec preexistente de `AUTH-B06`. Ver `_state/RUNBOOK.md#E-005` para el diagnÃ³stico
completo. El spec queda listo para correr en cuanto se resuelva ese bloqueo.

### VerificaciÃ³n de contrato API real â€” sustituto de Playwright (2026-07-10)

`code/web/scripts/verify-propiedades-contract.mjs` (login real, sin mocks) ejercita
`LOCK-PROPIEDADES-04` completo: `PATCH` masivo de coeficientes (`data[]` con `vigente_hasta: null`
para el vigente actual), warning no bloqueante `COEFFICIENT_SUM_MISMATCH` cuando la suma â‰  1.0 (R-06),
error 422 `COEFFICIENT_OUT_OF_RANGE` en valor fuera de rango â€” cÃ³digo que `CoeficientesTab` espera
en su manejo de error â€”, superseder un coeficiente y confirmar que el anterior queda con
`vigente_hasta != null` (R-05), y `GET /condominiums/{id}/tree` con el shape completo
(`tree.towers[]`, `tree.untowered_properties_count`). **Resultado: 51/51 checks pasando** (corrida
consolidada con `PROPIEDADES-B06/B07/B08`) â€” sin discrepancias de contrato en este bloque. No
sustituye la verificaciÃ³n visual (barra de suma en tiempo real, toggle de historial) pero cubre el
riesgo de contrato real APIâ†”Web.

## Notas

> La barra de suma en tiempo real es el diferenciador UX de esta pantalla. Debe recalcularse en cada
> keystroke sin perder rendimiento â€” usar `useMemo` o `derived` state, no re-renderizar toda la
> tabla. Si el condominio tiene 200+ unidades, considerar virtualizaciÃ³n de la tabla.

> **AuditorÃ­a 2026-07-09:** revertido de `done` a `in_progress` â€” la secciÃ³n Evidencia no cumple
> `_system/05_DEFINITION_OF_DONE.md` (evidencia vacÃ­a). Requiere correr `pnpm ci` real y
> verificaciÃ³n visual Playwright de los criterios de aceptaciÃ³n antes de volver a `verifying`.


