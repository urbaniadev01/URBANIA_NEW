import { type ReactNode, useCallback } from "react";
import { useQueryClient } from "@tanstack/react-query";
import type { WidgetProps } from "@/features/dashboard/types";
import {
  WidgetCard,
  type WidgetState,
} from "@/features/dashboard/components/WidgetCard";
import { useCondominiumsQuery } from "@/features/propiedades/api/condominiums";
import { useCondominioTreeQuery } from "@/features/propiedades/api/coefficients";
import { useActiveCondominiumStore } from "@/stores/activeCondominiumStore";

// ── Helpers ────────────────────────────────────────────────────────────────

function getGreeting(name: string): string {
  const hour = new Date().getHours();
  if (hour < 12) return `Buenos días, ${name}`;
  if (hour < 18) return `Buenas tardes, ${name}`;
  return `Buenas noches, ${name}`;
}

function formatDate(): string {
  return new Date().toLocaleDateString("es-ES", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}

// ── Component ──────────────────────────────────────────────────────────────

/**
 * WelcomeWidget — saludo contextual + fecha + 3 KPIs mini.
 *
 * Datos:
 * - GET /condominiums → cantidad de condominios en el scope del usuario
 * - GET /condominiums/{activeId}/tree → cantidad de unidades y torres
 *   (0 si no hay condominio activo)
 *
 * Accesibilidad:
 * - Cada KPI tiene aria-label descriptivo (ej. "3 condominios en tu scope")
 * - La sección completa es un <section> con aria-label
 */
export default function WelcomeWidget({ user }: WidgetProps): ReactNode {
  const queryClient = useQueryClient();

  // ── Queries ──────────────────────────────────────────────────────────
  const {
    data: condominiums,
    isLoading: condominiumsLoading,
    isError: condominiumsError,
  } = useCondominiumsQuery();

  const activeCondominiumId = useActiveCondominiumStore(
    (s) => s.activeCondominiumId,
  );

  const {
    data: treeData,
    isLoading: treeLoading,
    isError: treeError,
  } = useCondominioTreeQuery(activeCondominiumId ?? undefined);

  // ── KPIs ─────────────────────────────────────────────────────────────
  const condominiosCount = condominiums?.length ?? 0;

  let unidadesCount = 0;
  let torresCount = 0;
  if (treeData?.tree) {
    torresCount = treeData.tree.towers.length;
    unidadesCount =
      treeData.tree.towers.reduce(
        (sum, tower) => sum + tower.properties_count,
        0,
      ) + treeData.tree.untowered_properties_count;
  }

  // ── State ────────────────────────────────────────────────────────────
  const isLoading = condominiumsLoading || (!!activeCondominiumId && treeLoading);
  const isError = condominiumsError || (!!activeCondominiumId && treeError);

  const handleRetry = useCallback(() => {
    void queryClient.invalidateQueries({ queryKey: ["condominiums"] });
    if (activeCondominiumId) {
      void queryClient.invalidateQueries({
        queryKey: ["condominiums", activeCondominiumId, "tree"],
      });
    }
  }, [queryClient, activeCondominiumId]);

  let state: WidgetState;
  if (isLoading) {
    state = { status: "loading" };
  } else if (isError) {
    state = {
      status: "error",
      message:
        "No se pudieron cargar los datos del panel. Verificá tu conexión e intentá de nuevo.",
      onRetry: handleRetry,
    };
  } else {
    state = { status: "normal" };
  }

  const greeting = getGreeting(user.name);
  const today = formatDate();

  return (
    <WidgetCard title="" state={state}>
      {state.status === "normal" && (
        <div className="space-y-4">
          <div>
            <h2 className="text-xl font-semibold tracking-tight">
              {greeting}
            </h2>
            <p className="text-sm capitalize text-muted-foreground">
              {today}
            </p>
          </div>

          <div className="grid grid-cols-3 gap-3">
            <KPICard
              label="Condominios"
              value={condominiosCount}
              ariaLabel={`${condominiosCount} condominios en tu scope`}
            />
            <KPICard
              label="Unidades"
              value={unidadesCount}
              ariaLabel={`${unidadesCount} unidades en tu scope`}
            />
            <KPICard
              label="Torres"
              value={torresCount}
              ariaLabel={`${torresCount} torres en tu scope`}
            />
          </div>
        </div>
      )}
    </WidgetCard>
  );
}

// ── KPI sub-component ──────────────────────────────────────────────────────

interface KPICardProps {
  label: string;
  value: number;
  ariaLabel: string;
}

function KPICard({ label, value, ariaLabel }: KPICardProps): ReactNode {
  return (
    <div
      className="flex flex-col items-center rounded-lg bg-muted/50 p-3 text-center"
      role="status"
      aria-label={ariaLabel}
    >
      <span className="text-2xl font-bold tabular-nums">{value}</span>
      <span className="text-xs text-muted-foreground">{label}</span>
    </div>
  );
}
