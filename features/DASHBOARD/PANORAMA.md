---
tipo: feature
proyecto: shared
feature: DASHBOARD
estado_diseño: approved
actualizado: 2026-07-09
design_council: completado
---

> **Design Council completado (2026-07-09).** Este documento es el output consolidado del protocolo
> de 3 fases (divergencia con `design-architect`, `design-ux`, `design-security`; peer review
> anonimizada; sintesis). Los 5 puntos abiertos del draft original estan resueltos. Ver S10 para
> el veredicto completo del council.
>
> Mientras `estado_diseno` no cambie a `approved` por el gate humano de `_system/03_LIFECYCLE.md` S3,
> ningun agente crea `BLOCKS.md` ni tarjetas de bloque para esta feature.

# Feature: DASHBOARD

## 1. Resumen y motivacion

Pantalla principal del panel (post-login), tipo home de un CRM: muestra informacion y accesos de
utilidad para el usuario segun su rol (resumen de lo relevante -- pendientes, alertas, accesos
directos) y enlaces de navegacion hacia los demas modulos (`PROPIEDADES`, `DIRECTORIO`, `COBRANZA`
cuando exista, etc.). Sin esta pantalla, el usuario aterriza en un panel sin punto de partida claro
mas alla del menu lateral.

El requisito explicito del usuario es que sea **facil de ampliar en el futuro**: cada feature nueva
debera poder aportar su propio widget/tarjeta al dashboard sin forzar un rediseno completo de esta
pantalla. Esto se modela como una **arquitectura de composicion con Widget Registry** (ver S7), no
como una lista fija de secciones.

### Por que existe ahora

El usuario solicito priorizar DASHBOARD para tener una pantalla sobre la cual hacer pruebas de
usuario a medida que se construyen features. Esto no compromete orden de fases del plan general,
pero establece que DASHBOARD se construye en paralelo con DIRECTORIO, usando PROPIEDADES (ya
SHIPPED) como unica fuente de datos reales en V1.

## 2. Capas afectadas

- [x] **Web** -- la pantalla principal en si. Feature puramente de frontend para V1.
- [x] **API** -- mejora menor NO BLOQUEANTE: extender `GET /api/v1/auth/me` para incluir
      `permissions: string[]` en la respuesta, filtrado solo a permisos de features visibles en
      el dashboard (prefijos `propiedades.`, `directorio.`, `cobranza.`). Sin esta mejora, el
      dashboard opera con fallback por rol. Ver S6.
- [ ] **App** -- diferido, mismo criterio que el resto del vault (`app/APP_DEFERRED.md`).

**Decision de arquitectura de endpoints (unAnime):** CERO endpoints nuevos de agregacion. El
dashboard compone sus datos 100% client-side via TanStack Query, llamando a los endpoints que
cada feature ya expone. Esto:

