---
tipo: referencia
proyecto: web
actualizado: 2026-07-10
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
| `Textarea` | `textarea.tsx` | HTML nativo | Campos multilínea (notas, descripciones). |
| `Label` | `label.tsx` | `@radix-ui/react-label` | Peer-disabled-aware. |
| `Form` + `FormField` + `FormItem` + `FormLabel` + `FormControl` + `FormDescription` + `FormMessage` | `form.tsx` | `react-hook-form` + `zod` | Wrapper completo de RHF con validación Zod. `FormControl` usa `Slot` de Radix. |
| `Card` + `CardHeader` + `CardTitle` + `CardDescription` + `CardContent` + `CardFooter` | `card.tsx` | HTML nativo | Superficie con borde, sombra y padding consistente. |
| `Dialog` + `DialogTrigger` + `DialogContent` + `DialogHeader` + `DialogFooter` + `DialogTitle` + `DialogDescription` + `DialogClose` | `dialog.tsx` | `@radix-ui/react-dialog` | Modal con overlay, animaciones, cierre con Escape, foco atrapado. |
| `Sheet` + `SheetTrigger` + `SheetContent` + `SheetHeader` + `SheetFooter` + `SheetTitle` + `SheetDescription` + `SheetClose` | `sheet.tsx` | `@radix-ui/react-dialog` | Drawer lateral — creación/edición sin salir del contexto de lista, y sidebar mobile de `DashboardShell`. |
| `Table` + `TableHeader` + `TableBody` + `TableFooter` + `TableRow` + `TableHead` + `TableCell` + `TableCaption` | `table.tsx` | HTML nativo | Tabla con filas hoverables, cabecera muted. Lógica de sorting/filtering/paginación vía `@tanstack/react-table` (§1.3) — este primitivo solo resuelve el render. |
| `Tabs` + `TabsList` + `TabsTrigger` + `TabsContent` | `tabs.tsx` | `@radix-ui/react-tabs` | Navegación por pestañas (ej. detalle de condominio). |
| `Select` + `SelectTrigger` + `SelectContent` + `SelectItem` + ... | `select.tsx` | `@radix-ui/react-select` | Combobox de opción única. |
| `Checkbox` | `checkbox.tsx` | `@radix-ui/react-checkbox` | Selección booleana en formularios y filas de tabla. |
| `RadioGroup` + `RadioGroupItem` | `radio-group.tsx` | `@radix-ui/react-radio-group` | 2-4 opciones excluyentes en formularios. |
| `Switch` | `switch.tsx` | `@radix-ui/react-switch` | Toggles activo/inactivo. |
| `DropdownMenu` + subcomponentes | `dropdown-menu.tsx` | `@radix-ui/react-dropdown-menu` | Acciones de fila en tablas (editar/eliminar/ver). |
| `Popover` + `PopoverTrigger` + `PopoverContent` | `popover.tsx` | `@radix-ui/react-popover` | Base de paneles de filtro y del date-picker (`Calendar`). |
| `Tooltip` + `TooltipTrigger` + `TooltipContent` + `TooltipProvider` | `tooltip.tsx` | `@radix-ui/react-tooltip` | Label accesible en botones solo-ícono y en ítems de sidebar colapsados. |
| `Avatar` + `AvatarImage` + `AvatarFallback` | `avatar.tsx` | `@radix-ui/react-avatar` | Identificador visual de usuario/contacto. |
| `Separator` | `separator.tsx` | `@radix-ui/react-separator` | Divisor de layout. |
| `Breadcrumb` + subcomponentes | `breadcrumb.tsx` | HTML nativo | Jerarquía de navegación (ej. condominio → torre → unidad). |
| `Calendar` | `calendar.tsx` | `react-day-picker` | Selector de fecha, combinado con `Popover` para el patrón date-picker. |
| `Badge` | `badge.tsx` | HTML nativo | Variantes: default, secondary, destructive, outline, **success, warning, info** (§1.2). |
| `Alert` + `AlertTitle` + `AlertDescription` | `alert.tsx` | HTML nativo | Variantes: default, destructive, **success, warning, info** (§1.2). |
| `Skeleton` | `skeleton.tsx` | HTML nativo | Placeholder de carga. |
| `Toaster` | `sonner.tsx` | `sonner` | Toast notifications con tema del design system. |
| `Command` + `CommandDialog` + `CommandInput` + `CommandList` + `CommandGroup` + `CommandItem` + `CommandEmpty` + `CommandSeparator` + `CommandShortcut` | `command.tsx` | `cmdk` (agregado 2026-07-10, no era dependencia antes) | Command palette de búsqueda de features (`Cmd/Ctrl+K`) — base del componente `CommandMenu` (§1.3). |

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
| `--primary` | `211 55% 23%` | `#1b3a5c` | Acción principal — azul marino de marca (logo Urbania) |
| `--primary-foreground` | `210 40% 98%` | `#f8fafc` | Texto sobre primario |
| `--secondary` | `210 40% 96.1%` | `#f1f5f9` | Superficie secundaria |
| `--secondary-foreground` | `222.2 47.4% 11.2%` | `#1e293b` | Texto sobre secundario |
| `--muted` | `210 40% 96.1%` | `#f1f5f9` | Superficie atenuada |
| `--muted-foreground` | `215.4 16.3% 46.9%` | `#64748b` | Texto atenuado / placeholders |
| `--accent` | `210 40% 96.1%` | `#f1f5f9` | Acento sutil |
| `--accent-foreground` | `222.2 47.4% 11.2%` | `#1e293b` | Texto sobre acento |
| `--destructive` | `0 84.2% 60.2%` | `#ef4444` | Acción destructiva / error |
| `--destructive-foreground` | `210 40% 98%` | `#f8fafc` | Texto sobre destructivo |
| `--success` | `142 71% 45%` | `#22a35e` | Estado positivo (pagado, activo, vigente) |
| `--success-foreground` | `0 0% 100%` | `#ffffff` | Texto sobre success |
| `--warning` | `38 92% 50%` | `#f0a90c` | Estado de atención (pendiente, modificado sin guardar) |
| `--warning-foreground` | `222.2 84% 4.9%` | `#0a0f1a` | Texto sobre warning (fondo claro, requiere texto oscuro) |
| `--info` | `199 89% 48%` | `#0ea1e0` | Estado informativo neutro (ej. "Sistema" en catálogos) |
| `--info-foreground` | `0 0% 100%` | `#ffffff` | Texto sobre info |
| `--accent-brand` | `122 39% 40%` | `#3e8e41` | Acento de marca — verde de logo, uso moderado (panel de `AuthLayout`, ítem de nav activo, avatar de `UserMenu`), nunca en botones primarios |
| `--accent-brand-foreground` | `0 0% 100%` | `#ffffff` | Texto sobre accent-brand |
| `--brand-cta` | `109 51% 31%` | `#367927` | Botón de acción primaria sobre fondos con imagen/glass (ej. submit de `LoginPage`) — verde más oscuro/saturado que `accent-brand`, pensado para leerse sobre foto de fondo |
| `--brand-cta-hover` | `109 51% 37%` | `#408d2e` | Hover de `brand-cta` |
| `--brand-cta-active` | `110 52% 26%` | `#2c6520` | Active/pressed de `brand-cta` |
| `--brand-cta-foreground` | `0 0% 100%` | `#ffffff` | Texto sobre brand-cta |
| `--surface-glass` | `163 84% 20%` | `#065f46` | Base de superficies "vidrio" traslúcidas sobre fondo con imagen (usar con opacidad, ej. `bg-surface-glass/20`) |
| `--surface-glass-border` | `150 60% 90%` | `#d6f5e6` | Borde de superficies glass (usar con opacidad, ej. `border-surface-glass-border/20`) |
| `--surface-glass-foreground` | `152 81% 96%` | `#ecfdf5` | Texto sobre superficies glass |
| `--input-accent-bg` | `204 94% 94%` | `#e0f2fe` | Fondo de inputs sobre superficies glass (usar con opacidad, ej. `bg-input-accent-bg/20`) |
| `--input-accent-border` | `201 94% 86%` | `#bae6fd` | Borde de inputs sobre superficies glass (usar con opacidad, ej. `border-input-accent-border/40`) |
| `--border` | `214.3 31.8% 91.4%` | `#e2e8f0` | Bordes e inputs |
| `--input` | `214.3 31.8% 91.4%` | `#e2e8f0` | Bordes de inputs |
| `--ring` | `211 55% 23%` | `#1b3a5c` | Anillo de foco (igual que primary) |

