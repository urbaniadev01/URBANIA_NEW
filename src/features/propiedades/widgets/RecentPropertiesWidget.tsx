import type { ReactNode } from "react";
import { Link } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { DoorOpen } from "lucide-react";
import type { WidgetProps } from "@/features/dashboard/types";
import {
  WidgetCard,
  type WidgetState,
} from "@/features/dashboard/components/WidgetCard";
import { useActiveCondominiumStore } from "@/stores/activeCondominiumStore";
import { apiClient } from "@/services/api-client";
import type { PropertyListItem, PropertyListResponse } from "@/features/propiedades/types";
import type { ApiError } from "@/types/api-error";

/**
 * RecentPropertiesWidget — "Unidades Recientes".
 *
 * Consume LOCK-PROPIEDADES-03: GET /api/v1/condominiums/{activeId}/properties?limit=5
 * Depende de activeCondominiumId vía Zustand.
 * Sin condominio activo: empty "Selecciona un condominio...".
 * Footer: link "Ver todas" → /properties.
 */
export function RecentPropertiesWidget(_props: WidgetProps): ReactNode {
  const activeCondominiumId = useActiveCondominiumStore(
    (s) => s.activeCondominiumId,
  );

  const {
    data: properties,
    isLoading,
    isError,
    error,
    refetch,
  } = useQuery<PropertyListItem[], ApiError>({
    queryKey: ["properties", activeCondominiumId, "recent"],
    queryFn: async () => {
      const params = new URLSearchParams();
      params.set("limit", "5");
      const url = `/api/v1/condominiums/${activeCondominiumId}/properties?${params.toString()}`;
      const response = await apiClient.get<PropertyListResponse>(url);
      return response.data;
    },
    enabled: !!activeCondominiumId,
    retry: false,
    staleTime: 30_000,
  });

  // ── Estado derivado ──────────────────────────────────────────────────

  let state: WidgetState;

  if (!activeCondominiumId) {
    state = {
      status: "empty",
      message: "Selecciona un condominio para ver sus unidades.",
      cta: "",
      onCta: () => {},
    };
  } else if (isLoading) {
    state = { status: "loading" };
  } else if (isError) {
    state = {
      status: "error",
      message: error?.message ?? "Error al cargar las unidades.",
      onRetry: () => void refetch(),
    };
  } else if (!properties || properties.length === 0) {
    state = {
      status: "empty",
      message: "Este condominio no tiene unidades registradas.",
      cta: "Agregar unidad",
      onCta: () => {
        window.location.href = `/condominiums/${activeCondominiumId}/properties/nueva`;
      },
    };
  } else {
    state = { status: "normal" };
  }

  // ── Render ───────────────────────────────────────────────────────────

  return (
    <WidgetCard
      title="Unidades Recientes"
      description="Últimas unidades registradas"
      state={state}
    >
      {state.status === "normal" && (
        <div className="space-y-1">
          {properties!.map((p) => (
            <div
              key={p.id}
              className="flex items-center justify-between rounded-md px-3 py-2 text-sm"
            >
              <span className="flex items-center gap-2">
                <DoorOpen className="h-4 w-4 flex-shrink-0 text-muted-foreground" />
                <span className="font-mono text-xs">{p.codigo}</span>
              </span>
              {p.piso !== null && p.piso !== undefined && (
                <span className="text-xs text-muted-foreground">
                  Piso {p.piso}
                </span>
              )}
            </div>
          ))}

          {/* Footer link — solo si hay condominio activo */}
          {activeCondominiumId && (
            <Link
              to={`/condominiums/${activeCondominiumId}/properties`}
              className="mt-2 flex items-center justify-center rounded-md px-3 py-2 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
            >
              Ver todas
            </Link>
          )}
        </div>
      )}
    </WidgetCard>
  );
}

export default RecentPropertiesWidget;
