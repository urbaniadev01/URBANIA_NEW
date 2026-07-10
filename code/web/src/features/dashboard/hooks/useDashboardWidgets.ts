import { useMemo } from "react";
import type { AuthUser } from "@/features/dashboard/types";
import { getVisibleWidgets } from "@/features/dashboard/registry";

interface UseDashboardWidgetsResult {
  /** Widgets visibles para el usuario actual, ordenados por prioridad. */
  widgets: ReturnType<typeof getVisibleWidgets>;
  /** true mientras los permisos del usuario no están resueltos (ventana async de /auth/me). */
  isLoading: boolean;
}

/**
 * Hook memoizado que filtra los widgets del registry según RBAC.
 *
 * Ventana de permisos no resueltos (PANORAMA §10.4 punto 3):
 * - Si `user` es null → los permisos aún no se conocen (llamada a /auth/me pendiente).
 *   Retorna `isLoading: true` y `widgets: []`. NUNCA flashea widgets no autorizados.
 * - Si `user` está presente → filtra inmediatamente vía getVisibleWidgets().
 *
 * La memoización depende de `user?.permissions` y `user?.role` — si los permisos
 * cambian (ej. refresh de /auth/me), el filtro se reevalúa.
 */
export function useDashboardWidgets(
  user: AuthUser | null,
): UseDashboardWidgetsResult {
  const widgets = useMemo(
    () => getVisibleWidgets(user),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [user?.permissions, user?.role],
  );

  const isLoading = user === null;

  return { widgets, isLoading };
}
