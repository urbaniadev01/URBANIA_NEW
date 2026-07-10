import { type ReactNode, useState, useCallback } from "react";
import { Button } from "@/components/ui/button";
import { DashboardGrid } from "@/features/dashboard/components/DashboardGrid";
import { DashboardShell } from "@/components/layout/DashboardShell";
import { useDashboardWidgets } from "@/features/dashboard/hooks/useDashboardWidgets";
import { useUserQuery } from "@/features/dashboard/hooks/useUserQuery";
import type { AuthUser } from "@/features/dashboard/types";

/**
 * DashboardPage — página principal del panel, ruta / y /dashboard.
 *
 * Punto de entrada del dashboard. Resuelve el usuario vía GET /auth/me
 * (useUserQuery, LOCK-AUTH-10) y lo comparte con DashboardShell
 * (sidebar RBAC) y DashboardGrid (filtro de widgets).
 *
 * Estructura: DashboardShell > Header > Main > DashboardGrid
 * El header tiene un slot para WelcomeWidget (B03).
 *
 * Mecanismo de desarrollo: window.__dashboardSetUser se conserva para
 * que Playwright pueda inyectar un usuario de prueba sin depender de
 * /auth/me. El usuario dev-inyectado tiene prioridad sobre el de la API.
 */
export function DashboardPage(): ReactNode {
  const {
    data: apiUser,
    isLoading: userLoading,
    isError: userError,
    refetch: refetchUser,
  } = useUserQuery();

  // Dev override: __dashboardSetUser puede inyectar un usuario en DEV
  const [devUser, setDevUser] = useState<AuthUser | null>(null);

  // Usuario real: dev override (Playwright) > API (/auth/me)
  const user = devUser ?? apiUser ?? null;

  const { widgets, isLoading: widgetsLoading } = useDashboardWidgets(user);

  const isLoading = userLoading || widgetsLoading;

  const handleDevUserResolved = useCallback((resolvedUser: AuthUser) => {
    setDevUser(resolvedUser);
  }, []);

  // ── Estado de error ───────────────────────────────────────────────
  if (userError && !devUser) {
    return (
      <DashboardShell user={null} headerSlot={null}>
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
            <Button
              variant="outline"
              onClick={() => {
                void refetchUser();
              }}
            >
              Reintentar
            </Button>
          </div>
        </div>
      </DashboardShell>
    );
  }

  // ── Estado de carga ───────────────────────────────────────────────
  if (isLoading) {
    return (
      <DashboardShell user={null} headerSlot={null}>
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
      </DashboardShell>
    );
  }

  // ── Dashboard poblado ─────────────────────────────────────────────
  return (
    <DashboardShell
      user={user}
      headerSlot={
        /* Slot para WelcomeWidget — B03 renderiza aquí el saludo/fecha/KPIs */
        user ? (
          <span className="text-sm font-medium">Buenos días, {user.name}</span>
        ) : null
      }
    >
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

      {/*
        Mecanismo de desarrollo: expone handleDevUserResolved al window
        para que Playwright pueda inyectar un usuario de prueba.
        Se excluye del bundle de producción.
      */}
      {import.meta.env.DEV && (
        <DevUserInjector onUserResolved={handleDevUserResolved} />
      )}
    </DashboardShell>
  );
}

/**
 * Mecanismo de desarrollo para inyectar un usuario de prueba.
 * Permite que los tests de Playwright prueben el dashboard sin /auth/me.
 *
 * Expone window.__dashboardSetUser(user) que el test puede llamar para
 * establecer el usuario manualmente. El valor inyectado tiene prioridad
 * sobre el resultado de useUserQuery().
 */
function DevUserInjector({
  onUserResolved,
}: {
  onUserResolved: (user: AuthUser) => void;
}): ReactNode {
  if (typeof window !== "undefined") {
    (window as unknown as Record<string, unknown>).__dashboardSetUser =
      onUserResolved;
  }

  return null;
}
