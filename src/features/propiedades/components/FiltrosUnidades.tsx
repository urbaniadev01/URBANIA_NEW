import { useMemo } from "react";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Search } from "lucide-react";
import type { PropertyFilters, CatalogoItem, TorreItem } from "../types";

interface FiltrosUnidadesProps {
  filters: PropertyFilters;
  searchInput: string;
  onFilterChange: (filters: PropertyFilters) => void;
  onSearchChange: (value: string) => void;
  towers: TorreItem[];
  tipos: CatalogoItem[];
  estados: CatalogoItem[];
}

/**
 * Barra de filtros combinables para la tabla de unidades.
 * - Dropdown de torre (con opción "Todas las torres")
 * - Dropdown de tipo (con opción "Todos los tipos")
 * - Dropdown de estado (con opción "Todos los estados")
 * - Campo de búsqueda por código (debounce manejado por el padre)
 */
export function FiltrosUnidades({
  filters,
  searchInput,
  onFilterChange,
  onSearchChange,
  towers,
  tipos,
  estados,
}: FiltrosUnidadesProps) {
  const towerOptions = useMemo(
    () => [
      { value: "", label: "Todas las torres" },
      ...towers.map((t) => ({ value: t.id, label: t.nombre })),
    ],
    [towers],
  );

  const tipoOptions = useMemo(
    () => [
      { value: "", label: "Todos los tipos" },
      ...tipos.map((t) => ({ value: t.id, label: t.nombre })),
    ],
    [tipos],
  );

  const estadoOptions = useMemo(
    () => [
      { value: "", label: "Todos los estados" },
      ...estados.map((e) => ({ value: e.id, label: e.nombre })),
    ],
    [estados],
  );

  function updateTower(value: string) {
    onFilterChange({ ...filters, tower_id: value || undefined });
  }

  function updateType(value: string) {
    onFilterChange({ ...filters, type_id: value || undefined });
  }

  function updateStatus(value: string) {
    onFilterChange({ ...filters, status_id: value || undefined });
  }

  return (
    <div className="flex flex-wrap items-center gap-3">
      <div className="flex-1 min-w-[200px] max-w-[320px]">
        <div className="relative">
          <Search className="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            placeholder="Buscar por código..."
            className="pl-8"
            value={searchInput}
            onChange={(e) => onSearchChange(e.target.value)}
          />
        </div>
      </div>
      <Select
        options={towerOptions}
        value={filters.tower_id ?? ""}
        onChange={(e) => updateTower(e.target.value)}
        className="w-[180px]"
      />
      <Select
        options={tipoOptions}
        value={filters.type_id ?? ""}
        onChange={(e) => updateType(e.target.value)}
        className="w-[180px]"
      />
      <Select
        options={estadoOptions}
        value={filters.status_id ?? ""}
        onChange={(e) => updateStatus(e.target.value)}
        className="w-[180px]"
      />
    </div>
  );
}
