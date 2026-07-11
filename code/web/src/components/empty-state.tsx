import type { ReactNode } from "react";
import type { LucideIcon } from "lucide-react";

interface EmptyStateProps {
  icon: LucideIcon;
  message: ReactNode;
  action?: ReactNode;
}

/**
 * Estado vacío estándar (borde punteado + ícono + mensaje + acción
 * opcional). Reemplaza el bloque duplicado en cada página de lista/tabla.
 */
export function EmptyState({ icon: Icon, message, action }: EmptyStateProps): ReactNode {
  return (
    <div className="flex flex-col items-center justify-center rounded-lg border border-dashed py-16 text-center">
      <Icon className="mb-4 h-10 w-10 text-muted-foreground/60" />
      <div className="text-sm text-muted-foreground">{message}</div>
      {action ? <div className="mt-4">{action}</div> : null}
    </div>
  );
}
