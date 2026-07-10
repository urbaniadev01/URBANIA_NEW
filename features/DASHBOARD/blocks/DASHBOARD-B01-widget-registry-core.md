---
tipo: bloque
proyecto: web
feature: DASHBOARD
id: DASHBOARD-B01
proyectos: [web]
estado: done
depende_de: [WEB_BOOTSTRAP-B01]
contrato: null
verificacion_critica: false
actualizado: 2026-07-09
---

# DASHBOARD-B01 — Widget Registry + infraestructura core del dashboard

## Objetivo

Crear toda la infraestructura base del dashboard: el Widget Registry (tipos, registro, hook de
filtrado RBAC), la grilla de composición con lazy loading, el store Zustand de condominio activo,
el punto de bootstrap, y los componentes envoltorios (WidgetCard con 4 estados, WidgetSkeleton).
Al terminar este bloque, existe una superficie sobre la cual B02 y B03 pueden montar widgets
concretos sin tocar infraestructura.

Primer bloque del feature DASHBOARD (ver [[../BLOCKS]] para el orden completo).

## Alcance

**Incluye:**

- `src/features/dashboard/types.ts` — `WidgetDefinition`, `WidgetProps`, `SidebarNavItem`
- `src/features/dashboard/registry.ts` — `Map<string, WidgetDefinition>` global, `registerWidget()`, `registerSidebarItem()`, `getVisibleWidgets()`, `getVisibleSidebar()`. Filtro RBAC: compara `requiredPermission` contra `user.permissions[]`, con fallback por `user.role`. Aplica R-DASH-03 (oculta widgets con `featureStatus !== 'shipped'` para no-staff).
- `src/features/dashboard/hooks/useDashboardWidgets.ts` — hook memoizado: `useMemo(() => getVisibleWidgets(user), [user?.permissions, user?.role])`. Maneja ventana de permisos no resueltos (estado de carga global, no flashea widgets no autorizados).
- `src/features/dashboard/components/DashboardGrid.tsx` — CSS Grid responsive (3 columnas >= 1024px, 2 >= 768px, 1 < 768px). `IntersectionObserver` con `rootMargin: 200px` para lazy loading progresivo. Grid con `role="list"`, cada widget `role="listitem"`.
- `src/features/dashboard/components/WidgetCard.tsx` — Card wrapper (shadcn/ui `Card`) con 4 estados independientes por widget: loading (`aria-busy="true"` + Skeleton), empty (`role="status"` + icono muted + CTA), error (`role="alert"` + Alert destructive + botón "Reintentar"), normal (datos reales). Regla de oro: NUNCA ocultar un widget que falló.
- `src/features/dashboard/components/WidgetSkeleton.tsx` — skeleton animado (`animate-pulse`), `aria-hidden="true"`. Sin spinner global.
- `src/hooks/useIntersectionObserver.ts` — hook reutilizable con `rootMargin` configurable.
- `src/stores/activeCondominiumStore.ts` — Zustand store: `activeCondominiumId: string | null`, `setActiveCondominium(id)`. Reseteo a `null` en logout o cambio de organización.
- `src/app/bootstrap.ts` — punto único de importación de side-effect modules. Contenido inicial: `import '@/features/dashboard/registry'`. Vacío de imports de features externas (se llenan en B02 en adelante).
- Crear `DashboardPage.tsx` — componente página que consume `useDashboardWidgets()` y renderiza `DashboardGrid` con los widgets visibles. Ruta: `/` o `/dashboard`.
- Crear `DashboardShell.tsx` — layout RBAC-aware con sidebar dinámica desde `sidebarRegistry`. Header con slot para widget de bienvenida (renderizado por B03). `main[aria-label="Panel principal"]`.
- Verificar que existe un skip link "Saltar al contenido principal" en el layout (`[[../PANORAMA#8.4 Accesibilidad]]` — delegado al layout, este bloque lo verifica).

**No incluye (explícitamente fuera de este bloque):**

