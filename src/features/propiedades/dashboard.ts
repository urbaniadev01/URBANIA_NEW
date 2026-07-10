/**
 * PROPIEDADES — Side-effect module para integración con el dashboard.
 *
 * Este archivo se importa UNA vez desde bootstrap.ts.
 * Al importarse, registra los 3 widgets de PROPIEDADES y sus sidebar items
 * en el Widget Registry sin modificar ningún archivo del core del dashboard.
 *
 * Zero-touch integration (PANORAMA §7.4):
 * Agregar un feature nuevo al dashboard = una línea de import en bootstrap.ts.
 *
 * Widgets registrados:
 * 1. condominiums-summary — "Mis Condominios" (selector de condominio activo)
 * 2. recent-properties    — "Unidades Recientes"
 * 3. property-tree        — "Estructura" (árbol colapsable)
 */
import { lazy } from "react";
import {
  registerWidget,
  registerSidebarItem,
} from "@/features/dashboard/registry";

// ── Widget 1: Mis Condominios ────────────────────────────────────────────

registerWidget({
  id: "condominiums-summary",
  feature: "propiedades",
  title: "Mis Condominios",
  description: "Condominios de tu organización",
  component: lazy(
    () =>
      import(
        "@/features/propiedades/widgets/CondominiumsSummaryWidget"
      ),
  ),
  requiredPermission: "condominiums.ver",
  requiredRole: "admin",
  priority: 20,
  size: "md",
  defaultVisible: true,
  featureStatus: "shipped",
});

// ── Widget 2: Unidades Recientes ─────────────────────────────────────────

registerWidget({
  id: "recent-properties",
  feature: "propiedades",
  title: "Unidades Recientes",
  description: "Últimas unidades registradas",
  component: lazy(
    () =>
      import(
        "@/features/propiedades/widgets/RecentPropertiesWidget"
      ),
  ),
  requiredPermission: "condominiums.ver",
  requiredRole: "admin",
  priority: 30,
  size: "md",
  defaultVisible: true,
  featureStatus: "shipped",
});

// ── Widget 3: Estructura ────────────────────────────────────────────────

registerWidget({
  id: "property-tree",
  feature: "propiedades",
  title: "Estructura",
  description: "Árbol de torres y unidades",
  component: lazy(
    () =>
      import(
        "@/features/propiedades/widgets/PropertyTreeWidget"
      ),
  ),
  requiredPermission: "condominiums.ver",
  requiredRole: "admin",
  priority: 40,
  size: "lg",
  defaultVisible: true,
  featureStatus: "shipped",
});

// ── Sidebar Items ────────────────────────────────────────────────────────

registerSidebarItem({
  id: "sidebar-condominiums",
  to: "/condominiums",
  label: "Condominios",
  permission: "condominiums.ver",
  group: "Gestión",
});

registerSidebarItem({
  id: "sidebar-properties",
  to: "/properties",
  label: "Unidades",
  permission: "condominiums.ver",
  group: "Gestión",
});
