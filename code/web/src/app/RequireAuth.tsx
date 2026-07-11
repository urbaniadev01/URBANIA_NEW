import { useEffect, useState } from "react";
import { Navigate, Outlet, useLocation } from "react-router-dom";
import { useAuthStore } from "@/stores/auth-store";
import { tryRefresh } from "@/services/api-client";
import { LoadingState } from "@/components/loading-state";

/**
 * El access_token vive solo en memoria (Zustand, sin persist — ver
 * auth-store.ts). Un F5 lo resetea a null, pero el refresh_token
 * (cookie httpOnly) sigue vivo del lado del servidor. Antes de decidir
 * que no hay sesión, intentamos una vez /auth/refresh con esa cookie.
 */
export function RequireAuth(): React.ReactNode {
  const accessToken = useAuthStore((s) => s.accessToken);
  const location = useLocation();
  const [status, setStatus] = useState<"checking" | "authenticated" | "unauthenticated">(
    accessToken ? "authenticated" : "checking",
  );

  useEffect(() => {
    if (accessToken) {
      setStatus("authenticated");
      return;
    }

    let cancelled = false;
    void tryRefresh().then((refreshed) => {
      if (!cancelled) {
        setStatus(refreshed ? "authenticated" : "unauthenticated");
      }
    });

    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  if (status === "checking") {
    return <LoadingState className="min-h-screen" />;
  }

  if (status === "unauthenticated") {
    return (
      <Navigate
        to="/login"
        state={{ from: location.pathname + location.search }}
        replace
      />
    );
  }

  return <Outlet />;
}
