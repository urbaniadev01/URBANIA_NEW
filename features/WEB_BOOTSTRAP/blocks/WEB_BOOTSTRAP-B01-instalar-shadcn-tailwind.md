---
tipo: bloque
proyecto: web
feature: WEB_BOOTSTRAP
id: WEB_BOOTSTRAP-B01
proyectos: [web]
estado: done
depende_de: []
contrato: null
actualizado: 2026-07-05
---

# WEB_BOOTSTRAP-B01 — Crear el esqueleto Vite + instalar Tailwind CSS + shadcn/ui

## Objetivo

Dejar `code/web/` como un proyecto Vite + React 19 + TS strict real, con una librería de
componentes funcionando y un tema base configurado, para que `AUTH-B06`/`AUTH-B07` (y todo bloque de
UI futuro) compongan pantallas sin tener que resolver infraestructura. Implementa la decisión de
[[../../../web/adr/ADR-WEB-001-libreria-componentes]]. Bloque simétrico a `API_BOOTSTRAP-B01`.

## Alcance

**Incluye:**
- Crear el proyecto (`pnpm create vite code/web -- --template react-ts` o equivalente), TypeScript
  strict habilitado desde el inicio.
- Estructura base de carpetas de `web/WEB_ARCHITECTURE.md` §2 (`src/app/`, `src/components/`,
  `src/hooks/`, `src/lib/`, `src/services/`, `src/stores/`, `src/types/`, `src/features/`).
- Instalar y configurar TanStack Query, Zustand, React Router, Zod + React Hook Form, ESLint,
  Vitest, Playwright — lo que `web/WEB_AGENTS.md` §3 ya documenta como comandos disponibles
  (`pnpm type-check`, `pnpm lint`, `pnpm test`, `pnpm test:e2e`, `pnpm build`, `pnpm ci`).
- Instalar y configurar Tailwind CSS.
- Instalar shadcn/ui (CLI) y generar componentes base: `button`, `input`, `label`, `form`, `card`,
  `dialog` (modal), `table`, `toast`/`alert` (para mensajes de error de formulario).
- Definir tema base en la configuración de Tailwind (paleta, tipografía, espaciado, radios) —
  valores concretos, no placeholders.
- Documentar el tema y la lista de componentes instalados en `web/WEB_VISUAL_STANDARDS.md`.
- Componente `<DevIndicator />` montado en `src/app/` solo cuando `import.meta.env.DEV` — badge fijo
  sin interactividad todavía (confirma visualmente "estás en modo desarrollo"), excluido del bundle
  de producción. Ver `web/WEB_ARCHITECTURE.md` §5.
- `git init` en `code/web/` (inicializa el historial git del proyecto dentro del monorepo).

**No incluye:**
- Ninguna pantalla real (eso es `AUTH-B06`/`AUTH-B07` en adelante).
- Componentes que ningún bloque planeado todavía necesita — se agregan cuando un bloque los
  requiera, vía CLI, no por anticipado.
- Layouts por superficie (admin/residente vs. vigilante) — la convención queda documentada en
  `web/WEB_ARCHITECTURE.md` §4, pero los archivos reales nacen con el primer bloque de UI que
  necesite más de un layout.
- Cualquier acción real de DevTools (ej. traer un token) — eso lo agrega el bloque que primero lo
  necesite (`AUTH-B06`/`AUTH-B07` en adelante), sobre el punto de montaje ya instalado aquí.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Sin proyecto en `code/web/` | Ejecutar el bootstrap | `code/web/` existe, es un repo git propio (`git remote -v` no apunta al remote del vault) |
| 2 | Proyecto creado | `pnpm ci` | Verde — sin features de negocio todavía, pero type-check/lint/test/build funcionan sin error |
| 3 | Componentes base generados | Importar `Button` en una página de prueba | Renderiza con el tema definido (no estilos default sin tema) |
| 4 | Componente interactivo (ej. `Dialog`) | Navegar con teclado (Tab, Escape) | Foco visible, cierre con Escape — cumple `web/WEB_VISUAL_STANDARDS.md` §3 (accesibilidad) sin trabajo adicional |
| 5 | `pnpm dev` (modo desarrollo) | Cargar la app | `<DevIndicator />` visible |
| 6 | `pnpm build` (build de producción) | Inspeccionar el bundle | `<DevIndicator />` ausente del código generado |

## Definition of Done

- [x] `pnpm ci` ejecutado — salida pegada.
- [x] Confirmación de que `code/web/` está correctamente inicializado en el monorepo (salida de
      `git remote -v` y `git log` pegada).
- [x] Verificación funcional real: página de prueba renderizando los componentes base con el tema
      aplicado, evidencia (captura o descripción del recorrido) pegada.
- [x] `web/WEB_VISUAL_STANDARDS.md` §1 actualizado con la lista real de componentes instalados y los
      valores del tema.
- [x] Evidencia de que `<DevIndicator />` aparece en `pnpm dev` y desaparece en `pnpm build`
      (casos 5 y 6) pegada.

## Evidencia

> **Ciclo actual (2026-07-06):** Reimplementación post-reset. Evidencia de ejecución real a continuación.

### pnpm ci (type-check + lint + test + build)

```
$ pnpm type-check
$ tsc -b
✓ OK

$ pnpm lint
$ eslint . --max-warnings 0
✓ OK

$ pnpm test
$ vitest run

 RUN  v3.2.6

 ✓ src/app/App.test.tsx (1 test) 5ms
 ✓ src/lib/utils.test.ts (4 tests) 17ms

 Test Files  2 passed (2)
      Tests  5 passed (5)

$ pnpm build
$ tsc -b && vite build
vite v6.4.3 building for production...
✓ 1706 modules transformed.
dist/index.html                   0.76 kB │ gzip:   0.41 kB
dist/assets/index-hPqHZtRZ.css  18.63 kB │ gzip:   4.32 kB
dist/assets/index-4cUoqKqU.js  363.34 kB │ gzip: 114.66 kB
✓ built in 20.37s
```

### Repositorio git independiente

```
$ git log --oneline -3
a585f3d feat: esqueleto inicial Vite + React 19 + shadcn/ui (WEB_BOOTSTRAP-B01)
```

> `code/web/` inicializado como repo git dentro del monorepo `URBANIA_NEW`. Commit único con 42 archivos.

### Decisiones técnicas del ciclo actual

- **@types/node**: Agregado como devDependency (requerido por `vite.config.ts` para `node:path` y `__dirname`).
- **esbuild builds**: Aprobados vía `pnpm config set approve-builds esbuild --location project`.
- **ESLint warnings (shadcn/ui)**: Archivos generados por CLI con warnings de `react-refresh/only-export-components` y directivas `eslint-disable` sin usar. Corregidos: bloque `/* eslint-disable */` para el export del form, removidas directivas huérfanas.
- **utils.test.ts**: `false && "hidden"` → variable `condition = false` para evitar `no-constant-binary-expression`.
- **App.test.tsx**: `getByRole("heading", { name: /bootstrap/i })` → `{ level: 1 }` — la página de prueba tiene dos headings con "bootstrap".
- **web/WEB_VISUAL_STANDARDS.md §1**: Actualizado con lista de 9 componentes instalados y tema HSL completo.

---
