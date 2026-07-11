import { type ReactNode, useState, useCallback } from "react";
import { Outlet } from "react-router-dom";
import { DashboardShell } from "@/components/layout/DashboardShell";
import { useUserQuery } from "@/features/dashboard/hooks/useUserQuery";
import type { AuthUser } from "@/features/dashboard/types";
import type { AppOutletContext } from "@/app/app-user-context";

/**
 * AppLayout — única instanciación de DashboardShell para todas las rutas
 * privadas. Antes, solo DashboardPage se envolvía a sí misma en
 * DashboardShell; el resto de páginas privadas (PROPIEDADES) no tenían
 * sidebar/header en absoluto. Ver plan de rediseño — Fase 1.
 */
export function AppLayout(): ReactNode {
  const {
    data: apiUser,
    isLoading: userLoading,
    isError: userError,
    refetch: refetchUser,
  } = useUserQuery();

  // Dev override: __dashboardSetUser puede inyectar un usuario en DEV
  // (mecanismo conservado desde DashboardPage — ver DevUserInjector abajo).
  const [devUser, setDevUser] = useState<AuthUser | null>(null);

  const user = devUser ?? apiUser ?? null;

  const handleDevUserResolved = useCallback((resolvedUser: AuthUser) => {
    setDevUser(resolvedUser);
  }, []);

  const context: AppOutletContext = {
    user,
    isLoading: userLoading,
    isError: userError,
    refetchUser: () => {
      void refetchUser();
    },
  };

  return (
    <DashboardShell
      user={user}
      headerSlot={
        user ? (
          <span className="text-sm font-medium">Hola, {user.name}</span>
        ) : null
      }
    >
      <Outlet context={context} />

      {/*
        Mecanismo de desarrollo: expone handleDevUserResolved al window
        para que Playwright pueda inyectar un usuario de prueba sin
        depender de /auth/me. Se excluye del bundle de producción.
      */}
      {import.meta.env.DEV && (
        <DevUserInjector onUserResolved={handleDevUserResolved} />
      )}
    </DashboardShell>
  );
}

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
