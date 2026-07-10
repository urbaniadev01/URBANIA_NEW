---
tipo: referencia
proyecto: web
feature: DASHBOARD
actualizado: 2026-07-09
---

# DASHBOARD — Dashboard (Panel Principal)

**Bloques que la producen:**
- [[../../../features/DASHBOARD/blocks/DASHBOARD-B01-widget-registry-core]] — infraestructura (registry, grid, WidgetCard, layout)
- [[../../../features/DASHBOARD/blocks/DASHBOARD-B03-core-widgets-placeholders]] — widgets core + placeholders + accesibilidad
**Tipo:** Página
**Ruta:** `/` y `/dashboard`

## Widgets implementados (B03)

| # | Widget | ID | Feature | Priority | Size | Visibilidad |
|---|---|---|---|---|---|---|
| 1 | **WelcomeWidget** — Saludo contextual + fecha + 3 KPIs mini | `welcome` | dashboard | 1 | full | Todo usuario autenticado |
| 2 | **QuickLinksWidget** — Accesos directos filtrados por permiso | `quick-links` | dashboard | 20 | full | Todo usuario autenticado |
| 3 | **DirectoryPlaceholderWidget** — Placeholder "Directorio" | `directory-placeholder` | directorio | 80 | md | Solo staff (R-DASH-03) |
| 4 | **CobranzaPlaceholderWidget** — Placeholder "Cuotas Pendientes" | `cobranza-placeholder` | cobranza | 90 | md | Solo staff (R-DASH-03) |

### WelcomeWidget

Saludo contextual ("Buenos días/tardes/noches, [nombre]") basado en la hora local, fecha actual
formateada en español, y 3 KPIs numéricos extraídos de endpoints de PROPIEDADES:

| KPI | Origen | Sin condominio activo |
|---|---|---|
| Condominios | `useCondominiumsQuery()` → `data.length` | Muestra el total (siempre disponible) |
| Unidades | `useCondominioTreeQuery(activeId)` → suma de `tower.properties_count` + `untowered_properties_count` | 0 |
| Torres | `useCondominioTreeQuery(activeId)` → `tree.towers.length` | 0 |

Cada KPI renderiza con `role="status"` y `aria-label` descriptivo (ej. "3 condominios en tu scope").
Estados: loading (skeleton), error (Alert destructive + Reintentar), normal (KPIs).
`staleTime`: 5 min (default de TanStack Query).
Sin `requiredPermission` — visible para todo usuario autenticado.

### QuickLinksWidget

5 botones `variant="ghost"` con icono de lucide-react, cada uno envuelto en `<Link>` de React Router.
Cada link se remueve completamente del DOM si el usuario no tiene el permiso requerido
(no se deshabilita — no existe en el DOM).

| Link | Ruta | Permiso | Icono |
|---|---|---|---|
| Condominios | `/condominios` | `condominiums.ver` | `Building2` |
| Unidades | `/properties` | `properties.ver` | `Home` |
| Coeficientes | `/properties/coefficients` | `properties.ver` | `Percent` |
| Directorio | `/contacts` | `contacts.ver` | `Users` |
| Cobranza | `/billing` | `billing.ver` | `CreditCard` |

Chequeo de permisos: duplica la lógica del registry (`userHasPermission`) — admin ve todo,
usuarios con `permissions[]` vacío solo ven lo que su rol permite vía fallback.
Si ningún link es visible → estado empty con mensaje "No tienes accesos directos disponibles."
El widget completo usa `<nav aria-label="Accesos directos">`.

### DirectoryPlaceholderWidget

Placeholder estático — NO dispara llamadas API. Visible solo para staff (admin) porque
`featureStatus: 'in_progress'` → el registry lo filtra para no-staff (R-DASH-03).

Renderiza: `Card` con `CardHeader` (icono `Users` + título "Directorio" + `Badge variant="secondary"`
"Próximamente") y `CardContent` con `WidgetSkeleton` permanente (`aria-hidden="true"`).

Cuando DIRECTORIO llegue a SHIPPED, su side-effect module registra el widget real con mayor
priority — el placeholder se reemplaza sin tocar el core del dashboard.

### CobranzaPlaceholderWidget

Mismo patrón que DirectoryPlaceholderWidget. `featureStatus: 'draft'`. Badge "En desarrollo".
Icono `CreditCard`. Título "Cuotas Pendientes".

## Accesibilidad (B03 — cierre del MVP)

