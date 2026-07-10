import type { ReactNode } from "react";

/**
 * Badge fijo visible solo en modo desarrollo.
 * Montado vía `React.lazy()` condicional en `App.tsx`:
 *   import.meta.env.DEV ? lazy(() => import("./DevIndicator")) : null
 *
 * Vite tree-shakea este módulo completo en build de producción
 * porque el import condicional nunca se resuelve en PROD.
 *
 * Sin interactividad por ahora — futuros bloques (AUTH-B06, etc.)
 * agregan acciones de conveniencia en este mismo punto de montaje.
 */
export function DevIndicator(): ReactNode {
  return (
    <div
      role="status"
      aria-label="Modo desarrollo"
      className="fixed bottom-4 right-4 z-[9999] select-none rounded-md bg-destructive px-3 py-1.5 font-mono text-xs font-semibold text-destructive-foreground shadow-lg"
    >
      DEV
    </div>
  );
}
