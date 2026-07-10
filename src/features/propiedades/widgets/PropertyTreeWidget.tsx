import type { ReactNode } from "react";
import { useState, useCallback } from "react";
import { useQuery } from "@tanstack/react-query";
import { Building2, ChevronRight } from "lucide-react";
import type { WidgetProps } from "@/features/dashboard/types";
import {
  WidgetCard,
  type WidgetState,
} from "@/features/dashboard/components/WidgetCard";
import { useActiveCondominiumStore } from "@/stores/activeCondominiumStore";
import { apiClient } from "@/services/api-client";
import type {
  CondominioTreeResponse,
  TreeTower,
} from "@/features/propiedades/types";
import type { ApiError } from "@/types/api-error";
import { Badge } from "@/components/ui/badge";

/**
 * PropertyTreeWidget — "Estructura".
 *
 * Consume LOCK-PROPIEDADES-04: GET /api/v1/condominiums/{activeId}/tree
 * Árbol colapsable inline con indentación + badges de conteo, sin tabla:
 *   condominio → torres (conteo de unidades) → unidades sin torre.
 */
export function PropertyTreeWidget(_props: WidgetProps): ReactNode {
  const activeCondominiumId = useActiveCondominiumStore(
    (s) => s.activeCondominiumId,
  );

  const {
    data: treeData,
    isLoading,
    isError,
    error,
    refetch,
  } = useQuery<CondominioTreeResponse, ApiError>({
    queryKey: ["condominiums", activeCondominiumId, "tree"],
    queryFn: async () => {
      return apiClient.get<CondominioTreeResponse>(
        `/api/v1/condominiums/${activeCondominiumId}/tree`,
      );
    },
    enabled: !!activeCondominiumId,
    retry: false,
    staleTime: 5 * 60 * 1000,
  });

  // ── Estado derivado ──────────────────────────────────────────────────

  let state: WidgetState;

  if (!activeCondominiumId) {
    state = {
      status: "empty",
      message: "Selecciona un condominio para ver su estructura.",
      cta: "",
      onCta: () => {},
    };
  } else if (isLoading) {
    state = { status: "loading" };
  } else if (isError) {
    state = {
      status: "error",
      message: error?.message ?? "Error al cargar la estructura.",
      onRetry: () => void refetch(),
    };
  } else if (!treeData?.tree) {
    state = {
      status: "error",
      message: "No se pudo cargar la estructura del condominio.",
      onRetry: () => void refetch(),
    };
  } else {
    state = { status: "normal" };
  }

  // ── Render ───────────────────────────────────────────────────────────

  return (
    <WidgetCard
      title="Estructura"
      description="Árbol de torres y unidades"
      state={state}
    >
      {state.status === "normal" && treeData?.tree && (
        <TreeView tree={treeData.tree} />
      )}
    </WidgetCard>
  );
}

// ─── TreeView ─────────────────────────────────────────────────────────────

interface TreeViewProps {
  tree: CondominioTreeResponse["tree"];
}

function TreeView({ tree }: TreeViewProps): ReactNode {
  const [expandedTowers, setExpandedTowers] = useState<Set<string>>(new Set());
  const [rootExpanded, setRootExpanded] = useState(true);

  const toggleTower = useCallback((towerId: string) => {
    setExpandedTowers((prev) => {
      const next = new Set(prev);
      if (next.has(towerId)) {
        next.delete(towerId);
      } else {
        next.add(towerId);
      }
      return next;
    });
  }, []);

  const toggleRoot = useCallback(() => {
    setRootExpanded((prev) => !prev);
  }, []);

  const totalProperties =
    tree.towers.reduce((sum, t) => sum + t.properties_count, 0) +
    tree.untowered_properties_count;

  return (
    <div className="space-y-0.5 text-sm">
      {/* Nodo raíz: condominio */}
      <TreeNode
        label={tree.nombre}
        count={totalProperties}
        depth={0}
        isExpanded={rootExpanded}
        onToggle={toggleRoot}
        icon={<Building2 className="h-3.5 w-3.5" />}
      />

      {rootExpanded && (
        <>
          {/* Torres */}
          {tree.towers.map((tower) => (
            <TowerNode
              key={tower.id}
              tower={tower}
              isExpanded={expandedTowers.has(tower.id)}
              onToggle={() => toggleTower(tower.id)}
            />
          ))}

          {/* Unidades sin torre */}
          {tree.untowered_properties_count > 0 && (
            <TreeNode
              label="Unidades sin torre"
              count={tree.untowered_properties_count}
              depth={1}
              isExpanded={false}
              onToggle={undefined}
              icon={null}
            />
          )}

          {/* Sin torres ni unidades */}
          {tree.towers.length === 0 && tree.untowered_properties_count === 0 && (
            <div className="py-2 pl-8 text-xs text-muted-foreground">
              Sin torres ni unidades registradas.
            </div>
          )}
        </>
      )}
    </div>
  );
}

// ─── TowerNode ────────────────────────────────────────────────────────────

interface TowerNodeProps {
  tower: TreeTower;
  isExpanded: boolean;
  onToggle: () => void;
}

function TowerNode({
  tower,
  isExpanded,
  onToggle,
}: TowerNodeProps): ReactNode {
  return (
    <>
      <TreeNode
        label={tower.nombre}
        count={tower.properties_count}
        depth={1}
        isExpanded={isExpanded}
        onToggle={onToggle}
        icon={null}
      />

      {isExpanded && (
        <div className="py-1 pl-10 text-xs text-muted-foreground">
          {tower.properties_count} {tower.properties_count === 1 ? "unidad" : "unidades"} en esta torre.
        </div>
      )}
    </>
  );
}

// ─── TreeNode ─────────────────────────────────────────────────────────────

interface TreeNodeProps {
  label: string;
  count: number;
  depth: number;
  isExpanded: boolean;
  onToggle: (() => void) | undefined;
  icon: ReactNode;
}

function TreeNode({
  label,
  count,
  depth,
  isExpanded,
  onToggle,
  icon,
}: TreeNodeProps): ReactNode {
  const isCollapsible = onToggle !== undefined;

  return (
    <button
      type="button"
      onClick={onToggle}
      disabled={!isCollapsible}
      className={`flex w-full items-center gap-1.5 rounded-md py-1.5 text-left transition-colors hover:bg-muted/50 ${
        isCollapsible ? "cursor-pointer" : "cursor-default"
      }`}
      style={{ paddingLeft: `${depth * 1.25 + 0.5}rem` }}
    >
      {/* Chevron para colapsables */}
      {isCollapsible && (
        <ChevronRight
          className={`h-3.5 w-3.5 flex-shrink-0 text-muted-foreground transition-transform ${
            isExpanded ? "rotate-90" : ""
          }`}
        />
      )}

      {/* Icono (solo nodo raíz) */}
      {icon && <span className="flex-shrink-0 text-muted-foreground">{icon}</span>}

      {/* Label */}
      <span className="flex-1 truncate">{label}</span>

      {/* Badge de conteo */}
      <Badge variant="secondary" className="ml-auto flex-shrink-0 text-xs">
        {count}
      </Badge>
    </button>
  );
}

export default PropertyTreeWidget;
