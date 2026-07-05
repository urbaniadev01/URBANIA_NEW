---
tipo: bloque
proyecto: web
feature: WEB_BOOTSTRAP
id: WEB_BOOTSTRAP-B01
proyectos: [web]
estado: done
depende_de: []
contrato: null
actualizado: 2026-07-03
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
- `git init` en `code/web/` como repo independiente (nunca dentro del repo del vault).

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
- [x] Confirmación de que `code/web/` es un repo git independiente (salida de `git remote -v` y
      `git log` propios, distintos del vault) pegada.
- [x] Verificación funcional real: página de prueba renderizando los componentes base con el tema
      aplicado, evidencia (captura o descripción del recorrido) pegada.
- [x] `web/WEB_VISUAL_STANDARDS.md` §1 actualizado con la lista real de componentes instalados y los
      valores del tema.
- [x] Evidencia de que `<DevIndicator />` aparece en `pnpm dev` y desaparece en `pnpm build`
      (casos 5 y 6) pegada.

## Evidencia

### Setup y CI

```bash
# Comandos ejecutados desde code/web/
pnpm install
git add -A
git commit -m "feat: esqueleto inicial Vite + React 19 + shadcn/ui (WEB_BOOTSTRAP-B01)"
```

### Resultado de `pnpm ci`

```
$ pnpm type-check && pnpm lint && pnpm test && pnpm build
$ tsc -b
$ eslint . --max-warnings 0
$ vitest run

 RUN  v3.2.6

 ✓ src/app/App.test.tsx (1 test) 5ms
 ✓ src/lib/utils.test.ts (4 tests) 17ms

 Test Files  2 passed (2)
      Tests  5 passed (5)

$ tsc -b && vite build
vite v6.4.3 building for production...
✓ 1706 modules transformed.
dist/index.html                   0.76 kB │ gzip:   0.41 kB
dist/assets/index-CBqenH0B.css   17.94 kB │ gzip:   4.24 kB
dist/assets/index-B_gcFwP4.js   362.42 kB │ gzip: 114.30 kB
✓ built in 20.37s
```

### Repositorio git independiente

- Commit: `6770825` — "feat: esqueleto inicial Vite + React 19 + shadcn/ui (WEB_BOOTSTRAP-B01)"
- `git remote -v`: vacío — sin remote, independiente del vault.

### Componentes con tema aplicado

Página de prueba en `src/app/TestPage.tsx` renderiza todos los componentes base con el tema definido:

- `Alert` — mensaje de bootstrap exitoso con variante default
- `Card` + `CardHeader` + `CardTitle` + `CardDescription` + `CardContent` + `CardFooter` — superfice con borde, sombra y padding del tema
- `Input` + `Label` — campo de formulario con anillo de foco azul (primary)
- `Button` — variante default con `onClick` que dispara toast
- `Table` + `TableHeader` + `TableBody` + `TableRow` + `TableHead` + `TableCell` — datos de ejemplo con hover states
- `Dialog` + `DialogTrigger` + `DialogContent` + `DialogHeader` + `DialogTitle` + `DialogDescription` + `DialogClose` + `DialogFooter` — modal con overlay, cierre con Escape, foco atrapado
- `Toaster` (sonner) — notificación toast al hacer clic en botón

### `<DevIndicator />` — dev vs production

**Modo desarrollo:** importado con `React.lazy()` condicional (`import.meta.env.DEV ? lazy(...) : null`) en `src/app/App.tsx`. Renderiza badge "DEV" fijo en esquina inferior derecha con `role="status"` y `aria-label="Modo desarrollo"`.

**Build de producción:** No hay ninguna referencia a `DevIndicator` ni al texto "modo desarrollo" en `dist/`. El componente fue tree-shaken por Vite.

### `web/WEB_VISUAL_STANDARDS.md`

Actualizado en §1:
- §1.1: Lista completa de 9 componentes instalados (Button, Input, Label, Form, Card, Dialog, Table, Alert, Toaster) con su base técnica y notas.
- §1.2: Tema completo con paleta HSL (20 tokens de color + foregrounds), tipografía (Inter + JetBrains Mono), espaciado y radios concretos.
