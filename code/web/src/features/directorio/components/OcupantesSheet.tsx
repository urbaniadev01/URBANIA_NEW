import { type ReactNode, useState, useCallback } from "react";
import { Button } from "@/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Badge } from "@/components/ui/badge";
import { LoadingState } from "@/components/loading-state";
import { EmptyState } from "@/components/empty-state";
import { Plus, Pencil, Trash2, Users, AlertTriangle, Loader2 } from "lucide-react";
import { AssignOccupantDialog } from "./AssignOccupantDialog";
import { EditOccupantDialog } from "./EditOccupantDialog";
import {
  usePropertyOccupantsQuery,
  useAssignOccupantMutation,
  useUpdatePropertyOccupantMutation,
  useUnassignOccupantMutation,
} from "../api/property-occupants";
import type { PropertyOccupantItem } from "../types";

interface OcupantesSheetProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  propertyId: string | null;
  propertyCodigo: string;
}

/**
 * Sheet de ocupantes de una unidad — sección insertada dentro del flujo de
 * gestión de unidades de PROPIEDADES-B08 (ver nota de alcance en la tarjeta:
 * no existe una ruta de detalle de unidad dedicada, se ofrece como acción por
 * fila en UnidadesTab). Consume LOCK-DIRECTORIO-03 (escritura) y, de forma
 * read-only, LOCK-DIRECTORIO-01/02 para poblar los selectores.
 */
export function OcupantesSheet({
  open,
  onOpenChange,
  propertyId,
  propertyCodigo,
}: OcupantesSheetProps): ReactNode {
  const { data, isLoading } = usePropertyOccupantsQuery(propertyId);
  const occupants = data?.data ?? [];

  const assignMutation = useAssignOccupantMutation(propertyId);
  const updateMutation = useUpdatePropertyOccupantMutation(propertyId);
  const unassignMutation = useUnassignOccupantMutation(propertyId);

  const [assignOpen, setAssignOpen] = useState(false);
  const [editingItem, setEditingItem] = useState<PropertyOccupantItem | null>(null);
  const [unassigningItem, setUnassigningItem] = useState<PropertyOccupantItem | null>(
    null,
  );

  const handleAssignSubmit = useCallback(
    (values: { contact_id: string; occupant_type_id: string; es_principal: boolean }) => {
      assignMutation.mutate(values, {
        onSuccess: () => setAssignOpen(false),
      });
    },
    [assignMutation],
  );

  const handleEditSubmit = useCallback(
    (values: { occupant_type_id: string; es_principal: boolean }) => {
      if (!editingItem) return;
      updateMutation.mutate(
        { id: editingItem.id, data: values },
        { onSuccess: () => setEditingItem(null) },
      );
    },
    [editingItem, updateMutation],
  );

  const handleUnassignConfirm = useCallback(() => {
    if (!unassigningItem) return;
    unassignMutation.mutate(unassigningItem.id, {
      onSettled: () => setUnassigningItem(null),
    });
  }, [unassigningItem, unassignMutation]);

  return (
    <>
      <Sheet open={open} onOpenChange={onOpenChange}>
        <SheetContent side="right" className="sm:max-w-lg">
          <SheetHeader>
            <SheetTitle>Ocupantes de {propertyCodigo}</SheetTitle>
            <SheetDescription>
              Personas asignadas a esta unidad, con su tipo de ocupante.
            </SheetDescription>
          </SheetHeader>

          <div className="mt-6 space-y-4">
            <div className="flex justify-end">
              <Button size="sm" onClick={() => setAssignOpen(true)}>
                <Plus className="mr-1.5 h-4 w-4" />
                Asignar ocupante
              </Button>
            </div>

            {isLoading ? (
              <LoadingState />
            ) : occupants.length === 0 ? (
              <EmptyState
                icon={Users}
                message="No hay ocupantes asignados a esta unidad."
              />
            ) : (
              <ul className="space-y-2">
                {occupants.map((occupant) => (
                  <li
                    key={occupant.id}
                    className="flex items-center justify-between rounded-md border px-3 py-2"
                  >
                    <div>
                      <div className="flex items-center gap-2">
                        <span className="text-sm font-medium">
                          {occupant.contact?.nombre ?? "Contacto"}
                        </span>
                        {occupant.es_principal ? (
                          <Badge variant="success">Principal</Badge>
                        ) : null}
                      </div>
                      <p className="text-xs text-muted-foreground">
                        {occupant.occupant_type?.nombre ?? "—"}
                      </p>
                    </div>
                    <div className="flex items-center gap-1">
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => setEditingItem(occupant)}
                        title="Editar"
                      >
                        <Pencil className="h-4 w-4" />
                        <span className="sr-only">Editar</span>
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => setUnassigningItem(occupant)}
                        title="Desasignar"
                        className="text-destructive hover:text-destructive"
                      >
                        <Trash2 className="h-4 w-4" />
                        <span className="sr-only">Desasignar</span>
                      </Button>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </SheetContent>
      </Sheet>

      <AssignOccupantDialog
        open={assignOpen}
        onOpenChange={setAssignOpen}
        isSubmitting={assignMutation.isPending}
        onSubmit={handleAssignSubmit}
      />

      <EditOccupantDialog
        open={editingItem !== null}
        onOpenChange={(o) => {
          if (!o) setEditingItem(null);
        }}
        item={editingItem}
        isSubmitting={updateMutation.isPending}
        onSubmit={handleEditSubmit}
      />

      <Dialog
        open={unassigningItem !== null}
        onOpenChange={(o) => {
          if (!o) setUnassigningItem(null);
        }}
      >
        <DialogContent className="sm:max-w-[440px]">
          <DialogHeader>
            <div className="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-destructive/10">
              <AlertTriangle className="h-6 w-6 text-destructive" />
            </div>
            <DialogTitle className="text-center">Desasignar ocupante</DialogTitle>
            <DialogDescription className="text-center">
              ¿Estás seguro de desasignar a{" "}
              <span className="font-semibold text-foreground">
                &quot;{unassigningItem?.contact?.nombre ?? "este contacto"}&quot;
              </span>{" "}
              de esta unidad?
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setUnassigningItem(null)}
              disabled={unassignMutation.isPending}
            >
              Cancelar
            </Button>
            <Button
              variant="destructive"
              onClick={handleUnassignConfirm}
              disabled={unassignMutation.isPending}
            >
              {unassignMutation.isPending ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Desasignando...
                </>
              ) : (
                "Desasignar"
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