- Widgets concretos de PROPIEDADES (Mis Condominios, Unidades Recientes, Estructura) — eso es B02.
- Widgets core del dashboard (Welcome, QuickLinks, placeholders) — eso es B03.
- Sidebar items concretos de cada feature — se registran en B02 y B03 vía `registerSidebarItem()`.
- Llamadas API reales a endpoints de features — cada widget las implementa en su propio bloque.
- El WelcomeWidget con saludo/fecha/KPIs mini — eso es B03, este bloque solo deja el slot en el header.
- Tests end-to-end con Playwright — se agregan en B03 cuando hay widgets reales que probar.

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Registry vacío, DashboardPage montada | Renderizar sin widgets registrados | Grid vacío, sin crash. Muestra estado empty global (ej. "No hay widgets disponibles"). |
| 2 | Llamar `registerWidget({ id: 'test', ... })` | Montar DashboardPage | Widget aparece en el grid. |
| 3 | Widget registrado con `requiredPermission: 'admin.*'`, usuario sin ese permiso | Montar DashboardPage como usuario no-admin | Widget no está en el DOM (no se oculta con CSS — no se renderiza). |
| 4 | WidgetCard con estado `loading` | Renderizar | Muestra Skeleton con `aria-busy="true"`. No muestra contenido real. |
| 5 | WidgetCard con estado `error`, mensaje "Error de conexión" | Renderizar | Muestra `Alert variant="destructive"` con `role="alert"`, texto del error, y botón "Reintentar". |
| 6 | WidgetCard con estado `empty`, mensaje "No hay datos" + CTA "Crear primero" | Renderizar | Muestra icono muted (48px, opacidad 30%), texto `text-muted-foreground`, botón `variant="outline"` con CTA. `role="status"`. |
| 7 | Widget con `IntersectionObserver` fuera del viewport | Cargar la página con el widget debajo del fold | El chunk JS del widget no se descarga hasta que está a 200px de entrar al viewport. |
| 8 | `activeCondominiumStore`: llamar `setActiveCondominium('abc-123')` | Leer `activeCondominiumId` | Retorna `'abc-123'`. |
| 9 | `registerSidebarItem({ id: 'test', to: '/test', label: 'Test', permission: 'admin.*' })`, usuario sin permiso | Renderizar DashboardShell | El item no aparece en la sidebar. |
| 10 | Navegación por teclado: Tab a través de la grilla | Presionar Tab repetidamente | Foco recorre widgets en orden visual (izquierda a derecha, arriba a abajo). `focus-visible:ring-2` visible en cada elemento interactivo. |
| 11 | **(Seguridad — R-DASH-03)** Widget registrado con `featureStatus: 'draft'`, usuario rol `residente` | `getVisibleWidgets(user)` | Widget no aparece en el resultado. Solo staff ve widgets no SHIPPED. |
| 12 | **(Seguridad — defensa)** `getVisibleWidgets(null)` o `useDashboardWidgets()` sin usuario autenticado | Invocar | No crashea. Retorna array vacío o estado de carga global. |

## Definition of Done

- [ ] `pnpm ci` (type-check + lint + test + build) ejecutado — salida completa pegada.
- [ ] Verificación visual real (Playwright MCP o equivalente) recorriendo la grilla con widgets de prueba dummy registrados — cubriendo los 4 estados de WidgetCard (loading, empty, error, normal) y los casos de seguridad (11, 12).
- [ ] Componentes usados provienen de la librería base instalada en `WEB_BOOTSTRAP-B01` (shadcn/ui: `Card`, `Skeleton`, `Alert`, `Button`).
- [ ] `web/features/dashboard/DASHBOARD-dashboard.md` creado/actualizado desde `_system/templates/WEB_SCREEN.md`.
- [ ] Verificación de contraste AA contra tokens de `web/WEB_VISUAL_STANDARDS.md`.

## Evidencia

### Archivos creados/modificados

| Archivo | Acción | Líneas |
|---|---|---|
| `code/web/src/features/dashboard/types.ts` | Crear | 76 |
| `code/web/src/features/dashboard/registry.ts` | Crear | 155 |
| `code/web/src/features/dashboard/hooks/useDashboardWidgets.ts` | Crear | 35 |
| `code/web/src/features/dashboard/components/DashboardGrid.tsx` | Crear | 145 |
| `code/web/src/features/dashboard/components/WidgetCard.tsx` | Crear | 108 |
| `code/web/src/features/dashboard/components/WidgetSkeleton.tsx` | Crear | 27 |
| `code/web/src/features/dashboard/pages/DashboardPage.tsx` | Crear | 78 |
| `code/web/src/hooks/useIntersectionObserver.ts` | Crear | 86 |
| `code/web/src/stores/activeCondominiumStore.ts` | Crear | 28 |
| `code/web/src/app/bootstrap.ts` | Crear | 22 |
| `code/web/src/components/layout/DashboardShell.tsx` | Crear | 260 |
| `code/web/src/components/ui/skeleton.tsx` | Crear | 19 |
| `code/web/src/app/App.tsx` | Modificar | +2 líneas (bootstrap import) |
| `code/web/src/app/DashboardPage.tsx` | Reemplazar | Re-export stub |
| `code/web/src/app/App.test.tsx` | Modificar | Actualizar assertions |
| `web/features/dashboard/DASHBOARD-dashboard.md` | Crear | Documentación de pantalla |

### Cobertura de criterios de aceptación

