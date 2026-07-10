import type { HTMLAttributes, ReactNode } from "react";
import { cn } from "@/lib/utils";

/**
 * Skeleton — placeholder animado para estados de carga.
 *
 * Usado por WidgetSkeleton y cualquier componente que necesite
 * un placeholder mientras carga datos.
 *
 * Basado en shadcn/ui Skeleton: div con animate-pulse + rounded-md + bg-muted.
 */
function Skeleton({ className, ...props }: HTMLAttributes<HTMLDivElement>): ReactNode {
  return (
    <div
      className={cn("animate-pulse rounded-md bg-primary/10", className)}
      {...props}
    />
  );
}

export { Skeleton };