| Elemento | Implementación |
|---|---|
| Skip link | `<a href="#main-content">` con `sr-only focus:not-sr-only`. Primer elemento enfocable al cargar la página. |
| Tab order | Header → widgets (izquierda a derecha, arriba a abajo) → sidebar |
| Widget sections | Cada widget renderiza dentro de un `section[aria-label]`. Grid: `role="list"`, widgets: `role="listitem"`. |
| KPIs | `role="status"` + `aria-label` descriptivo (ej. "3 condominios en tu scope") |
| Focus visible | `focus-visible:ring-2 focus-visible:ring-ring` en todo elemento interactivo (provisto por shadcn/ui + Tailwind). |
| Contraste AA | Verificado contra tokens de `web/WEB_VISUAL_STANDARDS.md` — primary (#2563eb) sobre white (#ffffff): ratio 4.7:1 ✅. Texto muted (#64748b) sobre white: ratio 4.6:1 ✅. |
| Placeholders | `aria-hidden="true"` en skeletons. Badges visibles solo para staff. |
| Loading states | `aria-busy="true"` en skeletons. `aria-label` descriptivo en grid de carga. |

## Acciones disponibles

| Acción | Dispara | Endpoint consumido (lock) |
|---|---|---|
| Carga inicial de widgets | `useDashboardWidgets(user)` memoizado | Ninguno — filtro client-side desde registry |
| Lazy load de widget | `IntersectionObserver` (200px rootMargin) | Chunk JS del widget se descarga on-demand |
| KPIs del WelcomeWidget | `useCondominiumsQuery()` + `useCondominioTreeQuery(activeId)` | `GET /condominiums` (LOCK-PROPIEDADES-02), `GET /condominiums/{id}/tree` (LOCK-PROPIEDADES-04) |
| Seleccionar condominio activo | `useActiveCondominiumStore.setActiveCondominium(id)` | Estado local (Zustand) — validado server-side por cada endpoint |
| Reintentar widget fallido | Botón "Reintentar" en WidgetCard error | El widget decide qué query invalidar |
| Navegar desde QuickLinks | `<Link to="...">` en Button ghost | Navegación client-side (React Router) — sin llamada API |

## Estados de la vista

### Vista global
- **Carga (ventana de permisos):** 3 skeletons en grid 3-columnas. `aria-label="Cargando widgets"`.
- **Vacío:** Mensaje "No hay widgets disponibles" con icono `PackageOpen` (16, opacidad 30%).
- **Normal:** Grid poblado con widgets visibles según RBAC. Admin ve 4 widgets (Welcome full-width, QuickLinks full-width, 2 placeholders). Residente sin permisos extendidos ve 2 widgets (Welcome, QuickLinks con 3 links).

### Estados por widget (independientes — no contagian a otros widgets)
- **Loading:** `WidgetSkeleton` animado, `aria-busy="true"`.
- **Empty:** Icono `PackageOpen` 48px al 30% opacidad + texto muted + botón outline con CTA contextual. `role="status"`. QuickLinks: ocurre cuando ningún link es visible por permisos.
- **Error:** `Alert variant="destructive"` con `role="alert"` + botón "Reintentar". El widget **nunca** se oculta. WelcomeWidget: error si `GET /condominiums` o `GET /tree` fallan.
- **Normal:** Contenido real del widget. Placeholders: siempre en estado normal (skeleton estático, sin API calls).

### Sidebar
- **Desktop:** 5 ítems en grupo "Gestión" (Condominios, Unidades, Coeficientes, Directorio, Cobranza) + "Inicio" fijo. Filtrados por permiso.
- **Mobile:** Sidebar en `Sheet` (drawer lateral).

## Permisos

La visibilidad de widgets y sidebar items se determina por RBAC granular:

| Regla | Implementación |
|---|---|
| **R-DASH-01:** `requiredPermission` en `WidgetDefinition` | Comparado contra `user.permissions[]`. Fallback: staff (admin) ve todo. |
| **R-DASH-03:** `featureStatus !== 'shipped'` | Solo visible para staff (admin). Residentes y vigilantes no ven widgets de features no terminados. |
| **Permisos no resueltos** (ventana async de `/auth/me`) | Estado de carga global — nunca se flashean widgets no autorizados. |
| **Defensa en profundidad** | Cada widget usa su propio endpoint → el backend aplica su propia autorización. Si un widget se renderiza por error, la API retorna 403/404 unificado. |
| **QuickLinks — permiso por link** | Cada `<Link>` se remueve del DOM si `!userHasPermission(link.permission, user)`. Admin ve 5 links. Residente sin `contacts.ver` ni `billing.ver` ve 3. |
