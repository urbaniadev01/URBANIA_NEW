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
import type { ContactItem } from "../types";

interface ContactDeleteDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  item: ContactItem | null;
  isDeleting: boolean;
  onConfirm: () => void;
  /** Mensaje contextual adicional (ej. 409 CONTACT_HAS_OCCUPATIONS) */
  warningMessage?: string;
}

/**
 * Diálogo de confirmación antes de eliminar un contacto — mismo patrón que
 * DeleteConfirmDialog de PROPIEDADES-B06, adaptado a ContactItem (forma
 * distinta: incluye email/telefono/user_id, no descripcion).
 */
export function ContactDeleteDialog({
  open,
  onOpenChange,
  item,
  isDeleting,
  onConfirm,
  warningMessage,
}: ContactDeleteDialogProps): ReactNode {
  if (!item) return null;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[440px]">
        <DialogHeader>
          <div className="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-destructive/10">
            <AlertTriangle className="h-6 w-6 text-destructive" />
          </div>
          <DialogTitle className="text-center">Eliminar contacto</DialogTitle>
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
          <Button variant="destructive" onClick={onConfirm} disabled={isDeleting}>
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
