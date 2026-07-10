import { useState, useCallback, useMemo } from "react";
import { useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Loader2, Plus, Search, Building2 } from "lucide-react";
import { CondominioCard } from "../components/CondominioCard";
import { CondominioSheet } from "../components/CondominioSheet";
import {
  useCondominiumsQuery,
  useCreateCondominioMutation,
} from "../api/condominiums";
import type { CondominioItem, CondominioFormValues } from "../types";

/**
 * Página de lista de condominios — ruta /condominios.
 * Muestra grid de cards con búsqueda, y Sheet para crear condominio.
 * Consume LOCK-PROPIEDADES-02: GET /condominiums, POST /condominiums.
 */
export function CondominiosListPage(): React.ReactNode {
  const navigate = useNavigate();
  const { data: items = [], isLoading } = useCondominiumsQuery();
  const createMutation = useCreateCondominioMutation();

  const [sheetOpen, setSheetOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState("");

  // ── Búsqueda local ───────────────────────────────────────────────────

  const filteredItems = useMemo(() => {
    if (!searchTerm.trim()) return items;
    const term = searchTerm.toLowerCase().trim();
    return items.filter((item) =>
      item.nombre.toLowerCase().includes(term),
    );
  }, [items, searchTerm]);

  // ── Handlers ─────────────────────────────────────────────────────────

  const handleCreate = useCallback(() => {
    setSheetOpen(true);
  }, []);

  const handleSheetSubmit = useCallback(
    (values: CondominioFormValues) => {
      createMutation.mutate(
        {
          nombre: values.nombre,
          direccion: values.direccion || undefined,
          nit: values.nit || undefined,
        },
        {
          onSuccess: () => setSheetOpen(false),
        },
      );
    },
    [createMutation],
  );

  const handleCardClick = useCallback(
    (item: CondominioItem) => {
      navigate(`/condominios/${item.id}`);
    },
    [navigate],
  );

  // ── Render ──────────────────────────────────────────────────────────

  return (
    <div className="container mx-auto max-w-6xl px-8 py-8">
      {/* Header */}
      <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">Condominios</h1>
          <p className="text-sm text-muted-foreground">
            Gestiona los condominios de tu organización.
          </p>
        </div>
        <Button onClick={handleCreate}>
          <Plus className="mr-2 h-4 w-4" />
          Nuevo condominio
        </Button>
      </div>

      {/* Search bar */}
      <div className="relative mb-6">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          placeholder="Buscar por nombre..."
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
          className="pl-9"
        />
      </div>

      {/* Content */}
      {isLoading ? (
        <div className="flex items-center justify-center py-16">
          <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
        </div>
      ) : filteredItems.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed py-16 text-center">
          <Building2 className="mb-4 h-10 w-10 text-muted-foreground/60" />
          {searchTerm ? (
            <>
              <p className="text-sm text-muted-foreground">
                No se encontraron condominios con &quot;{searchTerm}&quot;.
              </p>
              <Button
                variant="outline"
                className="mt-4"
                onClick={() => setSearchTerm("")}
              >
                Limpiar búsqueda
              </Button>
            </>
          ) : (
            <>
              <p className="text-sm text-muted-foreground">
                No hay condominios registrados.
              </p>
              <Button variant="outline" className="mt-4" onClick={handleCreate}>
                <Plus className="mr-2 h-4 w-4" />
                Crear primero
              </Button>
            </>
          )}
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {filteredItems.map((item) => (
            <CondominioCard
              key={item.id}
              condominio={item}
              onClick={() => handleCardClick(item)}
            />
          ))}
        </div>
      )}

      {/* Create Sheet */}
      <CondominioSheet
        key="new"
        open={sheetOpen}
        onOpenChange={setSheetOpen}
        item={null}
        isSubmitting={createMutation.isPending}
        onSubmit={handleSheetSubmit}
      />
    </div>
  );
}
