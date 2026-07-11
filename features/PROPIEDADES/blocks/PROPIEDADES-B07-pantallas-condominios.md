---
tipo: bloque
proyecto: web
feature: PROPIEDADES
id: PROPIEDADES-B07
proyectos: [web]
estado: done
depende_de: [PROPIEDADES-B03, WEB_BOOTSTRAP-B01]
contrato: consume
verificacion_critica: false
actualizado: 2026-07-11
---

# PROPIEDADES-B07 â€” Pantallas de condominios (CondominiosList + DetalleCondominio)

## Objetivo

Construir las pantallas de gestiÃ³n de condominios: una vista de lista con cards y una vista de
detalle con tabs (Torres, ConfiguraciÃ³n). Integra con `LOCK-PROPIEDADES-02`.

## Alcance

- **Incluye:**
  - PÃ¡gina `CondominiosList` (`/condominios`): grid de cards, cada card muestra nombre, direcciÃ³n,
    NIT, conteo de torres y unidades. Barra de bÃºsqueda por nombre. BotÃ³n "Nuevo condominio".
  - PÃ¡gina `DetalleCondominio` (`/condominios/{id}`): layout con header (nombre, breadcrumb) y dos
    tabs:
    - Tab **Torres**: lista de torres con nombre, conteo de unidades. Sheet de crear/editar torre.
      DiÃ¡logo de confirmaciÃ³n para eliminar.
    - Tab **ConfiguraciÃ³n**: formulario de ediciÃ³n del condominio (nombre, direcciÃ³n, NIT). BotÃ³n
      "Eliminar condominio" con confirmaciÃ³n (bloqueado si tiene torres/unidades â†’ toast del 409).
  - Sheet de crear/editar condominio (desde la lista o desde ConfiguraciÃ³n) con validaciÃ³n Zod.
  - DiÃ¡logos de confirmaciÃ³n para eliminaciÃ³n de torres y condominios, con manejo de errores 409.
  - IntegraciÃ³n con API: hooks/clients para `LOCK-PROPIEDADES-02`.
  - NavegaciÃ³n: click en card â†’ `DetalleCondominio`. Breadcrumb para volver a la lista.
  - DocumentaciÃ³n de pantalla en `web/features/propiedades/PROPIEDADES-condominios.md`.

- **No incluye (explÃ­citamente fuera de este bloque):**
  - Tab de Unidades dentro de DetalleCondominio (B08).
  - Tab de Coeficientes (B09).
  - Pantallas de catÃ¡logos (B06).
  - Mapa o vista geogrÃ¡fica del condominio.

## Criterios de aceptaciÃ³n

| # | Entrada | AcciÃ³n | Salida esperada |
|---|---|---|---|
| 1 | Admin logueado, API con condominios | Navegar a `/condominios` | Grid de cards con los condominios de la org |
| 2 | Lista cargada | Escribir en barra de bÃºsqueda | Cards se filtran por nombre (bÃºsqueda local o vÃ­a API) |
| 3 | Lista cargada | Click en "Nuevo condominio" | Sheet con formulario vacÃ­o (nombre, direcciÃ³n, NIT) |
| 4 | Formulario abierto, nombre vÃ¡lido | Click en "Guardar" | POST exitoso, card aparece en grid, toast de Ã©xito |
| 5 | Formulario abierto, nombre duplicado (API 422) | Click en "Guardar" | Toast de error: "Ya existe un condominio con ese nombre" |
| 6 | Grid con cards | Click en una card | Navega a `/condominios/{id}`, breadcrumb visible |
| 7 | Detalle, tab "Torres" activo | Ver contenido | Lista de torres (nombre, conteo unidades), botÃ³n "Nueva torre" |
| 8 | Tab Torres | Click en "Nueva torre" | Sheet con campo `nombre` |
| 9 | Sheet torre, nombre vÃ¡lido | Click en "Guardar" | POST exitoso, torre aparece en lista |
| 10 | Torre sin unidades | Click en "Eliminar" â†’ confirmar | DELETE exitoso, torre desaparece |
| 11 | Torre con unidades (API 409) | Click en "Eliminar" | Toast: "No se puede eliminar: la torre tiene X unidades activas" |
| 12 | Detalle, tab "ConfiguraciÃ³n" | Click en tab | Formulario de ediciÃ³n con datos del condominio precargados |
| 13 | ConfiguraciÃ³n, datos modificados | Click en "Guardar" | PATCH exitoso, toast de Ã©xito |
| 14 | ConfiguraciÃ³n, condominio sin hijos | Click en "Eliminar condominio" â†’ confirmar | DELETE exitoso, redirige a `/condominios` |
| 15 | ConfiguraciÃ³n, condominio con torres (API 409) | Click en "Eliminar condominio" | Toast: "No se puede eliminar: el condominio tiene torres activas" |
| 16 | API no disponible | Cualquier acciÃ³n | Toast de error, UI no se rompe |

## Contrato

Este bloque **consume** el contrato `LOCK-PROPIEDADES-02` (producido por `PROPIEDADES-B03`). No
puede pasar a `ready` sin ese lock vigente.

## Definition of Done

