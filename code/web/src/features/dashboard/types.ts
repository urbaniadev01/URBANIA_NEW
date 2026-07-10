import type { ComponentType, LazyExoticComponent } from "react";

/**
 * Usuario autenticado — resuelto desde GET /auth/me (mejora pendiente de AUTH).
 * Mientras tanto, el dashboard opera con fallback por rol.
 *
 * Este tipo vive en el feature dashboard porque es el primer consumidor real
 * del perfil de usuario. Cuando AUTH implemente /auth/me, este tipo se promueve
 * a src/types/.
 */
export interface AuthUser {
  /** UUID v7 — provisto por GET /auth/me (LOCK-AUTH-10). */
  id: string;
  email: string;
  name: string;
  role: UserRole;
  /** Permisos RBAC — provistos por /auth/me (LOCK-AUTH-10). */
  permissions: string[];
}

export type UserRole = "admin" | "user";

/**
 * Props que recibe todo widget del dashboard.
 * El componente real (lazy-loaded) recibe este objeto.
 */
export interface WidgetProps {
  user: AuthUser;
}

export type WidgetSize = "sm" | "md" | "lg" | "full";
export type FeatureStatus = "shipped" | "in_progress" | "draft";

/**
 * Definición registrada por cada feature para su widget.
 * El componente se importa con React.lazy() — cada widget es un chunk separado.
 */
export interface WidgetDefinition {
  /** Identificador único — ej. "condominiums-summary" */
  id: string;
  /** Feature propietaria — ej. "propiedades" */
  feature: string;
  /** Título visible en el header de la tarjeta */
  title: string;
  /** Descripción opcional bajo el título */
  description?: string;
  /** Componente lazy-loaded. Renderiza dentro de WidgetCard. */
  component: LazyExoticComponent<ComponentType<WidgetProps>>;
  /** Permiso RBAC requerido. Si el usuario no lo tiene, el widget no se renderiza. */
  requiredPermission?: string;
  /** Rol mínimo requerido (fallback si permissions[] no está disponible). */
  requiredRole?: UserRole;
  /** Prioridad de ordenamiento — menor = primero */
  priority: number;
  /** Tamaño base en la grilla */
  size: WidgetSize;
  /** Si el widget es visible por defecto cuando el usuario tiene permiso */
  defaultVisible: boolean;
  /** Estado del feature propietario — R-DASH-03: solo staff ve no-SHIPPED */
  featureStatus: FeatureStatus;
}

/**
 * Ítem de navegación en la sidebar.
 * Registrado por cada feature vía registerSidebarItem().
 */
export interface SidebarNavItem {
  id: string;
  to: string;
  label: string;
  /** Permiso RBAC requerido. Si no se cumple, el ítem no aparece. */
  permission?: string;
  /** Agrupación visual — ej. "Gestión", "Administración" */
  group?: string;
  /** Sub-ítems anidados (sin anidamiento adicional) */
  children?: Omit<SidebarNavItem, "group" | "children">[];
}
