/**
 * DIRECTORIO — Side-effect module para integración con el dashboard.
 *
 * Este archivo se importa UNA vez desde bootstrap.ts.
 * Al importarse, registra las entradas de sidebar de DIRECTORIO en el Widget
 * Registry sin modificar ningún archivo del core del dashboard.
 *
 * Zero-touch integration (ver features/DASHBOARD/PANORAMA.md §7.4).
 */
import { Users, Contact, UserCircle } from "lucide-react";
import { registerSidebarItem } from "@/features/dashboard/registry";

// Catálogo de tipos de ocupante — mismo permiso "admin.access" que el grupo
// "Administración" de PROPIEDADES (ver RbacDemoSeeder): admin/manager lo ven,
// resident nunca.
registerSidebarItem({
  id: "sidebar-admin-tipos-ocupante",
  to: "/catalogos/tipos-ocupante",
  label: "Tipos de ocupante",
  icon: Users,
  permission: "admin.access",
  group: "Administración",
});

// Directorio de contactos — pantalla administrativa (DIRECTORIO-B06), mismo
// permiso que el resto del grupo "Administración".
registerSidebarItem({
  id: "sidebar-admin-contactos",
  to: "/directorio/contactos",
  label: "Contactos",
  icon: Contact,
  permission: "admin.access",
  group: "Administración",
});

// Mi perfil — autoservicio (R-DIR-04), sin permiso: visible para cualquier
// usuario autenticado, incluido "resident".
registerSidebarItem({
  id: "sidebar-mi-perfil",
  to: "/perfil",
  label: "Mi perfil",
  icon: UserCircle,
});
