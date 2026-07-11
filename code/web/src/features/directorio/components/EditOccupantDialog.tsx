import { type ReactNode, useState, useEffect } from "react";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Loader2 } from "lucide-react";
import { useOccupantTypesQuery } from "../api/occupant-types";
import type { PropertyOccupantItem } from "../types";

interface EditOccupantDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  item: PropertyOccupantItem | null;
  isSubmitting: boolean;
  onSubmit: (values: { occupant_type_id: string; es_principal: boolean }) => void;
}

/**
 * Diálogo "Editar ocupante": cambia el tipo de ocupante y/o el indicador de
 * principal de una asignación existente. El contacto no es editable — para
 * cambiar de contacto hay que desasignar y volver a asignar.
 */
export function EditOccupantDialog({
  open,
  onOpenChange,
  item,
  isSubmitting,
  onSubmit,
}: EditOccupantDialogProps): ReactNode {
  const { data: occupantTypes = [] } = useOccupantTypesQuery();
  const occupantTypeOptions = occupantTypes.map((t) => ({
    value: t.id,
    label: t.nombre,
  }));

  const [occupantTypeId, setOccupantTypeId] = useState("");
  const [esPrincipal, setEsPrincipal] = useState(false);

  useEffect(() => {
    if (item) {
      setOccupantTypeId(item.occupant_type_id);
      setEsPrincipal(item.es_principal);
    }
  }, [item]);

  if (!item) return null;

  function handleSubmit(): void {
    onSubmit({ occupant_type_id: occupantTypeId, es_principal: esPrincipal });
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[440px]">
        <DialogHeader>
          <DialogTitle>Editar ocupante</DialogTitle>
          <DialogDescription>
            Modifica el tipo de ocupante de{" "}
            <span className="font-medium text-foreground">
              {item.contact?.nombre ?? "este contacto"}
            </span>
            .
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="edit-occupant-type">Tipo de ocupante</Label>
            <Select
              id="edit-occupant-type"
              options={occupantTypeOptions}
              value={occupantTypeId}
              onChange={(e) => setOccupantTypeId(e.target.value)}
              disabled={isSubmitting}
            />
          </div>

          <div className="flex items-center gap-2">
            <Checkbox
              id="edit-es-principal"
              checked={esPrincipal}
              onChange={() => setEsPrincipal((v) => !v)}
              disabled={isSubmitting}
            />
            <Label htmlFor="edit-es-principal" className="font-normal">
              Marcar como ocupante principal
            </Label>
          </div>
        </div>

        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            onClick={() => onOpenChange(false)}
            disabled={isSubmitting}
          >
            Cancelar
          </Button>
          <Button
            type="button"
            onClick={handleSubmit}
            disabled={!occupantTypeId || isSubmitting}
          >
            {isSubmitting ? (
              <>
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                Guardando...
              </>
            ) : (
              "Guardar"
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
