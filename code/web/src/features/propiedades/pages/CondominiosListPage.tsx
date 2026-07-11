import { useState, useCallback, useMemo } from "react";
import { useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Plus, Search, Building2 } from "lucide-react";
import { PageHeader } from "@/components/page-header";
import { EmptyState } from "@/components/empty-state";
import { LoadingState } from "@/components/loading-state";
import { PAGE_CONTAINER } from "@/lib/layout";
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
    <div className={PAGE_CONTAINER}>
      <PageHeader
        title="Condominios"
        description="Gestiona los condominios de tu organización."
        actions={
          <Button onClick={handleCreate}>
            <Plus className="mr-2 h-4 w-4" />
            Nuevo condominio
          </Button>
        }
      />

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
        <LoadingState />
      ) : filteredItems.length === 0 ? (
        <EmptyState
          icon={Building2}
          message={
            searchTerm
              ? `No se encontraron condominios con "${searchTerm}".`
              : "No hay condominios registrados."
          }
          action={
            searchTerm ? (
              <Button variant="outline" onClick={() => setSearchTerm("")}>
                Limpiar búsqueda
              </Button>
            ) : (
              <Button variant="outline" onClick={handleCreate}>
                <Plus className="mr-2 h-4 w-4" />
                Crear primero
              </Button>
            )
          }
        />
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
