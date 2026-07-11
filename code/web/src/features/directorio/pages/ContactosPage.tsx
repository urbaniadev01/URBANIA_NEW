import { useState, useCallback, useRef, useEffect } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Pencil, Plus, Trash2, Search, Users } from "lucide-react";
import { PageHeader } from "@/components/page-header";
import { EmptyState } from "@/components/empty-state";
import { LoadingState } from "@/components/loading-state";
import { PAGE_CONTAINER } from "@/lib/layout";
import { ContactSheet } from "../components/ContactSheet";
import { ContactDeleteDialog } from "../components/ContactDeleteDialog";
import { ContactDetailDrawer } from "../components/ContactDetailDrawer";
import type { ContactItem, ContactFormValues } from "../types";
import { hasAccount } from "../types";
import {
  useContactsQuery,
  useCreateContactMutation,
  useUpdateContactMutation,
  useDeleteContactMutation,
} from "../api/contacts";

/**
 * Página de directorio de contactos — ruta /directorio/contactos.
 * Tabla con búsqueda server-side, Sheet de crear/editar, diálogo de
 * confirmación de eliminar y drawer de detalle (click en fila).
 * Consume LOCK-DIRECTORIO-02.
 */
export function ContactosPage(): React.ReactNode {
  // ── Búsqueda con debounce (server-side, ?search=) ──────────────────────
  const [searchInput, setSearchInput] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const handleSearchChange = useCallback((value: string) => {
    setSearchInput(value);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      setDebouncedSearch(value);
    }, 300);
  }, []);

  useEffect(() => {
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, []);

  const { data, isLoading } = useContactsQuery(debouncedSearch);
  const items = data?.data ?? [];

  const createMutation = useCreateContactMutation();
  const updateMutation = useUpdateContactMutation();
  const deleteMutation = useDeleteContactMutation();

  const [sheetOpen, setSheetOpen] = useState(false);
  const [editingItem, setEditingItem] = useState<ContactItem | null>(null);
  const [deletingItem, setDeletingItem] = useState<ContactItem | null>(null);
  const [deleteWarning, setDeleteWarning] = useState<string | undefined>();
  const [detailItem, setDetailItem] = useState<ContactItem | null>(null);

  // ── Handlers ─────────────────────────────────────────────────────────

  const handleCreate = useCallback(() => {
    setEditingItem(null);
    setSheetOpen(true);
  }, []);

  const handleEdit = useCallback((item: ContactItem) => {
    setEditingItem(item);
    setSheetOpen(true);
  }, []);

  const handleDelete = useCallback((item: ContactItem) => {
    setDeletingItem(item);
    setDeleteWarning(undefined);
  }, []);

  const handleRowClick = useCallback((item: ContactItem) => {
    setDetailItem(item);
  }, []);

  const handleSheetSubmit = useCallback(
    (values: ContactFormValues) => {
      const payload = {
        nombre: values.nombre,
        email: values.email,
        telefono: values.telefono || undefined,
      };

      if (editingItem) {
        updateMutation.mutate(
          { id: editingItem.id, data: payload },
          { onSuccess: () => setSheetOpen(false) },
        );
      } else {
        createMutation.mutate(payload, {
          onSuccess: () => setSheetOpen(false),
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
          error.code === "CONTACT_HAS_OCCUPATIONS" ||
          error.message.includes("unidades asignadas")
        ) {
          setDeleteWarning(
            error.message ||
              "Este contacto tiene unidades asignadas, quítalas primero.",
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
    <div className={PAGE_CONTAINER}>
      <PageHeader
        title="Contactos"
        description="Directorio de personas asociadas a tu organización."
        actions={
          <Button onClick={handleCreate}>
            <Plus className="mr-2 h-4 w-4" />
            Nuevo contacto
          </Button>
        }
      />

      <div className="relative mb-6">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          placeholder="Buscar por nombre..."
          value={searchInput}
          onChange={(e) => handleSearchChange(e.target.value)}
          className="pl-9"
        />
      </div>

      {isLoading ? (
        <LoadingState />
      ) : items.length === 0 ? (
        <EmptyState
          icon={Users}
          message={
            searchInput
              ? `No se encontraron contactos con "${searchInput}".`
              : "No hay contactos registrados."
          }
          action={
            searchInput ? (
              <Button variant="outline" onClick={() => handleSearchChange("")}>
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
        <div className="rounded-md border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Nombre</TableHead>
                <TableHead className="w-[130px]">Vínculo</TableHead>
                <TableHead className="w-[120px]">Unidades</TableHead>
                <TableHead className="w-[120px] text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {items.map((item) => (
                <TableRow
                  key={item.id}
                  className="cursor-pointer"
                  onClick={() => handleRowClick(item)}
                >
                  <TableCell className="font-medium">{item.nombre}</TableCell>
                  <TableCell>
                    {hasAccount(item) ? (
                      <Badge variant="success">Con cuenta</Badge>
                    ) : (
                      <Badge variant="info">Sin cuenta</Badge>
                    )}
                  </TableCell>
                  <TableCell>
                    <Button
                      variant="link"
                      size="sm"
                      className="h-auto p-0"
                      onClick={(e) => {
                        e.stopPropagation();
                        handleRowClick(item);
                      }}
                    >
                      Ver unidades
                    </Button>
                  </TableCell>
                  <TableCell className="text-right">
                    <div className="flex items-center justify-end gap-1">
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={(e) => {
                          e.stopPropagation();
                          handleEdit(item);
                        }}
                        title="Editar"
                      >
                        <Pencil className="h-4 w-4" />
                        <span className="sr-only">Editar</span>
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={(e) => {
                          e.stopPropagation();
                          handleDelete(item);
                        }}
                        title="Eliminar"
                        className="text-destructive hover:text-destructive"
                      >
                        <Trash2 className="h-4 w-4" />
                        <span className="sr-only">Eliminar</span>
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}

      <ContactSheet
        key={editingItem?.id ?? "new"}
        open={sheetOpen}
        onOpenChange={setSheetOpen}
        item={editingItem}
        isSubmitting={isSubmitting}
        onSubmit={handleSheetSubmit}
      />

      <ContactDeleteDialog
        open={deletingItem !== null}
        onOpenChange={(open) => {
          if (!open) setDeletingItem(null);
        }}
        item={deletingItem}
        isDeleting={deleteMutation.isPending}
        onConfirm={handleDeleteConfirm}
        warningMessage={deleteWarning}
      />

      <ContactDetailDrawer
        open={detailItem !== null}
        onOpenChange={(open) => {
          if (!open) setDetailItem(null);
        }}
        item={detailItem}
      />
    </div>
  );
}
