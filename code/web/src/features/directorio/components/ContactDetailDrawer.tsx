import type { ReactNode } from "react";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { Badge } from "@/components/ui/badge";
import { LoadingState } from "@/components/loading-state";
import { Home } from "lucide-react";
import type { ContactItem } from "../types";
import { hasAccount } from "../types";
import { useContactPropertiesQuery } from "../api/contacts";

interface ContactDetailDrawerProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  item: ContactItem | null;
}

/**
 * Drawer de solo lectura con el detalle de un contacto: datos básicos +
 * unidades donde tiene una ocupación activa (GET /contacts/{id}/properties).
 * No permite asignar/desasignar unidades desde acá — eso es DIRECTORIO-B07.
 */
export function ContactDetailDrawer({
  open,
  onOpenChange,
  item,
}: ContactDetailDrawerProps): ReactNode {
  const { data, isLoading } = useContactPropertiesQuery(item?.id ?? null);
  const properties = data?.data ?? [];

  if (!item) return null;

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="sm:max-w-md">
        <SheetHeader>
          <SheetTitle>{item.nombre}</SheetTitle>
          <SheetDescription>Detalle del contacto</SheetDescription>
        </SheetHeader>

        <div className="mt-6 space-y-6">
          <div className="space-y-3">
            <div>
              <p className="text-xs font-medium text-muted-foreground">
                Vínculo
              </p>
              <Badge variant={hasAccount(item) ? "success" : "info"}>
                {hasAccount(item) ? "Con cuenta" : "Sin cuenta"}
              </Badge>
            </div>
            <div>
              <p className="text-xs font-medium text-muted-foreground">Email</p>
              <p className="text-sm">{item.email}</p>
            </div>
            <div>
              <p className="text-xs font-medium text-muted-foreground">
                Teléfono
              </p>
              <p className="text-sm">{item.telefono || "—"}</p>
            </div>
          </div>

          <div>
            <p className="mb-2 text-xs font-medium text-muted-foreground">
              Unidades asociadas
            </p>
            {isLoading ? (
              <LoadingState />
            ) : properties.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                Sin unidades asociadas.
              </p>
            ) : (
              <ul className="space-y-2">
                {properties.map((property) => (
                  <li
                    key={property.id}
                    className="flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
                  >
                    <Home className="h-4 w-4 shrink-0 text-muted-foreground" />
                    {property.codigo}
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      </SheetContent>
    </Sheet>
  );
}
