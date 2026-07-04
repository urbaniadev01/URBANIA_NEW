---
tipo: bloque
proyecto: web
feature: WEB_BOOTSTRAP
id: WEB_BOOTSTRAP-B01
proyectos: [web]
estado: ready
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

- [ ] `pnpm ci` ejecutado — salida pegada.
- [ ] Confirmación de que `code/web/` es un repo git independiente (salida de `git remote -v` y
      `git log` propios, distintos del vault) pegada.
- [ ] Verificación funcional real: página de prueba renderizando los componentes base con el tema
      aplicado, evidencia (captura o descripción del recorrido) pegada.
- [ ] `web/WEB_VISUAL_STANDARDS.md` §1 actualizado con la lista real de componentes instalados y los
      valores del tema.
- [ ] Evidencia de que `<DevIndicator />` aparece en `pnpm dev` y desaparece en `pnpm build`
      (casos 5 y 6) pegada.

## Evidencia

_Vacío — se completa al ejecutar este bloque._

## Notas

_Vacío._
