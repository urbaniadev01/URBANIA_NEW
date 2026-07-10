import type { ReactNode } from "react";
import { Building2, MapPin, Hash } from "lucide-react";
import { Card, CardContent } from "@/components/ui/card";
import type { CondominioItem } from "../types";

interface CondominioCardProps {
  condominio: CondominioItem;
  torresCount?: number;
  onClick: () => void;
}

/**
 * Card de condominio para el grid de la lista.
 * Muestra nombre, dirección, NIT y conteo de torres (si está disponible).
 */
export function CondominioCard({
  condominio,
  torresCount,
  onClick,
}: CondominioCardProps): ReactNode {
  return (
    <Card
      className="cursor-pointer transition-shadow hover:shadow-md"
      onClick={onClick}
      role="button"
      tabIndex={0}
      onKeyDown={(e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          onClick();
        }
      }}
    >
      <CardContent className="p-5">
        <div className="flex items-start gap-3">
          <div className="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
            <Building2 className="h-5 w-5 text-primary" />
          </div>
          <div className="min-w-0 flex-1">
            <h3 className="truncate text-base font-semibold">
              {condominio.nombre}
            </h3>
            {condominio.direccion ? (
              <p className="mt-1 flex items-center gap-1.5 text-sm text-muted-foreground">
                <MapPin className="h-3.5 w-3.5 shrink-0" />
                <span className="truncate">{condominio.direccion}</span>
              </p>
            ) : null}
            {condominio.nit ? (
              <p className="mt-0.5 flex items-center gap-1.5 text-sm text-muted-foreground">
                <Hash className="h-3.5 w-3.5 shrink-0" />
                <span className="truncate">{condominio.nit}</span>
              </p>
            ) : null}
            {torresCount !== undefined ? (
              <p className="mt-2 text-xs text-muted-foreground">
                {torresCount === 1
                  ? "1 torre"
                  : `${torresCount} torres`}
              </p>
            ) : null}
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
