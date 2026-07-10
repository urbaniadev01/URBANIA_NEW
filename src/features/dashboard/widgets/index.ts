/**
 * Módulo de registro del dashboard — side-effect module.
 *
 * Registra los 4 widgets core (Welcome, QuickLinks, DirectoryPlaceholder,
 * CobranzaPlaceholder) y sus sidebar items correspondientes.
 *
 * Se importa UNA vez desde bootstrap.ts. No exporta nada — su único efecto
 * es poblar los registries globales vía registerWidget() y registerSidebarItem().
 *
 * Ver features/DASHBOARD/PANORAMA.md §7.2
 */
import { lazy } from "react";
import { registerWidget, registerSidebarItem } from "@/features/dashboard/registry";

// ── Widgets ────────────────────────────────────────────────────────────────

registerWidget({
  id: "welcome",
  feature: "dashboard",
  title: "Bienvenido",
  description: "Saludo y resumen de tu actividad",
  component: lazy(() => import("./WelcomeWidget")),
  priority: 1,
  size: "full",
  defaultVisible: true,
  featureStatus: "shipped",
});

registerWidget({
  id: "quick-links",
  feature: "dashboard",
  title: "Accesos Directos",
  description: "Accesos rápidos a los módulos del sistema",
  component: lazy(() => import("./QuickLinksWidget")),
  priority: 20,
  size: "full",
  defaultVisible: true,
  featureStatus: "shipped",
});

registerWidget({
  id: "directory-placeholder",
  feature: "directorio",
  title: "Directorio",
  description: "Directorio de residentes y contactos",
  component: lazy(() => import("./DirectoryPlaceholderWidget")),
  priority: 80,
  size: "md",
  defaultVisible: true,
  featureStatus: "in_progress",
});

registerWidget({
  id: "cobranza-placeholder",
  feature: "cobranza",
  title: "Cuotas Pendientes",
  description: "Estado de cuotas y pagos",
  component: lazy(() => import("./CobranzaPlaceholderWidget")),
  priority: 90,
  size: "md",
  defaultVisible: true,
  featureStatus: "draft",
});

// ── Sidebar items ──────────────────────────────────────────────────────────

registerSidebarItem({
  id: "sidebar-condominios",
  to: "/condominios",
  label: "Condominios",
  permission: "condominiums.ver",
  group: "Gestión",
});

registerSidebarItem({
  id: "sidebar-unidades",
  to: "/properties",
  label: "Unidades",
  permission: "properties.ver",
  group: "Gestión",
});

registerSidebarItem({
  id: "sidebar-coeficientes",
  to: "/properties/coefficients",
  label: "Coeficientes",
  permission: "properties.ver",
  group: "Gestión",
});

registerSidebarItem({
  id: "sidebar-directorio",
  to: "/contacts",
  label: "Directorio",
  permission: "contacts.ver",
  group: "Gestión",
});

registerSidebarItem({
  id: "sidebar-cobranza",
  to: "/billing",
  label: "Cobranza",
  permission: "billing.ver",
  group: "Gestión",
});