- [ ] `pnpm ci` ejecutado â€” salida completa pegada.
- [ ] VerificaciÃ³n visual real (Playwright) recorriendo: crear condominio, navegar a detalle, crear
      torre, eliminar torre (Ã©xito y 409), editar condominio, eliminar condominio (Ã©xito y 409).
- [ ] Confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que la integraciÃ³n respeta exactamente
      `LOCK-PROPIEDADES-02`.
- [ ] `web/features/propiedades/PROPIEDADES-condominios.md` creado desde la plantilla
      `_system/templates/WEB_SCREEN.md`.
- [ ] Componentes usados provienen de la librerÃ­a base instalada en `WEB_BOOTSTRAP-B01`.
- [ ] `web/WEB_API_CLIENT.md` actualizado con los hooks/clientes nuevos hacia los endpoints de
      condominios y torres.

## Evidencia

### `pnpm run ci` (code/web) â€” 2026-07-10

```
$ pnpm type-check && pnpm lint && pnpm test && pnpm build
$ tsc -b
$ eslint . --max-warnings 0
$ vitest run
...
 âœ“ src/features/propiedades/__tests__/CondominiosListPage.test.tsx (6 tests)
 âœ“ src/features/propiedades/__tests__/DetalleCondominioPage.test.tsx (6 tests)
 Test Files  14 passed (14)
      Tests  126 passed (126)
$ tsc -b && vite build
âœ“ 1797 modules transformed.
âœ“ built in 11.71s
```

Misma corrida consolidada de `pnpm ci` que `PROPIEDADES-B06/B08/B09` (un Ãºnico comando en el
monorepo web) â€” sesiÃ³n de cierre de DoD del 2026-07-10.

### Tests de componente nuevos

- `code/web/src/features/propiedades/__tests__/CondominiosListPage.test.tsx` (6 tests): grid de
  cards, bÃºsqueda local por nombre, validaciÃ³n al crear sin nombre, crear exitoso, nombre duplicado
  (422) no cierra el sheet.
- `code/web/src/features/propiedades/__tests__/DetalleCondominioPage.test.tsx` (6 tests):
  breadcrumb + tab Torres activo por defecto, crear torre, eliminar torre, tab ConfiguraciÃ³n
  (datos + editar), eliminar condominio sin hijos.

### ConfirmaciÃ³n de contrato

`LOCK-PROPIEDADES-02` (`_state/contracts/CONTRACT_LOCKS.md`) sigue vigente, congelado 2026-07-08,
producido por `PROPIEDADES-B03` (`done`). Los endpoints usados en
`code/web/src/features/propiedades/api/condominiums.ts` y `towers.ts` coinciden exactamente con las
rutas del lock (`/api/v1/condominiums`, `/api/v1/condominiums/{id}/towers`, `/api/v1/towers/{id}`).

### VerificaciÃ³n visual (Playwright) â€” bloqueada, no completada

Se escribiÃ³ un spec real (sin mocks, login contra el backend real en Docker) en
`code/web/e2e/propiedades/propiedades.spec.ts` cubriendo CA1, CA3-CA7 de este bloque. **No se pudo
ejecutar**: `@playwright/test` estÃ¡ roto en este entorno â€” probado exhaustivamente en 1.49.0 (versiÃ³n
exacta committeada), 1.60.0 y 1.61.1, y en Node v22 y v25, incluso con un spec trivial de una lÃ­nea.
Falla tambiÃ©n en el spec preexistente de `AUTH-B06`. Ver `_state/RUNBOOK.md#E-005` para el
diagnÃ³stico completo. El spec queda listo para correr en cuanto se resuelva ese bloqueo.

### VerificaciÃ³n de contrato API real â€” sustituto de Playwright (2026-07-10)

`code/web/scripts/verify-propiedades-contract.mjs` (login real, sin mocks) ejercita
`LOCK-PROPIEDADES-02` completo para este bloque: listar condominios, crear, `GET` detalle con
`towers[]` anidado, crear torre, y el bloqueo de `DELETE` de un condominio con torres (409
`CONDOMINIUM_HAS_TOWERS` â€” mismo cÃ³digo que usa `DetalleCondominioPage` para deshabilitar el botÃ³n
"Eliminar condominio"). **Resultado: 51/51 checks pasando** (corrida consolidada con
`PROPIEDADES-B06/B08/B09`) â€” sin discrepancias de contrato en este bloque. No sustituye la
verificaciÃ³n visual (renderizado, routing de navegador) pero cubre el riesgo de contrato real
APIâ†”Web.

## Notas

> El tab "Torres" y el tab "ConfiguraciÃ³n" comparten la misma pÃ¡gina `DetalleCondominio`. En el
> futuro, B08 agrega un tercer tab "Unidades" y B09 un cuarto tab "Coeficientes". La estructura de
> tabs debe ser extensible.

> **AuditorÃ­a 2026-07-09:** revertido de `done` a `in_progress` â€” la secciÃ³n Evidencia no cumple
> `_system/05_DEFINITION_OF_DONE.md` (evidencia vacÃ­a). Requiere correr `pnpm ci` real y
> verificaciÃ³n visual Playwright de los criterios de aceptaciÃ³n antes de volver a `verifying`.


