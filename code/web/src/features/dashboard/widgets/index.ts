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
import { Building2 } from "lucide-react";
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
// Unidades, Coeficientes, Directorio y Cobranza se sacaron de acá: apuntaban
// a rutas que no existen todavía en App.tsx (/properties, /contacts,
// /billing) — se vuelven a agregar cuando esas features tengan una ruta real.

registerSidebarItem({
  id: "sidebar-condominios",
  to: "/condominios",
  label: "Condominios",
  icon: Building2,
  permission: "condominiums.ver",
  group: "Gestión",
});
