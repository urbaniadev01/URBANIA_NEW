import { type ReactNode, useState, useCallback } from "react";
import { Link, useLocation } from "react-router-dom";
import { Home, Menu, PanelLeftClose, PanelLeftOpen } from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import { getVisibleSidebar } from "@/features/dashboard/registry";
import type { AuthUser, SidebarNavItem } from "@/features/dashboard/types";
import { cn } from "@/lib/utils";
import { ModeToggle } from "@/components/mode-toggle";
import { ThemeCustomizer } from "@/components/theme-customizer";
import { CommandMenu } from "@/components/command-menu";
import { UserMenu } from "@/components/user-menu";

interface DashboardShellProps {
  /** Usuario autenticado (null durante carga de permisos). */
  user: AuthUser | null;
  /** Contenido del área principal (DashboardPage, etc.). */
  children: ReactNode;
  /** Slot para el widget de bienvenida en el header (renderizado por B03). */
  headerSlot?: ReactNode;
}

/**
 * DashboardShell — layout RBAC-aware del panel principal.
 *
 * Estructura (PANORAMA §8.1):
 * - Skip link "Saltar al contenido principal"
 * - Sidebar colapsable con ítems dinámicos desde sidebarRegistry
 * - Header con slot para WelcomeWidget (B03)
 * - main[aria-label="Panel principal"] con el contenido
 *
 * Responsive (PANORAMA §8.5):
 * - Desktop (>= 768px): sidebar fija + contenido desplazado
 * - Mobile (< 768px): sidebar en Sheet (drawer lateral)
 */
export function DashboardShell({
  user,
  children,
  headerSlot,
}: DashboardShellProps): ReactNode {
  const [sidebarOpen, setSidebarOpen] = useState(true);
  const location = useLocation();

  const sidebarItems = user ? getVisibleSidebar(user) : [];

  const toggleSidebar = useCallback(() => {
    setSidebarOpen((prev) => !prev);
  }, []);

  return (
    <TooltipProvider delayDuration={200}>
      <div className="flex min-h-screen bg-background">
        {/* ── Skip link ───────────────────────────────────────────── */}
        <a
          href="#main-content"
          className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-primary focus:px-4 focus:py-2 focus:text-primary-foreground focus:outline-none focus:ring-2 focus:ring-ring"
        >
          Saltar al contenido principal
        </a>

        {/* ── Sidebar desktop ─────────────────────────────────────── */}
        <aside
          className={cn(
            "hidden border-r bg-card md:flex md:flex-col md:transition-all md:duration-300",
            sidebarOpen ? "md:w-64" : "md:w-16",
          )}
        >
          <div className="flex h-16 items-center gap-2 border-b px-4">
            <img
              src="/logo.png"
              alt="Urbania"
              className="h-8 w-8 shrink-0 object-contain"
            />
            <span
              className={cn(
                "text-base font-semibold tracking-tight text-foreground",
                !sidebarOpen && "sr-only",
              )}
            >
              Urbania
            </span>
          </div>
          <SidebarContent
            items={sidebarItems}
            collapsed={!sidebarOpen}
            currentPath={location.pathname}
          />
        </aside>

        {/* ── Área principal ──────────────────────────────────────── */}
        <div className="flex flex-1 flex-col">
          {/* ── Header ──────────────────────────────────────────── */}
          <header className="sticky top-0 z-40 flex h-16 items-center gap-4 border-b bg-background/95 px-4 backdrop-blur supports-[backdrop-filter]:bg-background/60 md:px-6">
            {/* Mobile sidebar trigger */}
            <Sheet>
              <SheetTrigger asChild>
                <Button variant="ghost" size="icon" className="md:hidden">
                  <Menu className="h-5 w-5" />
                  <span className="sr-only">Abrir menú</span>
                </Button>
              </SheetTrigger>
              <SheetContent side="left" className="w-64 p-0">
                <SidebarContent
                  items={sidebarItems}
                  collapsed={false}
                  currentPath={location.pathname}
                />
              </SheetContent>
            </Sheet>

            {/* Desktop sidebar toggle */}
            <Button
              variant="ghost"
              size="icon"
              className="hidden md:flex"
              onClick={toggleSidebar}
              aria-label={sidebarOpen ? "Colapsar menú" : "Expandir menú"}
            >
              {sidebarOpen ? (
                <PanelLeftClose className="h-5 w-5" />
              ) : (
                <PanelLeftOpen className="h-5 w-5" />
              )}
            </Button>

            {/* Búsqueda de features (Cmd/Ctrl+K) */}
            <div className="hidden md:block">
              <CommandMenu user={user} />
            </div>

            {/* Slot para WelcomeWidget (renderizado por B03) */}
            <div className="flex flex-1 items-center justify-end gap-2">
              {headerSlot}
              <ThemeCustomizer />
              <ModeToggle />
              {user && <UserMenu user={user} />}
            </div>
          </header>

          {/* ── Contenido principal ──────────────────────────────── */}
          <main
            id="main-content"
            aria-label="Panel principal"
            className="flex-1"
          >
            {children}
          </main>
        </div>
      </div>
    </TooltipProvider>
  );
}

