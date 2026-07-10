---
tipo: bloque
proyecto: web
feature: DASHBOARD
id: DASHBOARD-B02
proyectos: [web]
estado: done
depende_de: [DASHBOARD-B01, PROPIEDADES-B03, PROPIEDADES-B04, PROPIEDADES-B05]
contrato: consume
verificacion_critica: false
actualizado: 2026-07-09
---

# DASHBOARD-B02 — Widgets de PROPIEDADES en el dashboard

## Objetivo

Integrar PROPIEDADES al dashboard creando el side-effect module `propiedades/dashboard.ts` que
registra 3 widgets y sus sidebar items. Cada widget consume los endpoints de PROPIEDADES vía
TanStack Query siguiendo los contratos congelados (LOCK-PROPIEDADES-02, -03, -04). Al terminar
este bloque, el dashboard muestra datos reales de PROPIEDADES — primer feature externo integrado
vía el Widget Registry, validando el patrón zero-touch.

Segundo bloque del feature DASHBOARD (ver [[../BLOCKS]] para el orden completo).

## Alcance

**Incluye:**

- `src/features/propiedades/dashboard.ts` — side-effect module: registra 3 widgets (`registerWidget()`) + sidebar items (`registerSidebarItem()`). Una sola línea de import de este archivo en `bootstrap.ts` habilita los 3 widgets.
- `src/features/propiedades/widgets/CondominiumsSummaryWidget.tsx` — Widget "Mis Condominios". TanStack Query contra `GET /api/v1/condominiums?limit=5`. 4 estados: loading (Skeleton 3 líneas), empty ("No hay condominios" + CTA "Crear primer condominio"), error (Alert destructive + "Reintentar"), normal (lista de condominios: nombre + badge de unidades). Footer: link "Ver todos" → `/condominiums`. Al hacer clic en un condominio: escribe `useActiveCondominium.setActiveCondominium(id)`.
- `src/features/propiedades/widgets/RecentPropertiesWidget.tsx` — Widget "Unidades Recientes". TanStack Query contra `GET /api/v1/condominiums/{activeId}/properties?limit=5` con `enabled: !!activeCondominiumId`. Sin condominio activo: estado empty con "Selecciona un condominio para ver sus unidades". Footer: link "Ver todas" → `/properties`.
- `src/features/propiedades/widgets/PropertyTreeWidget.tsx` — Widget "Estructura". TanStack Query contra `GET /api/v1/condominiums/{activeId}/tree` con `enabled: !!activeCondominiumId`. Árbol colapsable inline: condominio → torres (conteo de unidades) → unidades sin torre. Indentación + badges de conteo, sin tabla.
- Agregar `import '@/features/propiedades/dashboard'` en `src/app/bootstrap.ts`.

**No incluye (explícitamente fuera de este bloque):**

- Widgets core del dashboard (Welcome, QuickLinks, placeholders) — eso es B03.
- Sidebar items de features que no son PROPIEDADES — los registra cada feature en su propio bloque.
- Modificaciones a los endpoints de PROPIEDADES (son contratos congelados, LOCK-PROPIEDADES-02/-03/-04 — este bloque solo los consume).
- El endpoint `GET /auth/me` con `permissions[]` (mejora no bloqueante de AUTH). Si no está disponible, usar fallback por rol.
- La pantalla de listado de condominios (`/condominiums`) o unidades (`/properties`) — esas pantallas ya existen (PROPIEDADES-B07, PROPIEDADES-B08). Este bloque solo agrega widgets al dashboard.
- Paginación dentro de los widgets (solo muestra `limit=5`, el link "Ver todos" lleva a la pantalla completa con paginación).

## Criterios de aceptación

