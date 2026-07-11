import type { ReactNode } from "react";

/**
 * Fallback de <Suspense> estándar para rutas lazy-loaded. Reemplaza el
 * mismo bloque `<div>Cargando...</div>` duplicado 10 veces en App.tsx.
 */
export function RouteSuspenseFallback(): ReactNode {
  return (
    <div className="flex min-h-screen items-center justify-center">
      <p className="text-muted-foreground">Cargando...</p>
    </div>
  );
}
