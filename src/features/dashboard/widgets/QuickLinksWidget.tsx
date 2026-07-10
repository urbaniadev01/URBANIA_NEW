import type { ReactNode } from "react";
import { Link } from "react-router-dom";
import { Building2, Home, Percent, Users, CreditCard } from "lucide-react";
import type { WidgetProps} from "@/features/dashboard/types";
import {
  WidgetCard,
  type WidgetState,
} from "@/features/dashboard/components/WidgetCard";
import { Button } from "@/components/ui/button";

// ── Link definitions ───────────────────────────────────────────────────────

interface QuickLink {
  id: string;
  label: string;
  to: string;
  permission: string;
  icon: ReactNode;
}

const QUICK_LINKS: QuickLink[] = [
  {
    id: "condominios",
    label: "Condominios",
    to: "/condominios",
    permission: "condominiums.ver",
    icon: <Building2 className="h-4 w-4" />,
  },
  {
    id: "unidades",
    label: "Unidades",
    to: "/properties",
    permission: "properties.ver",
    icon: <Home className="h-4 w-4" />,
  },
  {
    id: "coeficientes",
    label: "Coeficientes",
    to: "/properties/coefficients",
    permission: "properties.ver",
    icon: <Percent className="h-4 w-4" />,
  },
  {
    id: "directorio",
    label: "Directorio",
    to: "/contacts",
    permission: "contacts.ver",
    icon: <Users className="h-4 w-4" />,
  },
  {
    id: "cobranza",
    label: "Cobranza",
    to: "/billing",
    permission: "billing.ver",
    icon: <CreditCard className="h-4 w-4" />,
  },
];

// ── Permission check (mirrors registry logic) ──────────────────────────────

function userHasPermission(
  permission: string,
  user: WidgetProps["user"],
): boolean {
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

// ── Component ──────────────────────────────────────────────────────────────

/**
 * QuickLinksWidget — Accesos Directos con filtrado por permiso RBAC.
 *
 * Reglas:
 * - Cada link se oculta (removido del DOM) si el usuario no tiene el permiso.
 * - Si ningún link es visible, muestra estado empty.
 * - size: 'full' (ocupa todo el ancho en desktop).
 * - priority: 20.
 */
export default function QuickLinksWidget({ user }: WidgetProps): ReactNode {
  const visibleLinks = QUICK_LINKS.filter((link) =>
    userHasPermission(link.permission, user),
  );

  const state: WidgetState =
    visibleLinks.length === 0
      ? {
          status: "empty",
          message: "No tienes accesos directos disponibles.",
          cta: "",
          onCta: () => {},
        }
      : { status: "normal" };

  return (
    <WidgetCard title="Accesos Directos" state={state}>
      {state.status === "normal" && (
        <nav aria-label="Accesos directos" className="flex flex-col gap-1">
          {visibleLinks.map((link) => (
            <Button
              key={link.id}
              variant="ghost"
              className="justify-start"
              asChild
            >
              <Link to={link.to}>
                {link.icon}
                {link.label}
              </Link>
            </Button>
          ))}
        </nav>
      )}
    </WidgetCard>
  );
}