Los tokens de estado (`success`/`warning`/`info`) y `accent-brand` se agregaron en la pasada de
rediseño visual del 2026-07-10 (ver nota en `_state/BOARD.md`) — reemplazan el uso de clases
Tailwind crudas (`bg-green-50`, `text-amber-600`, etc.) que existía disperso en varias pantallas
antes de esa fecha. Ningún componente nuevo debe volver a usar paletas Tailwind crudas para estado:
siempre uno de estos cuatro tokens semánticos, o se agrega un token nuevo aquí si genuinamente no
alcanza con los existentes.

Los tokens `brand-cta`, `surface-glass` e `input-accent` se agregaron en la sesión de diseño en vivo
del `LoginPage` (2026-07-10) — reemplazan valores sueltos que se habían usado ahí mismo
(`bg-[rgb(54,121,39)]`, `bg-emerald-800/20`, `bg-sky-100/20`, etc.). Son de uso específico para
pantallas con fondo de imagen (login y las 5 pantallas restantes de auth si adoptan el mismo
tratamiento visual); para el resto de la plataforma (superficies sólidas) siguen aplicando los
tokens de la tabla de arriba (`primary`, `accent-brand`, `card`, etc.).

**Colores de marca (pasada del 2026-07-10, segunda mitad — comportamientos globales):**
`--primary` y `--accent-brand` se recalibraron a partir de `logo.jpg` (azul marino `#1b3a5c` del
ícono/wordmark, verde `#3e8e41` de los edificios del ícono y el tagline). `--success` se mantiene
como token de estado independiente aunque quede cerca del verde de marca — son semánticamente
distintos (estado positivo de datos vs. identidad visual) y no se fusionan. Los valores hex son una
estimación desde un JPG comprimido, no una guía de marca formal — si en el futuro existe un manual de
marca con valores exactos (Pantone/hex certificados), esos reemplazan a los de esta tabla.