- Respeta el aislamiento de bounded contexts de la API (`api/API_ARCHITECTURE.md` S2: "no se
  importan entre si directamente").
- Hereda automaticamente la autorizacion de cada feature -- no duplica reglas de RBAC.
- Permite que cada feature nueva agregue su widget sin modificar la API ni el core del dashboard.

## 3. Relacion con otras features

| Feature | Estado | Relacion con DASHBOARD | Widget en V1 |
|---|---|---|---|
| [[../AUTH/PANORAMA|AUTH]] | SHIPPED | RBAC determina visibilidad de widgets. Mejora pendiente: `GET /auth/me` con `permissions[]`. | -- |
| [[../PROPIEDADES/PANORAMA|PROPIEDADES]] | SHIPPED | Fuente de datos reales. 3 widgets consumen sus endpoints. | Mis Condominios, Unidades recientes, Estructura |
| [[../DIRECTORIO/PANORAMA|DIRECTORIO]] | B01 ready, resto backlog | Fuente de datos futura. Widget placeholder. | Placeholder "Proximamente" (visible solo para staff) |
| [[../COBRANZA/PANORAMA|COBRANZA]] | draft | Fuente de datos futura. Widget placeholder. | Placeholder "En desarrollo" (visible solo para staff) |
| Features futuras | -- | Cada una registra su widget via side-effect module. Una linea de import en `bootstrap.ts`. | Dinamico |

- **Dependencia fuerte:** AUTH (sin RBAC, el dashboard no puede filtrar widgets).
- **Dependencia de datos:** PROPIEDADES (unica fuente de datos reales en V1).
- **Dependencias deferidas:** DIRECTORIO y COBRANZA aportan widgets cuando sus features esten SHIPPED.

## 4. Modelo de datos

### Veredicto del Design Council: CERO tablas nuevas.

El Widget Registry es 100% codigo TypeScript en el frontend. No se crea tabla `dashboard_widgets`
ni ninguna entidad nueva en base de datos.

**Justificacion (principio "Un dato, un dueno" -- `01_PRINCIPLES.md` S1):** Los widgets disponibles
son una consecuencia directa del codigo de cada feature -- no existe un widget de COBRANZA sin el
codigo del feature COBRANZA. Duplicar esa verdad en una tabla de BD crearia dos fuentes que deben
mantenerse sincronizadas.

**La verdad de "quien puede ver que" ya existe** en las tablas de RBAC (`permissions`,
`role_permissions`, `role_assignments`). El dashboard no introduce un sistema de permisos nuevo --
consume el existente.

**Preferencias de usuario (V2, futuro):** Si se requiere que cada usuario reordene u oculte widgets,
se agrega una tabla `user_dashboard_preferences (user_id, widget_key, visible, sort_order)` como
feature independiente. Esta tabla seria estrictamente de visibilidad/configuracion, nunca de
comportamiento (el `widget_key` referencia un widget que YA EXISTE en codigo, validado contra un
enum cerrado en backend).

### Entidades del dominio (todas existentes, ninguna nueva)

DASHBOARD no introduce entidades de dominio propias. Opera como **superficie de composicion** que
consume entidades de otros bounded contexts via sus endpoints:

| Entidad consumida | Feature duena | Endpoint |
|---|---|---|
| `Condominium`, `Tower`, `Property` | PROPIEDADES | `GET /condominiums`, `GET /condominiums/{id}/tree`, `GET /condominiums/{id}/properties` |
| `Contact`, `OccupantType`, `PropertyOccupant` | DIRECTORIO | `GET /contacts`, `GET /properties/{id}/occupants` (futuro) |
| `BillingPeriod`, `Invoice` | COBRANZA | `GET /billing-periods/active/summary`, `GET /me/invoices` (futuro) |

## 5. Reglas de negocio

### R-DASH-01 -- Visibilidad por RBAC granular

Un usuario solo ve widgets y accesos directos de modulos para los que tiene permiso via RBAC.
Implementacion:

- **Filtro primario:** `requiredPermission` en el `WidgetDefinition`. El frontend compara contra
  `permissions[]` obtenido de `GET /auth/me` (o usa fallback por rol mientras la mejora de AUTH
  no esta desplegada).
- **Filtro secundario (defensa en profundidad):** el endpoint de cada feature aplica su propia
  autorizacion. Si un widget se renderiza por error de filtrado client-side, la llamada API
  retorna 403/404 unificados (anti-enumeration ya implementado en PROPIEDADES) y el widget
  muestra su estado de error.
- **Sin datos reales para features no SHIPPED:** un widget con `featureStatus !== 'shipped'`
  jamas dispara una llamada API. Solo muestra placeholder estatico.

### R-DASH-02 -- Conteos scope-bound

Todo conteo numerico en un widget debe reflejar **el scope del usuario**, no el total de la
organizacion. Ejemplos:

- Un admin de scope `condominium: A`: el widget "Unidades recientes" muestra solo unidades del
  condominio A, no de B o C.
- Un residente (scope derivado de `property_occupants`): el widget "Mi unidad" muestra conteo 1
  (su unidad), no el total del condominio.
- Los labels deben reflejar el scope: "Tus condominios" (scope-bound), no "Condominios de la
  organizacion" (total).

Esta regla se implementa en el backend (cada endpoint ya filtra por scope; el dashboard no
agrega datos propios, solo los muestra). Es responsabilidad del endpoint de cada feature aplicar
el filtro correcto en SQL (`WHERE ... IN scope_del_usuario`), no en memoria.

### R-DASH-03 -- Placeholders visibles solo para staff

Los widgets de features no SHIPPED (`featureStatus: 'in_progress'` o `'draft'`) solo muestran
placeholder para roles de staff (admin, administrador). Para residentes y vigilantes, estos
widgets no se renderizan en absoluto -- no se revela el roadmap de producto a usuarios finales.

## 6. Widgets del MVP y endpoints

### Widget 1: Mis Condominios (PROPIEDADES -- SHIPPED)

| Atributo | Valor |
|---|---|
| **ID** | `condominiums-summary` |
| **Permiso** | `condominiums.ver` |
| **Scope** | `organization`, `condominium` |
| **Endpoint** | `GET /api/v1/condominiums?limit=5` |
| **Estados** | Normal: lista de condominios (nombre + badge de unidades). Empty: "No hay condominios" + CTA "Crear primer condominio". Error: Alert destructive + "Reintentar". Loading: Skeleton con 3 lineas. |
| **Footer** | Link "Ver todos" -> /condominiums |
| **Rol especial** | Este widget es el que **selecciona el condominio activo** para los widgets dependientes (escribe en Zustand `useActiveCondominium`). |

### Widget 2: Unidades Recientes (PROPIEDADES -- SHIPPED)

| Atributo | Valor |
|---|---|
| **ID** | `recent-properties` |
| **Permiso** | `properties.ver` |
| **Scope** | `organization`, `condominium`, `tower` |
| **Endpoint** | `GET /api/v1/condominiums/{activeId}/properties?limit=5` |
| **Dependencia** | Requiere `useActiveCondominium` seteado. Si no hay condominio seleccionado: estado empty con "Selecciona un condominio para ver sus unidades". |
| **Footer** | Link "Ver todas" -> /properties |

### Widget 3: Estructura (PROPIEDADES -- SHIPPED)

| Atributo | Valor |
|---|---|
| **ID** | `property-tree` |
| **Permiso** | `condominiums.ver` |
| **Scope** | `organization`, `condominium` |
| **Endpoint** | `GET /api/v1/condominiums/{activeId}/tree` |
| **Visualizacion** | Arbol colapsable inline: condominio -> torres (conteo de unidades) -> unidades sin torre. Indentacion + badges de conteo, sin tabla. |
| **Dependencia** | Requiere `useActiveCondominium` seteado. |

### Widget 4: Accesos Directos (CORE del dashboard)

| Atributo | Valor |
|---|---|
| **ID** | `quick-links` |
| **Permiso** | Ninguno (visible para todo usuario autenticado) |
| **Scope** | N/A |
| **Datos** | Ninguno -- estatico. Lista de `Button variant="ghost"` con icono + texto. |
| **Filtrado** | Cada link se oculta (no se deshabilita -- se remueve del DOM) si el usuario no tiene el permiso del modulo. Usa los mismos `permissions[]` que el filtro de widgets. |
| **Links V1** | Condominios (`/condominiums`), Unidades (`/properties`), Coeficientes (`/properties/coefficients`), Directorio (`/contacts`), Cobranza (`/billing`) -- los ultimos dos visibles solo si el usuario tiene permiso. |

### Widget 5: Directorio (placeholder -- DIRECTORIO en construccion)

| Atributo | Valor |
|---|---|
| **ID** | `directory-summary` |
| **Permiso** | `contacts.ver` |
| **featureStatus** | `in_progress` |
| **Visibilidad** | Solo roles staff (R-DASH-03). Muestra `Card` con icono `Users`, titulo "Directorio", badge "Proximamente", y skeleton permanente. No dispara llamadas API. |
| **Transicion** | Cuando DIRECTORIO llegue a SHIPPED, este placeholder se reemplaza por el widget real sin modificar el core del dashboard -- el feature DIRECTORIO registra su widget via side-effect module. |

### Widget 6: Cuotas Pendientes (placeholder -- COBRANZA en draft)

| Atributo | Valor |
|---|---|
| **ID** | `pending-invoices` |
| **Permiso** | `billing.ver` |
| **featureStatus** | `draft` |
| **Visibilidad** | Solo roles staff (R-DASH-03). Badge "En desarrollo". Mismo patron que Widget 5. |

### Widget futuro: Mi Unidad (Residente -- post-DIRECTORIO)

| Atributo | Valor |
|---|---|
| **ID** | `my-unit` |
| **Permiso** | Ninguno -- deriva de `property_occupants` del contacto asociado al usuario |
| **Scope** | `unit` (derivado) |
| **Endpoint** | `GET /api/v1/me/properties` (requiere que DIRECTORIO-B01 este done, porque `property_occupants` es quien vincula usuario -> unidad) |
| **Nota** | Este widget NO es parte del MVP (depende de DIRECTORIO). Se disena ahora para que la arquitectura no lo bloquee -- pero su implementacion espera a que el endpoint exista. |

## 7. Arquitectura de extensibilidad -- Widget Registry

### 7.1 API del Registry

```typescript
// src/features/dashboard/types.ts

export interface WidgetDefinition {
  id: string;
  feature: string;
  title: string;
  description?: string;
  component: React.LazyExoticComponent<React.ComponentType<WidgetProps>>;
  requiredPermission?: string;
  requiredRole?: 'admin' | 'user';
  priority: number;
  size: 'sm' | 'md' | 'lg' | 'full';
  defaultVisible: boolean;
  featureStatus: 'shipped' | 'in_progress' | 'draft';
}

export interface WidgetProps {
  user: AuthUser;
}

export interface SidebarNavItem {
  id: string;
  to: string;
  label: string;
  permission?: string;
  group?: string;
  children?: Omit<SidebarNavItem, 'children'>[];
}
```

### 7.2 Registro (side-effect module por feature)

```typescript
// src/features/dashboard/registry.ts
const widgetRegistry = new Map<string, WidgetDefinition>();
const sidebarRegistry = new Map<string, SidebarNavItem>();

export function registerWidget(def: WidgetDefinition): void { /* ... */ }
export function registerSidebarItem(item: SidebarNavItem): void { /* ... */ }
export function getVisibleWidgets(user: AuthUser): WidgetDefinition[] { /* ... */ }
export function getVisibleSidebar(user: AuthUser): SidebarNavItem[] { /* ... */ }
```

### 7.3 Como registra un feature sus widgets

```typescript
// src/features/propiedades/dashboard.ts
import { lazy } from 'react';
import { registerWidget, registerSidebarItem } from '@/features/dashboard/registry';

registerWidget({
  id: 'condominiums-summary',
  feature: 'propiedades',
  title: 'Mis Condominios',
  component: lazy(() => import('./widgets/CondominiumsSummaryWidget')),
  requiredPermission: 'condominiums.ver',
  priority: 10,
  size: 'md',
  defaultVisible: true,
  featureStatus: 'shipped',
});

registerSidebarItem({
  id: 'propiedades',
  to: '/condominiums',
  label: 'Condominios',
  permission: 'condominiums.ver',
  group: 'Gestion',
});
```

### 7.4 Bootstrap -- punto unico de importacion

```typescript
// src/app/bootstrap.ts
import '@/features/dashboard/registry';
import '@/features/propiedades/dashboard';
// import '@/features/directorio/dashboard';    // Cuando DIRECTORIO este SHIPPED
// import '@/features/cobranza/dashboard';       // Cuando COBRANZA este SHIPPED
```

**Garantia zero-touch:** Agregar un feature nuevo al dashboard requiere exactamente UNA linea de
import en `bootstrap.ts`. Ningun archivo del core del dashboard se modifica.

### 7.5 Lazy loading y code-splitting

- Cada widget usa `React.lazy()` -> chunk independiente (2-8 KB c/u).
- `IntersectionObserver` con `rootMargin: 200px` para lazy loading progresivo: widgets fuera del
  viewport no cargan su JS ni disparan queries hasta que estan a 200px de entrar.
- Core del dashboard (registry + grid + shell): ~11 KB. No crece con mas features.
- Filtro memoizado: `useMemo(() => getVisibleWidgets(user), [user?.permissions, user?.role])`.

## 8. Layout y experiencia de usuario

### 8.1 Estructura visual

```
+-- DashboardShell (RBAC-aware, sidebar dinamica desde registry) --+
| +-- Header ------------------------------------------------------+
| | "Buenos dias, [nombre]" | Fecha | KPIs mini (condominios,     |
| | unidades, torres en el scope)                                  |
| +-- DashboardGrid (CSS Grid 3-2-1 columns) ---------------------+
| | +-- WidgetCard --+ +-- WidgetCard --+ +-- WidgetCard --------+ |
| | | Mis Condominios| | Unidades       | | Estructura           | |
| | | 3 activos      | | 47 total       | | Torre A: 12 unid     | |
| | | [Ver todos ->] | | [Ver todas ->] | | Torre B: 15 unid     | |
| | +----------------+ +----------------+ +----------------------+ |
| | +-- WidgetCard (full-width) --+ +-- WidgetCard ---------------+ |
| | | Accesos Directos            | | Directorio                  | |
| | | [Condominios] [Unidades]    | | [Proximamente]              | |
| | | [Coeficientes] [Directorio] | +-----------------------------+ |
| | +-----------------------------+                                 |
| +----------------------------------------------------------------+
```

### 8.2 Cuatro estados visuales por widget (independientes)

Cada widget maneja sus estados sin contagiar a los demas. Si un endpoint falla,
solo ese widget muestra error -- los demas siguen funcionando.

| Estado | Implementacion |
|---|---|
| **Loading** | `Skeleton` (shadcn/ui) con barras animadas `animate-pulse`. El contenedor tiene `aria-busy="true"`. No se usa spinner global. |
| **Empty** | Icono muted (48px, opacidad 30%) + texto `text-muted-foreground` + `Button variant="outline"` con CTA contextual. Tiene `role="status"`. |
| **Error** | `Alert variant="destructive"` + mensaje + boton "Reintentar" (`queryClient.invalidateQueries`). Tiene `role="alert"`. |
| **Normal** | Datos reales. Cada item cliqueable tiene `hover:bg-accent`, `cursor-pointer`, `tabIndex={0}`. |

**Regla de oro (consenso Design Council):** NUNCA ocultar un widget que fallo. Mostrar el error
con opcion de reintentar. Ocultar errores es mentir por omision -- en un sistema administrativo
con consecuencias financieras, un widget silenciosamente ausente puede causar mora acumulada.

### 8.3 Zustand: `useActiveCondominium`

Estado compartido cross-widget que resuelve "que condominio estoy mirando":

```typescript
// src/stores/activeCondominiumStore.ts
interface ActiveCondominiumState {
  activeCondominiumId: string | null;
  setActiveCondominium: (id: string) => void;
}
```

- **Quien escribe:** Widget "Mis Condominios" al hacer clic en un condominio.
- **Quienes leen:** Widgets "Unidades recientes" y "Estructura". Sus queries de TanStack Query
  usan `enabled: !!activeCondominiumId`.
- **Reseteo:** Al cambiar de organizacion o al logout -> `null`.
- **Validacion server-side:** El endpoint de cada feature valida que el `condominium_id` (venga
  de path o query param) pertenece al scope del usuario. Nunca se confia en el valor del cliente
  (mitigacion de IDOR -- ver S9).

### 8.4 Accesibilidad

| Requisito | Implementacion |
|---|---|
| Navegacion por teclado | Tab order: header -> widgets izquierda a derecha -> sidebar. Cada widget es `section[aria-label]`. Items cliqueables: `tabIndex={0}`, responden a Enter/Space. |
| Screen readers | `main[aria-label="Panel principal"]`. Grid: `role="list"` y cada widget `role="listitem"`. KPIs con `aria-label` descriptivo. Skeletons: `aria-hidden="true"`. |
| Contraste | AA verificado contra tokens de `WEB_VISUAL_STANDARDS.md`. |
| Focus visible | `focus-visible:ring-2 focus-visible:ring-ring` en todo elemento interactivo (provisto por shadcn/ui). |
| Skip link | Delegado al layout -- el primer bloque de DASHBOARD debe verificar que existe un "Saltar al contenido principal". |

### 8.5 Responsive

| Breakpoint | Columnas | Adaptaciones |
|---|---|---|
| >= 1024px (lg) | 3 | Layout completo. Header con 3 KPIs en fila. |
| >= 768px (md) | 2 | KPIs stacked. Widget "Accesos directos" span 2 al final. |
| < 768px (sm) | 1 | Widgets stacked. Sidebar -> Sheet. Padding reducido. Max 3 items por widget. |

## 9. Seguridad y proteccion de datos

### 9.1 Superficie de ataque

| Vector | Severidad | Mitigacion |
|---|---|---|
| **Composicion client-side (N requests)** | Baja | Cada request ya tiene su propia autorizacion. Anti-enumeration (403/404 unificados) evita revelar existencia de recursos. HTTP/2 multiplexa. |
| **Timing attacks** | Baja | Al no haber endpoint de agregacion, no hay queries condicionales. Cada endpoint tarda lo mismo para todos los usuarios (filtros en WHERE, no en decision de ejecutar). |
| **Manipulacion de `activeCondominiumId`** | Media | Todo endpoint valida server-side que el ID pertenece al scope del usuario (mitigacion IDOR). El cliente propone, el backend dispone. |
| **Enumeracion via conteos** | Media | R-DASH-02: conteos scope-bound. Cada endpoint aplica filtro en SQL, no en memoria. |
| **Exposicion de `permissions[]` en cliente** | Baja | El endpoint `/auth/me` solo devuelve permisos con prefijos de features visibles (no `admin.*`, `usuarios.*`, `roles.*`). |

### 9.2 Datos que NUNCA aparecen en widgets

| Dato | Regla | Feature duena |
|---|---|---|
| `area_m2` | Solo en detalle de unidad, nunca en resumenes/listados | R-10 de PROPIEDADES |
| Coeficientes de unidades ajenas | Solo en detalle, solo unidades en scope | R-10 de PROPIEDADES |
| `email`/`telefono` de terceros para residentes | Habeas Data colombiano (Ley 1581 de 2012) | R-DIR-06 de DIRECTORIO |
| Datos financieros agregados para residentes | Solo ven sus propios saldos (`/me/invoices`), nunca totales del condominio | COBRANZA |
| `password_hash`, `totp_secret`, recovery codes | Nunca en ninguna respuesta | AUTH |

### 9.3 Checklist de seguridad para implementacion

- [ ] Rate limiting: `throttle:30,1` por usuario autenticado en `/auth/me`.
- [ ] `staleTime` maximo 5 minutos para `/auth/me` (evita permisos stale en cliente).
- [ ] Interceptor de 403 fuerza revalidacion de permisos antes de mostrar error.
- [ ] Cada widget con `featureStatus !== 'shipped'` NO dispara llamadas API.
- [ ] TanStack Query con `retry: false` para widgets (no reintentar automaticamente -- el usuario decide).
- [ ] Request timeout: 10s por widget. Si no responde, mostrar error.
- [ ] CSP headers: `default-src 'none'; script-src 'self'; style-src 'self' 'unsafe-inline'; connect-src 'self'; img-src 'self' data:`.
- [ ] Test de regresion RBAC: admin ve todos los widgets; admin parcial solo ve datos de su scope; residente solo ve widgets de su unidad; usuario sin role_assignments ve estructura completa pero vacia.

### 9.4 Puntos ciegos conocidos (no resueltos, monitorear)

1. **Herencia transitiva de scopes:** El RBAC actual no implementa herencia transitiva ("permiso en organization cubre condominium"). Si un usuario tiene scope `organization` en `role_assignments` pero un endpoint chequea scope `condominium`, el permiso no se resuelve automaticamente. Esto es una limitacion del modulo Authorization, no de DASHBOARD. El dashboard la hereda y debe documentarse como deuda tecnica.
2. **Request amplification a futuro:** Con 10+ widgets visibles, cada uno dispara su propia query. Monitorear en produccion. Si se vuelve problematico, evaluar un `GET /dashboard/summary` dedicado -- con el checklist de seguridad de 7 items documentado en el diseno del `design-security`.

## 10. Veredicto del Design Council

### 10.1 Proceso

El Design Council ejecuto el protocolo de 3 fases documentado en `_system/06_AGENT_ROLES.md` S12:

1. **Divergencia (2026-07-09):** `design-architect`, `design-ux` y `design-security` produjeron
   disenos independientes en paralelo desde sus respectivas lentes.
2. **Peer Review anonimizada (2026-07-09):** Los 3 disenos se anonimizaron como Diseno A/B/C.
   Cada sub-agente evaluo los 3 disenos desde su lente y produjo: ranking, fortalezas,
   debilidades, y puntos ciegos.
3. **Sintesis (2026-07-09):** `urbania` consolido los 3 disenos y las 3 peer reviews en este
   documento unificado.

### 10.2 Convergencias

Los 3 sub-agentes coincidieron -- sin excepcion -- en:

| Decision | Architect | UX | Security |
|---|---|---|---|
| Composicion client-side (cero endpoints de agregacion) | Si | Si | Si |
| Widget Registry en codigo TypeScript (cero tablas BD) | Si | Si | Si |
| Nombre de la feature: DASHBOARD | Si | Si | -- |
| Errores visibles con boton Reintentar (nunca ocultar widgets fallidos) | -- | Si | Si (corregido en peer review) |
| Conteos scope-bound ("en tu scope", no totales) | -- | Si | Si |
| Placeholders de features no SHIPPED visibles solo para staff | -- | Si | Si |

### 10.3 Divergencias resueltas

| Tema | Posturas | Resolucion |
|---|---|---|
| **Exposicion de `permissions[]` en `/auth/me`** | Architect: necesario para filtrar widgets. Security: riesgo de information disclosure. | Compromiso: `/auth/me` devuelve solo permisos con prefijos de features visibles (no `admin.*`, `usuarios.*`). Ademas, `staleTime: 5min` e interceptor de 403 fuerza revalidacion. |
| **`useActiveCondominium` en Zustand** | UX: necesario para coordinacion cross-widget. Architect: no lo menciono originalmente. Security: riesgo de IDOR si no se valida server-side. | Incorporado con validacion: el store es estado de cliente (que condominio eligio el usuario), pero todo endpoint valida el ID contra el scope del usuario en backend. |
| **Endpoint ligero para residente** | Security: sugirio `GET /me/dashboard` para residentes. UX: crea experiencia inconsistente. | Rechazado para V1. El residente usara el mismo mecanismo de composicion client-side. Si en el futuro se justifica por performance, se evalua como feature independiente. |

### 10.4 Puntos ciegos detectados en peer review

1. **Onboarding -- experiencia del primer login:** Un admin recien registrado sin condominios ve
   6 widgets en estado Empty con 6 CTAs. El dashboard no tiene un flujo guiado de primer uso.
   Esto es aceptable para V1 (el usuario tiene el menu lateral como alternativa), pero debe
   mejorarse en V2 con un wizard o un dashboard adaptativo que priorice widgets con datos.

2. **Cascada de queries en primera carga:** Con 6 widgets visibles, el navegador dispara 6
   requests simultaneas. El IntersectionObserver mitiga widgets fuera del viewport, pero los 3-4
   visibles inicialmente cargan en paralelo. Aceptable para V1 con HTTP/2. Monitorear.

3. **Timing de registro de widgets vs. resolucion de permisos:** El `Map` de widgets se puebla
   en tiempo de import (sincrono), pero los permisos del usuario vienen de una llamada async a
   `/auth/me`. Durante la ventana entre que el registry esta poblado y los permisos se conocen,
   se muestra un estado de carga global (no se flashean widgets no autorizados).

### 10.5 Recomendacion del council

El Design Council recomienda por unanimidad **proceder con este diseno**. Las decisiones
arquitectonicas son solidas, el modelo de extension esta validado en las 3 lentes, y los
riesgos de seguridad identificados tienen mitigaciones concretas. Los puntos ciegos detectados
no son bloqueantes para V1.

## 11. Checklist de aprobacion (gate)

- [x] S4: cada campo nuevo declara Valor o Referencia -- **N/A: CERO tablas nuevas.**
- [x] S6 cubre toda accion visible al usuario descrita en S1/S5 -- **6 widgets documentados con endpoints exactos.**
- [x] Nombres de campos y entidades consistentes con [[../../shared/GLOSSARY]] -- **Termino "Dashboard" agregado al glosario.**
- [x] No hay una feature existente en `features/` que ya cubra esto -- **Confirmado: ninguna feature existente documenta pantalla principal/home.**

> **Gate humano pendiente.** El `estado_diseno` permanece en `draft` hasta que un humano revise
> este documento y lo cambie a `approved`. Ver `_system/03_LIFECYCLE.md` S3. Una vez aprobado,
> `@doc-agent` crea `BLOCKS.md` y las tarjetas de bloque.