/**
 * Contenido interno de la sidebar — compartido entre desktop y mobile (Sheet).
 */
function SidebarContent({
  items,
  collapsed,
  currentPath,
}: {
  items: SidebarNavItem[];
  collapsed: boolean;
  currentPath: string;
}): ReactNode {
  // Agrupar ítems por group
  const grouped = new Map<string, SidebarNavItem[]>();
  const ungrouped: SidebarNavItem[] = [];

  for (const item of items) {
    if (item.group) {
      const group = grouped.get(item.group) ?? [];
      group.push(item);
      grouped.set(item.group, group);
    } else {
      ungrouped.push(item);
    }
  }

  return (
    <nav aria-label="Navegación principal" className="flex flex-col gap-1 p-3">
      {/* Ítem Dashboard siempre presente */}
      <SidebarLink
        to="/"
        label="Inicio"
        icon={Home}
        active={currentPath === "/" || currentPath === "/dashboard"}
        collapsed={collapsed}
      />

      {/* Grupos */}
      {[...grouped.entries()].map(([group, groupItems]) => (
        <div key={group} className="mt-4 first:mt-0">
          {!collapsed && (
            <p className="mb-1 px-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
              {group}
            </p>
          )}
          {groupItems.map((item) => (
            <SidebarItemNode
              key={item.id}
              item={item}
              collapsed={collapsed}
              currentPath={currentPath}
            />
          ))}
        </div>
      ))}

      {/* Sin grupo */}
      {ungrouped.length > 0 && (
        <div className="mt-4">
          {ungrouped.map((item) => (
            <SidebarItemNode
              key={item.id}
              item={item}
              collapsed={collapsed}
              currentPath={currentPath}
            />
          ))}
        </div>
      )}
    </nav>
  );
}

function SidebarItemNode({
  item,
  collapsed,
  currentPath,
}: {
  item: SidebarNavItem;
  collapsed: boolean;
  currentPath: string;
}): ReactNode {
  const isActive = currentPath.startsWith(item.to);

  return (
    <div>
      <SidebarLink
        to={item.to}
        label={item.label}
        icon={item.icon}
        active={isActive}
        collapsed={collapsed}
      />
      {item.children && !collapsed && (
        <div className="ml-4">
          {item.children.map((child) => (
            <SidebarLink
              key={child.id}
              to={child.to}
              label={child.label}
              icon={child.icon}
              active={currentPath.startsWith(child.to)}
              collapsed={false}
            />
          ))}
        </div>
      )}
    </div>
  );
}

function SidebarLink({
  to,
  label,
  icon: Icon,
  active,
  collapsed,
}: {
  to: string;
  label: string;
  icon?: LucideIcon;
  active: boolean;
  collapsed: boolean;
}): ReactNode {
  const link = (
    <Link
      to={to}
      className={cn(
        "flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors",
        "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
        active
          ? "bg-primary/10 text-primary"
          : "text-muted-foreground hover:bg-accent hover:text-accent-foreground",
        collapsed && "justify-center px-2",
      )}
    >
      {Icon && <Icon className="h-4 w-4 shrink-0" />}
      {!collapsed && label}
    </Link>
  );

  if (!collapsed) return link;

  return (
    <Tooltip>
      <TooltipTrigger asChild>{link}</TooltipTrigger>
      <TooltipContent side="right">{label}</TooltipContent>
    </Tooltip>
  );
}