**Modo oscuro:** implementado (bloque `.dark` en `index.css`, `ThemeProvider` propio en
`src/components/theme-provider.tsx` — receta oficial de shadcn/ui para Vite, sin `next-themes` por
ser Next-only). `--primary`/`--accent-brand` mantienen el mismo hue en `.dark`, solo se ajusta
lightness para contraste AA sobre fondo oscuro. Detalle de comportamiento en §6.

**Personalización en runtime (agregado 2026-07-10, tercera pasada):** `--radius` y `--primary` (+
`--ring`, que siempre sigue a `--primary`) dejaron de ser fijos — `ThemeCustomizer`
(`src/components/theme-customizer.tsx`, dropdown "Personalizar" en el header) los sobreescribe en
runtime vía `document.documentElement.style.setProperty(...)`, persistido en `localStorage`
(`urbania-ui-radius`, `urbania-ui-accent`, `urbania-ui-scale`). Los valores de esta tabla siguen
siendo el **default** de fábrica, no un piso inamovible — cualquier componente que asuma
`--primary` fijo (ej. comparar contra el hex exacto en un test visual) debe leer la CSS variable en
runtime, no hardcodear el HSL de esta tabla. `--radius` también controla el radio de todo lo
derivado (`rounded-lg/md/sm`, §"Espaciado y radios" más abajo). `urbania-ui-scale` es aparte: escala
`font-size` del root (90%–125%) y no toca ninguna CSS variable de color.

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

**Librerías adicionales** (más allá de la base shadcn/ui + Tailwind, decisión única — ningún bloque
de feature las redefine por su cuenta):

| Librería | Uso |
|---|---|
| `@tanstack/react-table` | Capa headless de sorting/filtering/pagination sobre el primitivo visual `Table` (`table.tsx`) — no lo reemplaza |
| `recharts` | Motor de gráficos para widgets de visualización (ej. DASHBOARD) — misma pareja que usa la receta "Charts" de shadcn/ui, compone directo con los tokens HSL de este documento |

### 1.3 Composiciones de layout compartidas (`src/components/` y `src/app/`)

Introducidas en la pasada de rediseño visual del 2026-07-10 (ver nota en `_state/BOARD.md`) para
reemplazar markup duplicado que antes vivía copiado en cada página. Un feature nunca reimplementa
estas piezas — si el patrón no alcanza para un caso nuevo, se extiende el componente compartido, no
se vuelve a duplicar inline.