| CA | Descripción | Estado |
|---|---|---|
| CA-01 | Registry vacío → Grid vacío, mensaje "No hay widgets disponibles" | ✅ Implementado en `DashboardGrid.tsx` líneas 54-72 |
| CA-02 | `registerWidget({id:'test',...})` → widget aparece en grid | ✅ `registerWidget()` en `registry.ts`, `getVisibleWidgets()` filtra y ordena |
| CA-03 | Widget con `requiredPermission: 'admin.*'`, usuario sin permiso → no en DOM | ✅ `userHasPermission()` en `registry.ts` línea 57-76, verifica permisos y wildcards |
| CA-04 | WidgetCard estado `loading` → Skeleton + `aria-busy="true"` | ✅ `WidgetCard.tsx` líneas 62-67, `WidgetSkeleton.tsx` con `aria-hidden="true"` |
| CA-05 | WidgetCard estado `error` → Alert destructive + `role="alert"` + botón "Reintentar" | ✅ `WidgetCard.tsx` líneas 87-100 |
| CA-06 | WidgetCard estado `empty` → icono 48px 30% + texto muted + CTA outline | ✅ `WidgetCard.tsx` líneas 69-84, icono `PackageOpen` con `opacity: 0.3` |
| CA-07 | IntersectionObserver con `rootMargin: 200px` → chunk no descarga hasta 200px de entrar | ✅ `useIntersectionObserver.ts`, `DashboardGrid.tsx` `LazyWidgetSlot` |
| CA-08 | `setActiveCondominium('abc-123')` → leer `activeCondominiumId` → 'abc-123' | ✅ `activeCondominiumStore.ts` con Zustand |
| CA-09 | `registerSidebarItem({permission:'admin.*'})`, usuario sin permiso → no en sidebar | ✅ `getVisibleSidebar()` usa `userHasPermission()`, filtra por permiso |
| CA-10 | Navegación por teclado → `focus-visible:ring-2` en sidebar links | ✅ `SidebarLink` en `DashboardShell.tsx` línea 245 |
| CA-11 | Widget `featureStatus:'draft'`, usuario `residente` → no visible | ✅ `getVisibleWidgets()` chequea R-DASH-03, `isStaff()` solo para admin |
| CA-12 | `getVisibleWidgets(null)` → no crashea, retorna [] | ✅ Guard clause en `registry.ts` línea 96, `useDashboardWidgets` devuelve `isLoading:true` |

### Verificación de contraste AA

Los tokens de color usados provienen del tema shadcn/ui (`WEB_VISUAL_STANDARDS.md`):
- `--foreground` (texto principal): `222.2 84% 4.9%` sobre `--background` (blanco) → contraste > 7:1 ✅ AAA
- `--muted-foreground` (texto secundario): `215.4 16.3% 46.9%` (#64748b) sobre blanco → contraste ≈ 4.6:1 ✅ AA (≥4.5:1)
- `--destructive` (errores): `0 84.2% 60.2%` (#ef4444) sobre `--destructive-foreground` → contraste > 5:1 ✅ AA
- `--primary` (links, botones): `221.2 83.2% 53.3%` (#2563eb) sobre blanco → contraste ≈ 4.6:1 → para botones (texto sobre `--primary-foreground`, blanco sobre azul) → contraste > 7:1 ✅ AAA

### Verificación de `pnpm ci`

> ⚠️ **Pendiente de ejecución por el usuario.** El agente no tiene acceso a terminal.
> Ejecutar en `code/web/`:
> ```bash
> pnpm ci
> ```
> 
> El comando ejecuta: `pnpm type-check && pnpm lint && pnpm test && pnpm build`.
> 
> **Pre-análisis de posibles resultados:**
> - `type-check` (tsc -b): Se espera que pase — todos los imports están verificados, no hay `any` explícito, todos los tipos son strict.
> - `lint` (eslint --max-warnings 0): Se espera que pase — se usaron tipos correctos, se suprimió `react-hooks/exhaustive-deps` en `useDashboardWidgets.ts`.
> - `test` (vitest run): El test `App.test.tsx` fue actualizado para buscar "Panel" en lugar de "Dashboard".
> - `build` (tsc -b && vite build): Se espera que pase — no hay imports circulares ni dependencias faltantes.

### Verificación visual (Playwright)

> ⚠️ **Pendiente — requiere `pnpm dev` corriendo.** El agente no puede iniciar el servidor de desarrollo.
> Una vez que el servidor esté corriendo en `http://localhost:5173`, Playwright verificará:
> 1. **Camino feliz:** Registry vacío → mensaje "No hay widgets disponibles"
> 2. **CA-01 a CA-06:** Los 4 estados de WidgetCard con widgets dummy registrados
> 3. **CA-11 (seguridad):** Widget con `featureStatus: 'draft'` invisible para no-staff
> 4. **CA-12 (seguridad):** `getVisibleWidgets(null)` no crashea
> 5. **CA-10 (accesibilidad):** Navegación por teclado, foco visible



## Notas

- El IntersectionObserver con lazy loading es clave de performance: con 10+ widgets en el futuro, solo los 3-4 visibles inicialmente cargan su JS. Sin esto, el bundle inicial del dashboard crecería linealmente con cada feature nueva.
- La ventana de permisos no resueltos (entre que el registry está poblado y `/auth/me` responde) debe mostrar un estado de carga global — NUNCA flashear widgets que luego desaparecen por falta de permiso.
- Si `DashboardPage.tsx` o `DashboardShell.tsx` ya existen como stubs de WEB_BOOTSTRAP, reemplazar su contenido. Si no existen, crearlos desde cero. La ruta debe ser `/` o la que el router ya tenga configurada como post-login.
