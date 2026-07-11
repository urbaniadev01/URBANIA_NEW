import type { ReactNode } from "react";
import { Loader2 } from "lucide-react";
import { cn } from "@/lib/utils";

interface LoadingStateProps {
  className?: string;
}

/**
 * Spinner centrado estándar para estados de carga de página/sección.
 * Reemplaza el `<Loader2 className="..." />` centrado duplicado en cada
 * página.
 */
export function LoadingState({ className }: LoadingStateProps): ReactNode {
  return (
    <div className={cn("flex items-center justify-center py-16", className)}>
      <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
    </div>
  );
}