| Componente | Archivo | Uso |
|---|---|---|
| `AppLayout` | `src/app/AppLayout.tsx` | Única instanciación de `DashboardShell` para todas las rutas privadas — resuelve el usuario (`useUserQuery`) una sola vez y lo expone vía `useAppUser()` (`src/app/app-user-context.ts`) a cualquier página anidada. Ninguna página privada vuelve a envolverse en `DashboardShell` por su cuenta. |
| `DashboardShell` | `src/components/layout/DashboardShell.tsx` | Shell RBAC-aware (sidebar + header) — mecanismo de filtrado sigue siendo el registry de `src/features/dashboard/registry.ts` (`getVisibleSidebar`). Ítem activo de sidebar usa `primary` (no `accent-brand`) para que siga el color de acento elegido en `ThemeCustomizer`. `SidebarNavItem.icon?: LucideIcon` (`features/dashboard/types.ts`) es lo único visible cuando la sidebar está colapsada — reemplaza la inicial del label que se mostraba antes (agregado 2026-07-10, tercera pasada). |
| `CommandMenu` | `src/components/command-menu.tsx` | Búsqueda de features (`Cmd/Ctrl+K`, componente `Command` de §1.1) en el header de `DashboardShell`. La lista de resultados sale de `getVisibleSidebar(user)` — el mismo filtro RBAC de la sidebar — para no listar pantallas que el usuario no puede ver. No hardcodear una lista de features aparte: si un ítem nuevo debe aparecer acá, se registra vía `registerSidebarItem` como cualquier otro. |
| `ThemeCustomizer` | `src/components/theme-customizer.tsx` | Dropdown "Personalizar" en el header — radio de bordes, color de acento y escala de UI. Ver nota de runtime override más arriba (§1.2). Independiente de `ModeToggle` (claro/oscuro). |
| `AuthLayout` | `src/components/auth-layout.tsx` | Shell split-screen de 5 de las 6 pantallas de auth (registro, forgot/reset password, MFA) — panel de marca con `accent-brand` a la izquierda (oculto en mobile), formulario a la derecha. Acepta `contentClassName` para pantallas con contenido más ancho (ej. MFA enroll). **`LoginPage` ya no usa `AuthLayout`** (ver nota abajo). |
| `PageHeader` | `src/components/page-header.tsx` | Header estándar de página: título + descripción + acciones. |
| `EmptyState` | `src/components/empty-state.tsx` | Estado vacío estándar: ícono + mensaje + acción opcional. |
| `LoadingState` | `src/components/loading-state.tsx` | Spinner centrado estándar. |
| `RouteSuspenseFallback` | `src/components/route-fallback.tsx` | Fallback único de `<Suspense>` para todas las rutas lazy-loaded en `App.tsx`. |
| `PAGE_CONTAINER` | `src/lib/layout.ts` | Constante de ancho de contenedor (`max-w-[1400px]`) — antes cada página competía con un ancho distinto (`max-w-4xl`/`5xl`/`6xl`); ahora hay uno solo. |
| `ThemeProvider` | `src/components/theme-provider.tsx` | Contexto de tema claro/oscuro/sistema, persistido en `localStorage` — envuelve todo `App.tsx`. |
| `ModeToggle` | `src/components/mode-toggle.tsx` | Botón de header que cambia el tema (claro/oscuro/sistema) vía `ThemeProvider`. |
| `UserMenu` | `src/components/user-menu.tsx` | Avatar + `DropdownMenu` en el header de `DashboardShell` — nombre/email del usuario y "Cerrar sesión" (`useLogoutMutation`, `features/auth/api/logout.ts`). No existía antes de esta pasada. |

Antes de esta pasada, el router no envolvía las rutas privadas en ningún layout compartido —
`DashboardPage` se envolvía a sí misma en `DashboardShell` y el resto de páginas privadas
(PROPIEDADES) no tenían sidebar/header en absoluto. `AppLayout` corrige eso a nivel de router.

