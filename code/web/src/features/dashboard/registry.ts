import type {
  AuthUser,
  SidebarNavItem,
  WidgetDefinition,
} from "@/features/dashboard/types";

/**
 * Widget Registry — mapa global poblado por side-effect modules.
 *
 * Cada feature registra sus widgets en su propio archivo (ej.
 * features/propiedades/dashboard.ts) vía registerWidget().
 * El punto de entrada bootstrap.ts importa cada archivo de registro.
 *
 * Diseño: ver features/DASHBOARD/PANORAMA.md §7.2
 */

const widgetRegistry = new Map<string, WidgetDefinition>();
const sidebarRegistry = new Map<string, SidebarNavItem>();

/**
 * Registra un widget en el sistema.
 * Lanza en desarrollo si el ID ya existe (detección temprana de colisiones).
 */
export function registerWidget(def: WidgetDefinition): void {
  if (widgetRegistry.has(def.id)) {
    if (import.meta.env.DEV) {
      console.warn(
        `[WidgetRegistry] El widget "${def.id}" ya está registrado. Se sobrescribe.`,
      );
    }
  }
  widgetRegistry.set(def.id, def);
}

/**
 * Registra un ítem de navegación lateral.
 */
export function registerSidebarItem(item: SidebarNavItem): void {
  if (sidebarRegistry.has(item.id)) {
    if (import.meta.env.DEV) {
      console.warn(
        `[SidebarRegistry] El ítem "${item.id}" ya está registrado. Se sobrescribe.`,
      );
    }
  }
  sidebarRegistry.set(item.id, item);
}

/**
 * Determina si un usuario tiene un permiso específico.
 *
 * Estrategia:
 * 1. Primario: busca el permiso exacto en `user.permissions[]`.
 * 2. Fallback: staff (admin) tiene acceso implícito a todo.
 * 3. Rol: si no hay permissions[], el rol 'admin' funciona como wildcard.
 */
function userHasPermission(
  user: AuthUser,
  permission: string | undefined,
): boolean {
  if (!permission) return true; // Sin permiso requerido → visible para todos

  // Admin role has implicit access to everything
  if (user.role === "admin") return true;

  // Check explicit permissions
  if (user.permissions.includes(permission)) return true;

  // Wildcard match: "propiedades.*" matches "propiedades.ver", etc.
  if (permission.endsWith(".*")) {
    const prefix = permission.slice(0, -2);
    return user.permissions.some((p) => p.startsWith(prefix + "."));
  }

  return false;
}

/**
 * Determina si un usuario es staff (puede ver features no shipped).
 * Staff = rol 'admin'.
 */
function isStaff(user: AuthUser): boolean {
  return user.role === "admin";
}

/**
 * Filtra los widgets visibles para un usuario.
 *
 * Reglas (ver PANORAMA §5):
 * - R-DASH-01: requiredPermission → el usuario debe tener el permiso
 * - R-DASH-03: featureStatus !== 'shipped' → solo staff
 * - Orden: por priority ascendente
 * - Si user es null (ventana de permisos no resueltos), retorna [].
 */
export function getVisibleWidgets(user: AuthUser | null): WidgetDefinition[] {
  if (!user) return [];

  const widgets: WidgetDefinition[] = [];

    for (const def of widgetRegistry.values()) {
      // R-DASH-01: check requiredRole (minimum role required)
      if (def.requiredRole === "admin" && user.role !== "admin") continue;

      // R-DASH-01: check requiredPermission
      if (!userHasPermission(user, def.requiredPermission)) continue;

    // R-DASH-03: non-shipped features only visible to staff
    if (def.featureStatus !== "shipped" && !isStaff(user)) continue;

    // defaultVisible check (future-proofing for user preferences)
    if (!def.defaultVisible) continue;

    widgets.push(def);
  }

  // Sort by priority ascending (lower = first)
  widgets.sort((a, b) => a.priority - b.priority);

  return widgets;
}

/**
 * Filtra los ítems de sidebar visibles para un usuario.
 *
 * Reglas:
 * - permission → el usuario debe tener el permiso (o ser admin)
 * - Agrupados por `group` (los sin grupo van al final)
 * - Si user es null, retorna [].
 */
export function getVisibleSidebar(user: AuthUser | null): SidebarNavItem[] {
  if (!user) return [];

  const items: SidebarNavItem[] = [];

  for (const item of sidebarRegistry.values()) {
    if (!userHasPermission(user, item.permission)) continue;

    // Filter children by permission too
    const filteredItem: SidebarNavItem = { ...item };
    if (item.children && item.children.length > 0) {
      filteredItem.children = item.children.filter((child) =>
        userHasPermission(user, child.permission),
      );
      // If all children are filtered out, still show the parent
    }

    items.push(filteredItem);
  }

  // Sort: grouped items first (by group name), then ungrouped
  items.sort((a, b) => {
    if (a.group && !b.group) return -1;
    if (!a.group && b.group) return 1;
    if (a.group && b.group) return a.group.localeCompare(b.group);
    return a.label.localeCompare(b.label);
  });

  return items;
}
