import type { ReactNode } from "react";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { AlertTriangle, Loader2 } from "lucide-react";
import type { CatalogoItem } from "../types";

interface DeleteConfirmDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  item: CatalogoItem | null;
  isDeleting: boolean;
  onConfirm: () => void;
  entityName: string;
  /** Mensaje contextual adicional (ej. "Este tipo está en uso por 5 propiedades") */
  warningMessage?: string;
}

/**
 * Diálogo de confirmación antes de eliminar un catálogo.
 * Muestra el nombre del elemento y un warning si viene del contexto de error (409).
 */
export function DeleteConfirmDialog({
  open,
  onOpenChange,
  item,
  isDeleting,
  onConfirm,
  entityName,
  warningMessage,
}: DeleteConfirmDialogProps): ReactNode {
  if (!item) return null;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[440px]">
        <DialogHeader>
          <div className="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-destructive/10">
            <AlertTriangle className="h-6 w-6 text-destructive" />
          </div>
          <DialogTitle className="text-center">
            Eliminar {entityName.toLowerCase()}
          </DialogTitle>
          <DialogDescription className="text-center">
            ¿Estás seguro de eliminar{" "}
            <span className="font-semibold text-foreground">
              &quot;{item.nombre}&quot;
            </span>
            ? Esta acción no se puede deshacer.
          </DialogDescription>
        </DialogHeader>

        {warningMessage ? (
          <div className="rounded-md border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
            {warningMessage}
          </div>
        ) : null}

        <DialogFooter>
          <Button
            variant="outline"
            onClick={() => onOpenChange(false)}
            disabled={isDeleting}
          >
            Cancelar
          </Button>
          <Button
            variant="destructive"
            onClick={onConfirm}
            disabled={isDeleting}
          >
            {isDeleting ? (
              <>
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                Eliminando...
              </>
            ) : (
              "Eliminar"
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