**Divergencia conocida — `LoginPage` (2026-07-10, tercera pasada):** por pedido explícito del
usuario, `LoginPage.tsx` se rediseñó en una sesión de diseño en vivo con un layout propio (fondo con
imagen a pantalla completa, panel central "glass" con logo flotante + formulario) en vez del split-
screen genérico de `AuthLayout`. Es la única de las 6 pantallas de auth así — las otras 5 (registro,
forgot/reset password, MFA) siguen usando `AuthLayout` sin cambios. Consecuencia visible: el logo
usado difiere por pantalla — `LoginPage` y `DashboardShell` usan `/logo.png` (isotipo completo con
wordmark, agregado en esta pasada), mientras `AuthLayout` (y por lo tanto las otras 5 pantallas de
auth) sigue en `/logo.jpg` (recorte cuadrado, de la pasada anterior). No es una inconsistencia
accidental sin registrar — es el estado real tras dos pasadas de diseño con alcances distintos; si
se quiere un logo único en las 6 pantallas de auth, es un ajuste pendiente, no un bug.

## 2. Política de referencia visual — wireframes opcionales por defecto

El panorama de cada feature ya declara pantallas, componentes y estados en su §7 "UI/UX" (ver
`_system/templates/FEATURE_PANORAMA.md`) — esa sección liviana es **obligatoria**, no opcional,
para toda feature que marque Web en §2 de su panorama.

Lo que sigue siendo opcional por defecto es la referencia visual **pesada** (wireframe ASCII,
mockup, imagen): porque los componentes ya están resueltos, la mayoría de las pantallas
(CRUD/formulario estándar) se implementan sin ningún mockup o wireframe previo — el §7.1-§7.4 del
panorama, más los componentes de `src/components/ui/`, alcanzan como especificación completa.

**Excepción — cuándo sí hace falta wireframe:** si una pantalla es genuinamente novedosa (Tier
"Novedosa" en el §7 del panorama — un dashboard con visualización de datos, un layout que no es
CRUD/formulario estándar), el wireframe ASCII y las notas responsive van en §7.5/§7.6 del propio
`PANORAMA.md` de la feature — no en un documento de diseño aparte, y no en `WEB_SCREEN.md`. Es la
excepción, no el flujo por defecto.

## 3. Convención de componentes

- Componentes base viven en `src/components/ui/` (generados vía CLI de shadcn/ui) y
  `src/components/` (composiciones propias sobre esa base) — compartidos por todos los features. Un
  feature nunca reimplementa un componente que ya existe en la librería instalada.
- Antes de construir un componente custom, se verifica si shadcn/ui ya lo resuelve — construir uno
  propio es la excepción, no el primer camino.
- Un componente nuevo que un feature necesita y que es genuinamente reusable se promueve a
  `src/components/` en el mismo bloque que lo origina, no se dejan "para después" copias locales
  que luego divergen.

No hay Storybook ni Figma (ni ninguna otra herramienta de diseño externa) en este proyecto: la
librería de componentes instalada (`src/components/ui/`) más el §7 "UI/UX" del `PANORAMA.md` de
cada feature son la especificación completa de una pantalla. Es un panel administrativo — no hay
identidad de marca pública que justifique ese costo de proceso adicional.

## 4. Accesibilidad

Todo componente interactivo nuevo cumple: foco visible, contraste AA como piso, y navegable por
teclado — esto es parte del DoD visual de cualquier bloque de UI, no un ítem opcional.

## 5. Identidad visual compartida con futuros clientes

Si en el futuro se decide compartir identidad visual entre Web y un cliente adicional, esa decisión
se registra como una fila nueva en [[../shared/SYSTEM_CONTRACT]] §1 y sigue el protocolo de
[[../_system/04_CROSS_PROJECT]] — no se asume compartida por defecto.

## 6. Comportamientos globales

Reglas transversales que ningún feature decide por su cuenta — a diferencia del §7 "UI/UX" de cada
`PANORAMA.md` (que describe pantallas puntuales), esto aplica a la app entera. Agregado en la pasada
del 2026-07-10 (segunda mitad, ver nota en `_state/BOARD.md`) para cerrar el hueco entre "tokens y
componentes ya definidos" y "cómo se comportan entre sí", que hasta esa fecha cada pantalla resolvía
por su cuenta.

- **Modo oscuro:** soportado. Toggle manual de 3 posiciones (claro/oscuro/sistema) vía `ModeToggle`
  en el header, persistido en `localStorage` (`urbania-ui-theme`), default = preferencia del sistema
  al primer acceso. Implementación en `ThemeProvider` (§1.3) — no se usa `next-themes` (es exclusivo
  de Next.js), se sigue la receta oficial de shadcn/ui para Vite con contexto propio.
