---
tipo: adr
proyecto: web
actualizado: 2026-07-03
---

# ADR-WEB-001: Librería de componentes base — shadcn/ui + Tailwind CSS

## Estado

Aceptada. Decisión delegada por el usuario al agente para esta sesión puntual (ver
`_system/templates/ADR.md` — se registra la delegación aquí mismo).

## Contexto

Urbania Web es un panel **administrativo puro**: tablas, formularios, dashboards internos, 100%
detrás de login, sin necesidad de identidad de marca pública ni diseño visual diferenciado como
ventaja competitiva. Diseñar cada pantalla desde cero (mockups, HTML de referencia, decisiones de
espaciado/color pantalla por pantalla) es esfuerzo que no se traduce en valor para este tipo de
producto — el valor está en la lógica de negocio (RBAC, cobranza, comunicaciones), no en la
originalidad visual.

La alternativa a diseñar desde cero es adoptar una librería de componentes ya resuelta y enfocar el
esfuerzo de cada bloque de UI en composición + reglas de negocio, no en inventar un botón o un
modal.

## Decisión

Se adopta **shadcn/ui** (componentes basados en Radix UI, generados vía CLI y copiados al
repo — no una dependencia de runtime que se actualiza sola) sobre **Tailwind CSS** como motor de
estilos.

**Por qué esta combinación y no otra:**
- Los componentes se generan como código propio del proyecto (`src/components/ui/`) — se pueden
  auditar, tipar en TS strict y modificar sin pelear contra el sistema de theming de una librería de
  componentes con estado interno (a diferencia de MUI o Ant Design).
- Radix UI (la base de shadcn/ui) resuelve accesibilidad (foco, ARIA, navegación por teclado) de
  fábrica — cumple directamente el requisito de `web/WEB_VISUAL_STANDARDS.md` §3 sin trabajo
  adicional por componente.
- Encaja sin fricción con el stack ya decidido (Vite + React 19 + TS strict) y no impone un sistema
  de props/theming propio que compita con Zustand/TanStack Query.
- Es el estándar de facto actual para paneles administrativos construidos con React — reduce la
  superficie de decisiones de diseño no resueltas a cero para la mayoría de las pantallas.

## Consecuencias

**Positivas:**
- La mayoría de las pantallas no requieren ninguna referencia visual (imagen, HTML, mockup) antes de
  implementarse — se componen con los componentes ya instalados y el criterio de aceptación de la
  tarjeta del bloque alcanza como especificación.
- Consistencia visual automática entre pantallas sin necesidad de un documento de design tokens
  extenso.
- El esfuerzo de cada bloque de UI se concentra en lógica de negocio y estados (carga/error/vacío),
  no en CSS.

**Trade-offs:**
- Para las pocas pantallas genuinamente novedosas (ver política en `WEB_VISUAL_STANDARDS.md` §2),
  no hay un componente pre-hecho — se documenta como excepción, no como el caso general.
- Si en el futuro el producto necesita una identidad visual de marca fuerte (por ejemplo, si Web
  pasa a tener una cara pública), esta decisión se revisita — no es una restricción permanente, es
  la decisión correcta para el perfil actual del producto.

## Alcance de la decisión

- `web/WEB_ARCHITECTURE.md` §2 — `src/components/` se puebla con componentes generados por shadcn/ui.
- `web/WEB_VISUAL_STANDARDS.md` — política de cuándo sí/no hace falta una referencia visual.
- Instalación real: bloque `WEB_BOOTSTRAP-B01` (ver [[../../features/WEB_BOOTSTRAP/PANORAMA]]).
