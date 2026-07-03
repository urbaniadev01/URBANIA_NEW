---
tipo: referencia
proyecto: web
actualizado: 2026-07-03
---

# WEB_VISUAL_STANDARDS — Sistema de diseño (base)

> Urbania Web es un panel **administrativo puro** — no hay identidad de marca pública que resolver
> pantalla por pantalla. Ver [[adr/ADR-WEB-001-libreria-componentes]] para la decisión completa: se
> adopta una librería de componentes en vez de diseñar desde cero.

## 1. Base — shadcn/ui + Tailwind CSS

Se instala y configura en el bloque `WEB_BOOTSTRAP-B01`
(ver [[../features/WEB_BOOTSTRAP/PANORAMA]]) — es el único bloque que fija tokens base (color,
tipografía, espaciado, tema) para todo el proyecto. Ningún bloque de feature posterior redefine
tokens base por su cuenta; si un color/espaciado no está en el tema, se agrega al tema en ese mismo
bloque, no como un valor suelto en el componente.

## 2. Política de referencia visual — por defecto NO hace falta

Porque los componentes ya están resueltos, **la mayoría de las pantallas se implementan sin ningún
mockup, imagen o HTML de referencia previo** — la tabla de "Criterios de aceptación" de la tarjeta
del bloque, más los componentes de `src/components/ui/`, alcanzan como especificación completa. No
se crea un tipo de documento nuevo para esto (evita fragmentar la documentación en más piezas de las
necesarias).

**Excepción — cuándo sí adjuntar una referencia:** si una pantalla es genuinamente novedosa (un
dashboard con visualización de datos, un layout que no es un CRUD/formulario estándar), se puede
adjuntar una imagen o una descripción de wireframe directamente en la sección "Qué muestra" del
propio `WEB_SCREEN.md` de esa pantalla (ver `_system/templates/WEB_SCREEN.md`) — no en un documento
de diseño aparte. Es la excepción, no el flujo por defecto.

## 3. Convención de componentes

- Componentes base viven en `src/components/ui/` (generados vía CLI de shadcn/ui) y
  `src/components/` (composiciones propias sobre esa base) — compartidos por todos los features. Un
  feature nunca reimplementa un componente que ya existe en la librería instalada.
- Antes de construir un componente custom, se verifica si shadcn/ui ya lo resuelve — construir uno
  propio es la excepción, no el primer camino.
- Un componente nuevo que un feature necesita y que es genuinamente reusable se promueve a
  `src/components/` en el mismo bloque que lo origina, no se dejan "para después" copias locales
  que luego divergen.

## 3. Accesibilidad

Todo componente interactivo nuevo cumple: foco visible, contraste AA como piso, y navegable por
teclado — esto es parte del DoD visual de cualquier bloque de UI, no un ítem opcional.

## 4. Identidad visual compartida con futuros clientes

Si en el futuro se decide compartir identidad visual entre Web y un cliente adicional, esa decisión
se registra como una fila nueva en [[../shared/SYSTEM_CONTRACT]] §1 y sigue el protocolo de
[[../_system/04_CROSS_PROJECT]] — no se asume compartida por defecto.
