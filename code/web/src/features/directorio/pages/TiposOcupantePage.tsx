import { useState, useCallback } from "react";
import { CatalogoTable } from "@/features/propiedades/components/CatalogoTable";
import { CatalogoDialog } from "@/features/propiedades/components/CatalogoDialog";
import { DeleteConfirmDialog } from "@/features/propiedades/components/DeleteConfirmDialog";
import type { CatalogoItem, CatalogoFormValues } from "@/features/propiedades/types";
import {
  useOccupantTypesQuery,
  useCreateOccupantTypeMutation,
  useUpdateOccupantTypeMutation,
  useDeleteOccupantTypeMutation,
} from "../api/occupant-types";

const ENTITY_NAME = "Tipo de ocupante";

/**
 * Página de administración de Tipos de Ocupante — ruta /catalogos/tipos-ocupante.
 * Muestra tabla con tipos (sistema + tenant), diálogo de crear/editar y confirmación de eliminar.
 * Reutiliza los componentes compartidos de catálogo de PROPIEDADES-B06 (misma forma exacta de
 * entidad — ver nota en DIRECTORIO-B05) en vez de reconstruirlos.
 * Consume LOCK-DIRECTORIO-01.
 */
export function TiposOcupantePage(): React.ReactNode {
  const { data: items = [], isLoading } = useOccupantTypesQuery();
  const createMutation = useCreateOccupantTypeMutation();
  const updateMutation = useUpdateOccupantTypeMutation();
  const deleteMutation = useDeleteOccupantTypeMutation();

  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingItem, setEditingItem] = useState<CatalogoItem | null>(null);
  const [deletingItem, setDeletingItem] = useState<CatalogoItem | null>(null);
  const [deleteWarning, setDeleteWarning] = useState<string | undefined>();

  // ── Handlers ─────────────────────────────────────────────────────────

  const handleCreate = useCallback(() => {
    setEditingItem(null);
    setDialogOpen(true);
  }, []);

  const handleEdit = useCallback((item: CatalogoItem) => {
    setEditingItem(item);
    setDialogOpen(true);
  }, []);

  const handleDelete = useCallback((item: CatalogoItem) => {
    setDeletingItem(item);
    setDeleteWarning(undefined);
  }, []);

  const handleDialogSubmit = useCallback(
    (values: CatalogoFormValues) => {
      const payload = {
        nombre: values.nombre,
        descripcion: values.descripcion || undefined,
      };

      if (editingItem) {
        updateMutation.mutate(
          { id: editingItem.id, data: payload },
          {
            onSuccess: () => setDialogOpen(false),
          },
        );
      } else {
        createMutation.mutate(payload, {
          onSuccess: () => setDialogOpen(false),
        });
      }
    },
    [editingItem, createMutation, updateMutation],
  );

  const handleDeleteConfirm = useCallback(() => {
    if (!deletingItem) return;

    deleteMutation.mutate(deletingItem.id, {
      onSuccess: () => setDeletingItem(null),
      onError: (error) => {
        // Si es 409 IN_USE, mostramos el warning en el diálogo en vez de cerrar
        if (
          error.code === "OCCUPANT_TYPE_IN_USE" ||
          error.message.includes("en uso")
        ) {
          setDeleteWarning(
            error.message || "No se puede eliminar: está en uso por ocupantes.",
          );
        } else {
          // Otros errores cierran el diálogo (el toast ya lo muestra el hook)
          setDeletingItem(null);
        }
      },
    });
  }, [deletingItem, deleteMutation]);

  const isSubmitting = createMutation.isPending || updateMutation.isPending;

  // ── Render ──────────────────────────────────────────────────────────

  return (
    <>
      <CatalogoTable
        items={items}
        isLoading={isLoading}
        title="Tipos de Ocupante"
        onCreate={handleCreate}
        onEdit={handleEdit}
        onDelete={handleDelete}
      />

      <CatalogoDialog
        key={editingItem?.id ?? "new"}
        open={dialogOpen}
        onOpenChange={setDialogOpen}
        item={editingItem}
        isSubmitting={isSubmitting}
        onSubmit={handleDialogSubmit}
        entityName={ENTITY_NAME}
      />

      <DeleteConfirmDialog
        open={deletingItem !== null}
        onOpenChange={(open) => {
          if (!open) setDeletingItem(null);
        }}
        item={deletingItem}
        isDeleting={deleteMutation.isPending}
        onConfirm={handleDeleteConfirm}
        entityName={ENTITY_NAME}
        warningMessage={deleteWarning}
      />
    </>
  );
}