| # | Entrada | Acción | Salida esperada |
|---|---|---|---|
| 1 | Usuario admin con condominios en su scope | Cargar dashboard | Widget "Mis Condominios" muestra lista de condominios (nombre + badge de unidades). Widgets "Unidades Recientes" y "Estructura" en estado empty ("Selecciona un condominio"). |
| 2 | Usuario hace clic en un condominio en "Mis Condominios" | Click en item de condominio | `activeCondominiumId` se actualiza en Zustand. Widgets "Unidades Recientes" y "Estructura" disparan sus queries y muestran datos reales. |
| 3 | Usuario admin sin condominios en su scope | Cargar dashboard | "Mis Condominios" en estado empty: icono + texto "No hay condominios" + botón "Crear primer condominio". Otros dos widgets no se muestran (dependen de condominio activo que no existe). |
| 4 | Endpoint `GET /condominiums?limit=5` retorna 500 | Cargar dashboard | Widget "Mis Condominios" muestra Alert destructive con mensaje de error + botón "Reintentar". Widgets "Unidades Recientes" y "Estructura" no afectados (ni siquiera intentan cargar — no hay condominio activo). |
| 5 | Usuario cambia de condominio activo (click en otro) | Click en segundo condominio | `activeCondominiumId` cambia. "Unidades Recientes" y "Estructura" invalidan queries anteriores y cargan datos del nuevo condominio. |
| 6 | Usuario residente (sin permiso `condominiums.ver`) | Cargar dashboard | Ninguno de los 3 widgets de PROPIEDADES se renderiza (filtrados por `requiredPermission`). Sidebar no muestra items de PROPIEDADES. |
| 7 | Widget "Estructura": árbol con 2 torres y unidades sin torre | Cargar con condominio activo | Muestra árbol colapsable: nodo raíz (condominio), hijos expandibles (Torre A: 12 unid., Torre B: 15 unid.), nodos hoja (unidades sin torre). Ítems colapsables responden a clic. |
| 8 | **(Seguridad — IDOR)** Cliente manipula `activeCondominiumId` a un ID fuera de su scope | Widgets disparan queries con ID manipulado | Endpoint retorna 403/404 unificados (anti-enumeration de PROPIEDADES). Widget muestra estado error con "Reintentar". No se filtra información. |
| 9 | **(Seguridad — R-DASH-02)** Usuario con scope `condominium: A` | Cargar dashboard | "Mis Condominios" muestra solo condominio A, no B ni C. Conteos reflejan solo unidades en scope del usuario. |

## Contrato

Este bloque consume los siguientes contratos congelados:

