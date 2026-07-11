import { useOutletContext } from "react-router-dom";
import type { AuthUser } from "@/features/dashboard/types";

/**
 * Contexto expuesto por AppLayout a toda ruta privada anidada vía
 * <Outlet context={...} /> — única fuente de verdad del usuario para el
 * árbol de rutas privadas (sidebar RBAC incluido). Separado de AppLayout.tsx
 * para que ese archivo exporte un único componente (fast-refresh).
 */
export interface AppOutletContext {
  user: AuthUser | null;
  isLoading: boolean;
  isError: boolean;
  refetchUser: () => void;
}

export function useAppUser(): AppOutletContext {
  return useOutletContext<AppOutletContext>();
}
