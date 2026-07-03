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

# WEB_BOOTSTRAP-B01 — Instalar Tailwind CSS + shadcn/ui y fijar tema base

## Objetivo

Dejar el proyecto Web con una librería de componentes funcionando y un tema base configurado, para
que `AUTH-B06`/`AUTH-B07` (y todo bloque de UI futuro) compongan pantallas sin tener que resolver
infraestructura visual. Implementa la decisión de
[[../../../web/adr/ADR-WEB-001-libreria-componentes]].

## Alcance

**Incluye:**
- Instalar y configurar Tailwind CSS en el proyecto Vite.
- Instalar shadcn/ui (CLI) y generar componentes base: `button`, `input`, `label`, `form`, `card`,
  `dialog` (modal), `table`, `toast`/`alert` (para mensajes de error de formulario).
- Definir tema base en la configuración de Tailwind (paleta, tipografía, espaciado, radios) —
  valores concretos, no placeholders.
- Documentar el tema y la lista de componentes instalados en `web/WEB_VISUAL_STANDARDS.md`.

**No incluye:**
- Ninguna pantalla real (eso es `AUTH-B06`/`AUTH-B07` en adelante).
- Componentes que ningún bloque planeado todavía necesita — se agregan cuando un bloque los
  requiera, vía CLI, no por anticipado.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Proyecto Vite limpio, sin Tailwind | Correr la instalación | Tailwind compila sin error, `pnpm build` funciona |
| 2 | Componentes base generados | Importar `Button` en una página de prueba | Renderiza con el tema definido (no estilos default sin tema) |
| 3 | Componente interactivo (ej. `Dialog`) | Navegar con teclado (Tab, Escape) | Foco visible, cierre con Escape — cumple `web/WEB_VISUAL_STANDARDS.md` §3 (accesibilidad) sin trabajo adicional |

## Definition of Done

- [ ] `pnpm ci` ejecutado — salida pegada.
- [ ] Verificación funcional real: página de prueba renderizando los componentes base con el tema
      aplicado, evidencia (captura o descripción del recorrido) pegada.
- [ ] `web/WEB_VISUAL_STANDARDS.md` §1 actualizado con la lista real de componentes instalados y los
      valores del tema.

## Evidencia

_Vacío — se completa al ejecutar este bloque._

## Notas

_Vacío._