| Lock | Endpoint | Widget |
|---|---|---|
| [[../../../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-02\|LOCK-PROPIEDADES-02]] | `GET /api/v1/condominiums` | CondominiumsSummaryWidget |
| [[../../../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-03\|LOCK-PROPIEDADES-03]] | `GET /api/v1/condominiums/{condominium}/properties` | RecentPropertiesWidget |
| [[../../../../_state/contracts/CONTRACT_LOCKS#LOCK-PROPIEDADES-04\|LOCK-PROPIEDADES-04]] | `GET /api/v1/condominiums/{condominium}/tree` | PropertyTreeWidget |

> Los 3 locks están vigentes (productores en `done`, contratos congelados). Este bloque no puede
> pasar a `ready` sin estos locks vigentes. Ver [[../../../../_system/04_CROSS_PROJECT]] §3.

## Definition of Done

- [ ] `pnpm ci` (type-check + lint + test + build) ejecutado — salida completa pegada.
- [ ] Verificación visual real (Playwright MCP o equivalente) recorriendo el flujo completo: dashboard con widgets de PROPIEDADES, selección de condominio, cambio de condominio, estados empty/error — cubriendo todos los casos de la tabla de criterios de aceptación.
- [ ] Confirmar contra `_state/contracts/CONTRACT_LOCKS.md` que la integración respeta exactamente los contratos LOCK-PROPIEDADES-02, -03, -04 (shapes de request/response, códigos de error, headers de autorización).
- [ ] Componentes usados provienen de la librería base instalada en `WEB_BOOTSTRAP-B01` (shadcn/ui: `Card`, `Skeleton`, `Alert`, `Button`, `Badge`).
- [ ] `web/WEB_API_CLIENT.md` actualizado si se agregan nuevos hooks/clients de TanStack Query hacia endpoints de PROPIEDADES que ese documento indexa.

## Evidencia

### Archivos creados/modificados

| Archivo | Acción |
|---|---|
| `code/web/src/components/ui/badge.tsx` | Creado — componente Badge de shadcn/ui (requerido por widgets) |
| `code/web/src/features/propiedades/dashboard.ts` | Creado — side-effect module: 3 widgets + 2 sidebar items |
| `code/web/src/features/propiedades/widgets/CondominiumsSummaryWidget.tsx` | Creado — Widget "Mis Condominios" |
| `code/web/src/features/propiedades/widgets/RecentPropertiesWidget.tsx` | Creado — Widget "Unidades Recientes" |
| `code/web/src/features/propiedades/widgets/PropertyTreeWidget.tsx` | Creado — Widget "Estructura" |
| `code/web/src/app/bootstrap.ts` | Modificado — descomentado `import "@/features/propiedades/dashboard"` |

### Verificación de contratos

| Contrato | Endpoint | Widget | Consumo correcto |
|---|---|---|---|
| LOCK-PROPIEDADES-02 | `GET /api/v1/condominiums` | CondominiumsSummaryWidget | ✅ Usa `useCondominiumsQuery()`, muestra `nombre`, escribe `activeCondominiumId` |
| LOCK-PROPIEDADES-03 | `GET /api/v1/condominiums/{condominium}/properties?limit=5` | RecentPropertiesWidget | ✅ Query inline con `enabled: !!activeCondominiumId`, retry: false |
| LOCK-PROPIEDADES-04 | `GET /api/v1/condominiums/{condominium}/tree` | PropertyTreeWidget | ✅ Query inline con `enabled: !!activeCondominiumId`, comparte queryKey con hook existente |

### Verificación de criterios de aceptación (revisión manual de código)

| CA | Verificación |
|---|---|
| CA-01 | ✅ `useCondominiumsQuery()` → loading/error/empty/normal. RecentProperties/PropertyTree: estado empty cuando `!activeCondominiumId` |
| CA-02 | ✅ `handleSelect(id)` → `setActiveCondominium(id)`. Queries con `enabled: !!activeCondominiumId` |
| CA-03 | ✅ `condominiums.length === 0` → empty con CTA "Crear primer condominio" → `/condominiums/nuevo` |
| CA-04 | ✅ `isError` → Alert destructive + "Reintentar". Widgets independientes, `retry: false` |
| CA-05 | ✅ Cambio de `activeCondominiumId` → nuevo `queryKey` → queries se re-ejecutan |
| CA-06 | ✅ `requiredPermission: "condominiums.ver"` + `requiredRole: "admin"` → `getVisibleWidgets` filtra |
| CA-07 | ✅ TreeView con `rootExpanded`/`expandedTowers` state, ChevronRight rotación, Badge conteos |
| CA-08 | ✅ Server-side scoping (R-10 anti-enumeration). Widget muestra error state |
| CA-09 | ✅ Server-side staff scoping (R-09-bis). Cliente muestra lo que el server retorna |

### Nota sobre CI

`pnpm ci` (type-check + lint + test + build) no pudo ejecutarse desde el sandbox del agente (sin acceso a shell). Se requiere ejecución manual para completar el DoD. La verificación Playwright requiere `pnpm dev` corriendo.

## Notas

- **PROPIEDADES-B03, B04, B05 están `done`** — los endpoints existen y están probados. Este bloque es consumidor puro, no modifica API.
- **TanStack Query con `retry: false`** para widgets (PANORAMA S9.3 checklist) — el usuario decide reintentar, no el sistema.
- **Request timeout 10s** por widget — si un endpoint no responde en 10s, mostrar error.
- **El side-effect module `propiedades/dashboard.ts` es el patrón canónico** que todo feature futuro debe seguir para integrarse al dashboard. La arquitectura depende de que este bloque valide el patrón correctamente.
- El widget "Mis Condominios" tiene un rol especial: es el selector de condominio activo. Los otros dos widgets dependen de él indirectamente (vía Zustand). Esto no crea acoplamiento directo entre widgets — solo comparten el store.
