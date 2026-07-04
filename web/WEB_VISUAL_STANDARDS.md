---
tipo: referencia
proyecto: web
actualizado: 2026-07-04
---

# WEB_VISUAL_STANDARDS — Sistema de diseño (base)

> Urbania Web es un panel **administrativo puro** — no hay identidad de marca pública que resolver
> pantalla por pantalla. Ver [[adr/ADR-WEB-001-libreria-componentes]] para la decisión completa: se
> adopta una librería de componentes en vez de diseñar desde cero.

## 1. Base — shadcn/ui + Tailwind CSS

Instalado y configurado en `WEB_BOOTSTRAP-B01`. Este bloque es el único que fija tokens base
(color, tipografía, espaciado, tema) para todo el proyecto. Ningún bloque de feature posterior
redefine tokens base por su cuenta; si un color/espaciado no está en el tema, se agrega al tema en
ese mismo bloque, no como un valor suelto en el componente.

### 1.1 Componentes instalados (`src/components/ui/`)

| Componente | Archivo | Base | Notas |
|---|---|---|---|
| `Button` | `button.tsx` | `@radix-ui/react-slot` | Variantes: default, destructive, outline, secondary, ghost, link. Tamaños: default, sm, lg, icon. Soporta `asChild`. |
| `Input` | `input.tsx` | HTML nativo | Estilizado con anillo de foco, estados disabled/placeholder. |
| `Label` | `label.tsx` | `@radix-ui/react-label` | Peer-disabled-aware. |
| `Form` + `FormField` + `FormItem` + `FormLabel` + `FormControl` + `FormDescription` + `FormMessage` | `form.tsx` | `react-hook-form` + `zod` | Wrapper completo de RHF con validación Zod. `FormControl` usa `Slot` de Radix. |
| `Card` + `CardHeader` + `CardTitle` + `CardDescription` + `CardContent` + `CardFooter` | `card.tsx` | HTML nativo | Superficie con borde, sombra y padding consistente. |
| `Dialog` + `DialogTrigger` + `DialogContent` + `DialogHeader` + `DialogFooter` + `DialogTitle` + `DialogDescription` + `DialogClose` | `dialog.tsx` | `@radix-ui/react-dialog` | Modal con overlay, animaciones, cierre con Escape, foco atrapado. |
| `Table` + `TableHeader` + `TableBody` + `TableFooter` + `TableRow` + `TableHead` + `TableCell` + `TableCaption` | `table.tsx` | HTML nativo | Tabla con filas hoverables, cabecera muted. |
| `Alert` + `AlertTitle` + `AlertDescription` | `alert.tsx` | HTML nativo | Variantes: default, destructive. |
| `Toaster` | `sonner.tsx` | `sonner` | Toast notifications con tema del design system. |

### 1.2 Tema — Design tokens (`src/index.css`)

**Paleta de color** (HSL — shadcn/ui CSS variables):

| Token | HSL | Hex aprox. | Uso |
|---|---|---|---|
| `--background` | `0 0% 100%` | `#ffffff` | Fondo principal de la app |
| `--foreground` | `222.2 84% 4.9%` | `#0a0f1a` | Texto principal |
| `--card` | `0 0% 100%` | `#ffffff` | Superficie de tarjetas |
| `--card-foreground` | `222.2 84% 4.9%` | `#0a0f1a` | Texto en tarjetas |
| `--popover` | `0 0% 100%` | `#ffffff` | Popovers/dropdowns |
| `--popover-foreground` | `222.2 84% 4.9%` | `#0a0f1a` | Texto en popovers |
| `--primary` | `221.2 83.2% 53.3%` | `#2563eb` | Acción principal, azul profesional |
| `--primary-foreground` | `210 40% 98%` | `#f8fafc` | Texto sobre primario |
| `--secondary` | `210 40% 96.1%` | `#f1f5f9` | Superficie secundaria |
| `--secondary-foreground` | `222.2 47.4% 11.2%` | `#1e293b` | Texto sobre secundario |
| `--muted` | `210 40% 96.1%` | `#f1f5f9` | Superficie atenuada |
| `--muted-foreground` | `215.4 16.3% 46.9%` | `#64748b` | Texto atenuado / placeholders |
| `--accent` | `210 40% 96.1%` | `#f1f5f9` | Acento sutil |
| `--accent-foreground` | `222.2 47.4% 11.2%` | `#1e293b` | Texto sobre acento |
| `--destructive` | `0 84.2% 60.2%` | `#ef4444` | Acción destructiva / error |
| `--destructive-foreground` | `210 40% 98%` | `#f8fafc` | Texto sobre destructivo |
| `--border` | `214.3 31.8% 91.4%` | `#e2e8f0` | Bordes e inputs |
| `--input` | `214.3 31.8% 91.4%` | `#e2e8f0` | Bordes de inputs |
| `--ring` | `221.2 83.2% 53.3%` | `#2563eb` | Anillo de foco (igual que primary) |

**Tipografía:**

| Rol | Stack |
|---|---|
| UI / cuerpo | `Inter`, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif |
| Código / datos tabulares | "JetBrains Mono", "Fira Code", Menlo, Monaco, Consolas, monospace |

Ambas familias se cargan desde Google Fonts en `index.html`.

**Espaciado y radios:**

| Token | Valor | Uso |
|---|---|---|
| `--radius` | `0.5rem` (8px) | Radio base de bordes |
| `rounded-lg` | `var(--radius)` = 8px | Tarjetas, modales |
| `rounded-md` | `calc(var(--radius) - 2px)` = 6px | Botones, inputs |
| `rounded-sm` | `calc(var(--radius) - 4px)` = 4px | Elementos pequeños |
| Container padding | `2rem` | Padding lateral del container |
| Container max-width | `1400px` (2xl) | Ancho máximo del contenido |

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
