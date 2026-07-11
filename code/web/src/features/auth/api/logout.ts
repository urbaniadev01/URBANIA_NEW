import { useMutation } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
import { apiClient } from "@/services/api-client";
import { useAuthStore } from "@/stores/auth-store";

const LOGOUT_URL = "/api/v1/auth/logout";

/**
 * Hook de mutación para logout. Consume POST /api/v1/auth/logout
 * (api/endpoints/AUTH.md — endpoint público, sin body, no requiere token).
 *
 * El store se limpia siempre, incluso si la llamada a la API falla
 * (red caída, 429, etc.) — el objetivo es que el usuario salga de la
 * sesión en el cliente sin importar la respuesta del servidor.
 */
export function useLogoutMutation() {
  const navigate = useNavigate();
  const clearAccessToken = useAuthStore((s) => s.clearAccessToken);

  return useMutation<void, unknown, void>({
    mutationFn: () => apiClient.unauthenticated.post<void>(LOGOUT_URL),
    onSettled: () => {
      clearAccessToken();
      navigate("/login", { replace: true });
    },
  });
}
