import { type ReactNode } from "react";
import { Button } from "@/components/ui/button";
import { DashboardGrid } from "@/features/dashboard/components/DashboardGrid";
import { useDashboardWidgets } from "@/features/dashboard/hooks/useDashboardWidgets";
import { useAppUser } from "@/app/app-user-context";

/**
 * DashboardPage — página principal del panel, ruta / y /dashboard.
 *
 * El usuario (GET /auth/me, LOCK-AUTH-10) se resuelve una única vez en
 * AppLayout y llega acá vía useAppUser() (contexto de <Outlet/>) — esta
 * página ya no instancia su propio DashboardShell ni su propia query de
 * usuario (ver plan de rediseño, Fase 1).
 */
export function DashboardPage(): ReactNode {
  const { user, isLoading: userLoading, isError: userError, refetchUser } =
    useAppUser();

  const { widgets, isLoading: widgetsLoading } = useDashboardWidgets(user);

  const isLoading = userLoading || widgetsLoading;

  // ── Estado de error ───────────────────────────────────────────────
  if (userError) {
    return (
      <div className="container mx-auto max-w-[1400px] px-4 py-8 sm:px-6 lg:px-8">
        <div className="flex flex-col items-center justify-center gap-4 py-20">
          <div className="rounded-full bg-destructive/10 p-4">
            <svg
              className="h-8 w-8 text-destructive"
              fill="none"
              viewBox="0 0 24 24"
              strokeWidth={1.5}
              stroke="currentColor"
              aria-hidden="true"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"
              />
            </svg>
          </div>
          <p className="text-lg font-medium text-destructive">
            Error al cargar perfil
          </p>
          <p className="text-sm text-muted-foreground">
            No se pudo obtener tu información. Verifica tu conexión e intenta
            de nuevo.
          </p>
          <Button variant="outline" onClick={refetchUser}>
            Reintentar
          </Button>
        </div>
      </div>
    );
  }

  // ── Estado de carga ───────────────────────────────────────────────
  if (isLoading) {
    return (
      <div className="container mx-auto max-w-[1400px] px-4 py-8 sm:px-6 lg:px-8">
        <div className="mb-8">
          <div className="h-8 w-24 animate-pulse rounded-md bg-muted" />
          <div className="mt-2 h-4 w-64 animate-pulse rounded-md bg-muted" />
        </div>
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {[1, 2, 3].map((i) => (
            <div
              key={i}
              className="h-48 animate-pulse rounded-lg border bg-card"
            />
          ))}
        </div>
      </div>
    );
  }

  // ── Dashboard poblado ─────────────────────────────────────────────
  return (
    <div className="container mx-auto max-w-[1400px] px-4 py-8 sm:px-6 lg:px-8">
      <div className="mb-8">
        <h1 className="text-2xl font-semibold tracking-tight">Panel</h1>
        <p className="text-sm text-muted-foreground">
          {user
            ? "Resumen de tu actividad y accesos rápidos"
            : "Cargando tu información..."}
        </p>
      </div>

      <DashboardGrid widgets={widgets} isLoading={isLoading} user={user} />
    </div>
  );
}
