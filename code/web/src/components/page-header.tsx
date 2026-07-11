import type { ReactNode } from "react";

interface PageHeaderProps {
  title: ReactNode;
  description?: ReactNode;
  actions?: ReactNode;
}

/**
 * Header de página estándar: título + descripción a la izquierda,
 * acciones (ej. botón "Nuevo") a la derecha. Reemplaza el bloque
 * h1+subtítulo+botón duplicado en cada página de feature.
 */
export function PageHeader({
  title,
  description,
  actions,
}: PageHeaderProps): ReactNode {
  return (
    <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
        {description ? (
          <p className="text-sm text-muted-foreground">{description}</p>
        ) : null}
      </div>
      {actions ? <div className="flex items-center gap-2">{actions}</div> : null}
    </div>
  );
}
