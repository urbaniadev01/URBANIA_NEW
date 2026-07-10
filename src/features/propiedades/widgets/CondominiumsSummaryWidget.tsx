import type { ReactNode } from "react";
import { useCallback } from "react";
import { Link } from "react-router-dom";
import { Building2 } from "lucide-react";
import type { WidgetProps } from "@/features/dashboard/types";
import {
  WidgetCard,
  type WidgetState,
} from "@/features/dashboard/components/WidgetCard";
import { useCondominiumsQuery } from "@/features/propiedades/api/condominiums";
import { useActiveCondominiumStore } from "@/stores/activeCondominiumStore";
import { Badge } from "@/components/ui/badge";

/**
 * CondominiumsSummaryWidget — "Mis Condominios".
 *
 * Consume LOCK-PROPIEDADES-02: GET /api/v1/condominiums
 * Muestra los primeros 5 condominios del tenant.
 * Al hacer clic en un condominio: setActiveCondominium(id).
 * Footer: link "Ver todos" → /condominiums.
 */
export function CondominiumsSummaryWidget(_props: WidgetProps): ReactNode {
  const { data: condominiums, isLoading, isError, error, refetch } =
    useCondominiumsQuery();
  const setActiveCondominium = useActiveCondominiumStore(
    (s) => s.setActiveCondominium,
  );
  const activeCondominiumId = useActiveCondominiumStore(
    (s) => s.activeCondominiumId,
  );

  const handleSelect = useCallback(
    (id: string) => {
      setActiveCondominium(id);
    },
    [setActiveCondominium],
  );

  // ── Estado derivado ──────────────────────────────────────────────────

  const state: WidgetState = isLoading
    ? { status: "loading" }
    : isError
      ? {
          status: "error",
          message: error?.message ?? "Error al cargar los condominios.",
          onRetry: () => void refetch(),
        }
      : condominiums && condominiums.length === 0
        ? {
            status: "empty",
            message: "No hay condominios",
            cta: "Crear primer condominio",
            onCta: () => {
              // Navegar a la pantalla de creación de condominios
              window.location.href = "/condominiums/nuevo";
            },
          }
        : { status: "normal" };

  // ── Render ───────────────────────────────────────────────────────────

  return (
    <WidgetCard
      title="Mis Condominios"
      description="Condominios en tu organización"
      state={state}
    >
      {state.status === "normal" && (
        <div className="space-y-1">
          {condominiums!.slice(0, 5).map((c) => {
            const isActive = c.id === activeCondominiumId;

            return (
              <button
                key={c.id}
                type="button"
                onClick={() => handleSelect(c.id)}
                className={`flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm transition-colors hover:bg-muted ${
                  isActive ? "bg-primary/10 font-medium text-primary" : ""
                }`}
              >
                <span className="flex items-center gap-2">
                  <Building2 className="h-4 w-4 flex-shrink-0 text-muted-foreground" />
                  <span className="truncate">{c.nombre}</span>
                </span>
                {isActive && (
                  <Badge variant="secondary" className="ml-2 flex-shrink-0">
                    Activo
                  </Badge>
                )}
              </button>
            );
          })}

          {/* Footer link */}
          <Link
            to="/condominiums"
            className="mt-2 flex items-center justify-center rounded-md px-3 py-2 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
          >
            Ver todos
          </Link>
        </div>
      )}
    </WidgetCard>
  );
}

export default CondominiumsSummaryWidget;
