import { useState, useCallback } from "react";
import { CatalogoTable } from "../components/CatalogoTable";
import { CatalogoDialog } from "../components/CatalogoDialog";
import { DeleteConfirmDialog } from "../components/DeleteConfirmDialog";
import type { CatalogoItem, CatalogoFormValues } from "../types";
import {
  usePropertyStatusesQuery,
  useCreatePropertyStatusMutation,
  useUpdatePropertyStatusMutation,
  useDeletePropertyStatusMutation,
} from "../api/property-statuses";

const ENTITY_NAME = "Estado de propiedad";

/**
 * Página de administración de Estados de Propiedad — ruta /catalogos/estados-propiedad.
 * Misma estructura que TiposPropiedadPage: tabla, diálogo create/edit, confirmación delete.
 * Consume LOCK-PROPIEDADES-01.
 */
export function EstadosPropiedadPage(): React.ReactNode {
  const { data: items = [], isLoading } = usePropertyStatusesQuery();
  const createMutation = useCreatePropertyStatusMutation();
  const updateMutation = useUpdatePropertyStatusMutation();
  const deleteMutation = useDeletePropertyStatusMutation();

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
        if (
          error.code === "PROPERTY_STATUS_IN_USE" ||
          error.message.includes("en uso")
        ) {
          setDeleteWarning(
            error.message || "No se puede eliminar: está en uso por propiedades.",
          );
        } else {
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
        title="Estados de Propiedad"
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
