import type { ReactNode } from "react";
import { Link } from "react-router-dom";
import { Button } from "@/components/ui/button";

/** Página 404 — mínima para MVP, ver web/WEB_VISUAL_STANDARDS.md §6. */
export function NotFoundPage(): ReactNode {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-4 bg-background px-4 text-center">
      <p className="text-sm font-semibold text-accent-brand">404</p>
      <h1 className="text-2xl font-semibold tracking-tight text-foreground">
        Página no encontrada
      </h1>
      <p className="max-w-sm text-sm text-muted-foreground">
        La página que buscás no existe o fue movida.
      </p>
      <Button asChild>
        <Link to="/">Volver al inicio</Link>
      </Button>
    </div>
  );
}
