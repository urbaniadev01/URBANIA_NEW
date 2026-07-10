---
tipo: bloque
proyecto: web
feature: PROPIEDADES
id: PROPIEDADES-B07
proyectos: [web]
estado: verifying
depende_de: [PROPIEDADES-B03, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: false
actualizado: 2026-07-10
---

# PROPIEDADES-B07 — Pantallas de condominios (CondominiosList + DetalleCondominio)

## Objetivo

Construir las pantallas de gestión de condominios: una vista de lista con cards y una vista de
detalle con tabs (Torres, Configuración). Integra con `LOCK-PROPIEDADES-02`.

## Alcance

- **Incluye:**
  - Página `CondominiosList` (`/condominios`): grid de cards, cada card muestra nombre, dirección,
    NIT, conteo de torres y unidades. Barra de búsqueda por nombre. Botón "Nuevo condominio".
  - Página `DetalleCondominio` (`/condominios/{id}`): layout con header (nombre, breadcrumb) y dos
    tabs:
    - Tab **Torres**: lista de torres con nombre, conteo de unidades. Sheet de crear/editar torre.
      Diálogo de confirmación para eliminar.
    - Tab **Configuración**: formulario de edición del condominio (nombre, dirección, NIT). Botón
      "Eliminar condominio" con confirmación (bloqueado si tiene torres/unidades → toast del 409).
  - Sheet de crear/editar condominio (desde la lista o desde Configuración) con validación Zod.
  - Diálogos de confirmación para eliminación de torres y condominios, con manejo de errores 409.
  - Integración con API: hooks/clients para `LOCK-PROPIEDADES-02`.
  - Navegación: click en card → `DetalleCondominio`. Breadcrumb para volver a la lista.
  - Documentación de pantalla en `web/features/propiedades/PROPIEDADES-condominios.md`.

- **No incluye (explícitamente fuera de este bloque):**
  - Tab de Unidades dentro de DetalleCondominio (B08).
  - Tab de Coeficientes (B09).
  - Pantallas de catálogos (B06).
  - Mapa o vista geográfica del condominio.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Admin logueado, API con condominios | Navegar a `/condominios` | Grid de cards con los condominios de la org |
| 2 | Lista cargada | Escribir en barra de búsqueda | Cards se filtran por nombre (búsqueda local o vía API) |
| 3 | Lista cargada | Click en "Nuevo condominio" | Sheet con formulario vacío (nombre, dirección, NIT) |
| 4 | Formulario abierto, nombre válido | Click en "Guardar" | POST exitoso, card aparece en grid, toast de éxito |
| 5 | Formulario abierto, nombre duplicado (API 422) | Click en "Guardar" | Toast de error: "Ya existe un condominio con ese nombre" |
| 6 | Grid con cards | Click en una card | Navega a `/condominios/{id}`, breadcrumb visible |
| 7 | Detalle, tab "Torres" activo | Ver contenido | Lista de torres (nombre, conteo unidades), botón "Nueva torre" |
| 8 | Tab Torres | Click en "Nueva torre" | Sheet con campo `nombre` |
| 9 | Sheet torre, nombre válido | Click en "Guardar" | POST exitoso, torre aparece en lista |
| 10 | Torre sin unidades | Click en "Eliminar" → confirmar | DELETE exitoso, torre desaparece |
| 11 | Torre con unidades (API 409) | Click en "Eliminar" | Toast: "No se puede eliminar: la torre tiene X unidades activas" |
| 12 | Detalle, tab "Configuración" | Click en tab | Formulario de edición con datos del condominio precargados |
| 13 | Configuración, datos modificados | Click en "Guardar" | PATCH exitoso, toast de éxito |
| 14 | Configuración, condominio sin hijos | Click en "Eliminar condominio" → confirmar | DELETE exitoso, redirige a `/condominios` |
| 15 | Configuración, condominio con torres (API 409) | Click en "Eliminar condominio" | Toast: "No se puede eliminar: el condominio tiene torres activas" |
| 16 | API no disponible | Cualquier acción | Toast de error, UI no se rompe |

## Contrato

Este bloque **consume** el contrato `LOCK-PROPIEDADES-02` (producido por `PROPIEDADES-B03`). No
puede pasar a `ready` sin ese lock vigente.

## Definition of Done

- [ ] `pnpm ci` ejecutado — salida completa pegada.
- [ ] Verificación visual real (Playwright) recorriendo: crear condominio, navegar a detalle, crear
      torre, eliminar torre (éxito y 409), editar condominio, eliminar condominio (éxito y 409).
- [ ] Confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que la integración respeta exactamente
      `LOCK-PROPIEDADES-02`.
- [ ] `web/features/propiedades/PROPIEDADES-condominios.md` creado desde la plantilla
      `_system/templates/WEB_SCREEN.md`.
- [ ] Componentes usados provienen de la librería base instalada en `WEB_BOOTSTRAP-B01`.
- [ ] `web/WEB_API_CLIENT.md` actualizado con los hooks/clientes nuevos hacia los endpoints de
      condominios y torres.

## Evidencia

### `pnpm run ci` (code/web) — 2026-07-10

```
$ pnpm type-check && pnpm lint && pnpm test && pnpm build
$ tsc -b
$ eslint . --max-warnings 0
$ vitest run
...
 ✓ src/features/propiedades/__tests__/CondominiosListPage.test.tsx (6 tests)
 ✓ src/features/propiedades/__tests__/DetalleCondominioPage.test.tsx (6 tests)
 Test Files  14 passed (14)
      Tests  126 passed (126)
$ tsc -b && vite build
✓ 1797 modules transformed.
✓ built in 11.71s
```

Misma corrida consolidada de `pnpm ci` que `PROPIEDADES-B06/B08/B09` (un único comando en el
monorepo web) — sesión de cierre de DoD del 2026-07-10.

### Tests de componente nuevos

- `code/web/src/features/propiedades/__tests__/CondominiosListPage.test.tsx` (6 tests): grid de
  cards, búsqueda local por nombre, validación al crear sin nombre, crear exitoso, nombre duplicado
  (422) no cierra el sheet.
- `code/web/src/features/propiedades/__tests__/DetalleCondominioPage.test.tsx` (6 tests):
  breadcrumb + tab Torres activo por defecto, crear torre, eliminar torre, tab Configuración
  (datos + editar), eliminar condominio sin hijos.

### Confirmación de contrato

`LOCK-PROPIEDADES-02` (`_state/contracts/CONTRACT_LOCKS.md`) sigue vigente, congelado 2026-07-08,
producido por `PROPIEDADES-B03` (`done`). Los endpoints usados en
`code/web/src/features/propiedades/api/condominiums.ts` y `towers.ts` coinciden exactamente con las
rutas del lock (`/api/v1/condominiums`, `/api/v1/condominiums/{id}/towers`, `/api/v1/towers/{id}`).

### Verificación visual (Playwright) — bloqueada, no completada

Se escribió un spec real (sin mocks, login contra el backend real en Docker) en
`code/web/e2e/propiedades/propiedades.spec.ts` cubriendo CA1, CA3-CA7 de este bloque. **No se pudo
ejecutar**: `@playwright/test` está roto en este entorno — probado exhaustivamente en 1.49.0 (versión
exacta committeada), 1.60.0 y 1.61.1, y en Node v22 y v25, incluso con un spec trivial de una línea.
Falla también en el spec preexistente de `AUTH-B06`. Ver `_state/RUNBOOK.md#E-005` para el
diagnóstico completo. El spec queda listo para correr en cuanto se resuelva ese bloqueo.

### Verificación de contrato API real — sustituto de Playwright (2026-07-10)

`code/web/scripts/verify-propiedades-contract.mjs` (login real, sin mocks) ejercita
`LOCK-PROPIEDADES-02` completo para este bloque: listar condominios, crear, `GET` detalle con
`towers[]` anidado, crear torre, y el bloqueo de `DELETE` de un condominio con torres (409
`CONDOMINIUM_HAS_TOWERS` — mismo código que usa `DetalleCondominioPage` para deshabilitar el botón
"Eliminar condominio"). **Resultado: 51/51 checks pasando** (corrida consolidada con
`PROPIEDADES-B06/B08/B09`) — sin discrepancias de contrato en este bloque. No sustituye la
verificación visual (renderizado, routing de navegador) pero cubre el riesgo de contrato real
API↔Web.

## Notas

> El tab "Torres" y el tab "Configuración" comparten la misma página `DetalleCondominio`. En el
> futuro, B08 agrega un tercer tab "Unidades" y B09 un cuarto tab "Coeficientes". La estructura de
> tabs debe ser extensible.

> **Auditoría 2026-07-09:** revertido de `done` a `in_progress` — la sección Evidencia no cumple
> `_system/05_DEFINITION_OF_DONE.md` (evidencia vacía). Requiere correr `pnpm ci` real y
> verificación visual Playwright de los criterios de aceptación antes de volver a `verifying`.
