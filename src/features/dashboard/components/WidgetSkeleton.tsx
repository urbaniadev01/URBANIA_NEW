import { Skeleton } from "@/components/ui/skeleton";

/**
 * WidgetSkeleton — placeholder animado para el estado de carga de un widget.
 *
 * Reglas (PANORAMA §8.2):
 * - animate-pulse (proporcionado por Skeleton de shadcn/ui)
 * - aria-hidden="true" — el skeleton no aporta información al screen reader
 * - Sin spinner global — cada widget tiene su propio skeleton
 * - Dimensiones proporcionales al tamaño del widget
 */
export function WidgetSkeleton(): React.ReactNode {
  return (
    <div aria-hidden="true" className="space-y-3">
      {/* Título */}
      <Skeleton className="h-5 w-2/5" />
      {/* Descripción */}
      <Skeleton className="h-4 w-4/5" />
      {/* Contenido — 3 líneas */}
      <div className="space-y-2 pt-2">
        <Skeleton className="h-4 w-full" />
        <Skeleton className="h-4 w-3/4" />
        <Skeleton className="h-4 w-1/2" />
      </div>
    </div>
  );
}