- **Confirmación de acciones destructivas:** toda acción irreversible (eliminar, no "desactivar" o
  "archivar") pasa por `Dialog` con el nombre del elemento afectado en el texto de confirmación. Ya
  hay un precedente parcial en `DeleteConfirmDialog.tsx` (PROPIEDADES) — esta regla lo generaliza a
  toda pantalla nueva.
- **Toasts (`sonner`, `<Toaster richColors closeButton />` en `App.tsx`):** disparan solo en
  mutaciones (éxito o error de una escritura contra la API) — nunca en lecturas ni en validación de
  formulario, que se muestra inline vía `FormMessage`. Una sola instancia de `Toaster` global, sin
  posición/duración custom por pantalla.
- **Loading:** `Skeleton` para contenido con forma conocida de antemano (filas de tabla, cards) —
  evita el salto de layout. `LoadingState` (spinner centrado) para cargas de página completa o de
  duración indeterminada (ej. mientras se resuelve el usuario en `AppLayout`). Ninguna pantalla nueva
  inventa un tercer patrón de loading.
- **Tablas:** paginación server-side con tamaño de página por defecto de 20 filas (ajustable por
  pantalla si el volumen de datos lo justifica, pero 20 es el default al no especificar nada); 0
  resultados siempre resuelve con `EmptyState`, nunca con una tabla vacía sin mensaje.
- **Formularios:** validación combinada on-blur (feedback temprano por campo) + on-submit (bloqueo
  final), nunca on-change agresivo campo por campo mientras el usuario todavía escribe. Mensaje de
  error siempre vía `FormMessage` debajo del campo, nunca en toast.
- **Responsive / colapso de sidebar:** breakpoint `md` (768px) — por debajo, sidebar pasa a `Sheet`
  (drawer); por encima, sidebar fija colapsable entre 64px (solo íconos, con `Tooltip`) y 256px.
  Comportamiento ya implementado en `DashboardShell`, documentado acá como contrato explícito.
- **Iconografía:** un solo set, `lucide-react` (ya es dependencia de facto vía shadcn/ui). Prohibido
  mezclar con otro set de íconos (Tabler, Heroicons, etc.) en el mismo proyecto.
- **Menú de usuario / logout:** `UserMenu` (Avatar con iniciales + `DropdownMenu`) arriba a la
  derecha del header de `DashboardShell`, junto a `ModeToggle`. Ítems mínimos: nombre + email del
  usuario, separador, "Cerrar sesión". No existía antes de esta pasada — el logout llama a
  `POST /api/v1/auth/logout` (`api/endpoints/AUTH.md`) y limpia el store local sin importar la
  respuesta del servidor.
- **Páginas de error:** 404 y error boundary genérico son mínimos para MVP (mensaje + botón "Volver")
  — no llevan ilustración ni copy elaborado en esta pasada.
- **Command palette (`Cmd/Ctrl+K`):** agregado 2026-07-10 (tercera pasada) — `CommandMenu` (§1.3),
  filtrado por RBAC vía `getVisibleSidebar`. Ya no está "fuera de esta pasada" (contradice lo que
  decía esta misma línea en pasadas anteriores).
- **Personalización visual (radio, acento, escala):** agregado 2026-07-10 (tercera pasada) —
  `ThemeCustomizer` (§1.3), independiente del toggle claro/oscuro. Sigue fuera de alcance: temas
  completos predefinidos (ej. "modo alto contraste") o personalización por organización/usuario
  persistida en backend — esto es un ajuste de UI local en `localStorage`, no una feature de cuentas.
- **Saludo del dashboard:** `"Hola, {nombre}"` fijo (`WelcomeWidget.tsx`, `AppLayout.tsx`) —
  reemplaza el saludo por franja horaria ("Buenos días/tardes/noches") que había antes. Cambio de
  copy simple, no de lógica de datos.
- **Explícitamente fuera de esta pasada (post-MVP):** internacionalización / soporte RTL, temas
  completos predefinidos (más allá de radio/acento/escala), personalización de tema persistida en
  backend. No se agregan hasta que haya una necesidad real, no especulativa.
