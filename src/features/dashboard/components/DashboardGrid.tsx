import { type ReactNode, Suspense } from "react";
import type { AuthUser, WidgetDefinition } from "@/features/dashboard/types";
import { useIntersectionObserver } from "@/hooks/useIntersectionObserver";
import { WidgetCard } from "@/features/dashboard/components/WidgetCard";
import { PackageOpen } from "lucide-react";

interface DashboardGridProps {
  widgets: WidgetDefinition[];
  isLoading: boolean;
  /** Usuario autenticado — pasado como prop a cada widget. */
  user: AuthUser | null;
}

/**
 * DashboardGrid — grilla responsive de widgets con lazy loading progresivo.
 *
 * Layout (PANORAMA §8.5):
 * - >= 1024px (lg): 3 columnas
 * - >= 768px (md): 2 columnas
 * - < 768px (sm): 1 columna
 *
 * Lazy loading (PANORAMA §7.5):
 * - IntersectionObserver con rootMargin: 200px
 * - Widgets fuera del viewport no cargan su chunk JS
 * - Solo los 3-4 widgets visibles inicialmente descargan su código
 */
export function DashboardGrid({
  widgets,
  isLoading,
  user,
}: DashboardGridProps): ReactNode {
  // Estado de carga global (ventana de permisos no resueltos)
  if (isLoading) {
    return (
      <div
        className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3"
        role="list"
        aria-label="Cargando widgets"
      >
        {[1, 2, 3].map((i) => (
          <div key={i} role="listitem">
            <WidgetCard title="" state={{ status: "loading" }}>
              {null}
            </WidgetCard>
          </div>
        ))}
      </div>
    );
  }

  // Estado vacío — sin widgets registrados o sin permisos
  if (widgets.length === 0) {
    return (
      <div
        role="status"
        className="flex flex-col items-center justify-center gap-4 py-16 text-center"
      >
        <PackageOpen
          className="h-16 w-16 text-muted-foreground"
          style={{ opacity: 0.3 }}
          aria-hidden="true"
        />
        <p className="text-lg font-medium text-muted-foreground">
          No hay widgets disponibles
        </p>
        <p className="text-sm text-muted-foreground">
          Los widgets aparecerán aquí cuando tengas acceso a funcionalidades del
          sistema.
        </p>
      </div>
    );
  }

  return (
    <div
      className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3"
      role="list"
      aria-label="Widgets del panel"
    >
      {widgets.map((widget) => (
        <LazyWidgetSlot key={widget.id} widget={widget} user={user} />
      ))}
    </div>
  );
}

/**
 * Slot individual con IntersectionObserver.
 * Solo carga el componente real cuando está a 200px de entrar al viewport.
 */
function LazyWidgetSlot({
  widget,
  user,
}: {
  widget: WidgetDefinition;
  user: AuthUser | null;
}): ReactNode {
  const { ref, isIntersecting } = useIntersectionObserver({
    rootMargin: "200px",
    triggerOnce: true,
  });

  // Placeholder mientras no es visible — evita CLS (Cumulative Layout Shift)
  const spanClass = getSpanClass(widget.size);

  // Usuario mínimo para el widget — en producción vendrá de /auth/me.
  // Si user es null, se pasa un usuario vacío (el widget no debería renderizarse
  // en ese caso porque isLoading sería true en el padre, pero es una defensa).
  const widgetUser: AuthUser = user ?? {
    id: "",
    email: "",
    name: "",
    role: "user",
    permissions: [],
  };

  return (
    <div ref={ref} className={spanClass} role="listitem">
      {isIntersecting ? (
        <Suspense
          fallback={
            <WidgetCard title={widget.title} state={{ status: "loading" }}>
              {null}
            </WidgetCard>
          }
        >
          <widget.component user={widgetUser} />
        </Suspense>
      ) : (
        <WidgetCard title={widget.title} state={{ status: "loading" }}>
          {null}
        </WidgetCard>
      )}
    </div>
  );
}

/**
 * Determina cuántas columnas ocupa un widget según su size.
 * - sm/md: 1 columna (default)
 * - lg: 2 columnas en desktop
 * - full: 3 columnas (todo el ancho)
 */
function getSpanClass(size: WidgetDefinition["size"]): string {
  switch (size) {
    case "full":
      return "lg:col-span-3 md:col-span-2";
    case "lg":
      return "lg:col-span-2";
    case "md":
    case "sm":
    default:
      return "";
  }
}
