import { useQuery } from "@tanstack/react-query";
import { apiClient } from "@/services/api-client";
import type { AuthUser } from "@/features/dashboard/types";
import type { ApiError } from "@/types/api-error";

/**
 * Response shape de GET /api/v1/auth/me (LOCK-AUTH-10).
 * El endpoint devuelve { user: { ... } } — este tipo solo modela
 * el envelope; el hook extrae y retorna user directamente.
 */
interface MeResponse {
  user: AuthUser;
}

/**
 * Hook de query para obtener el usuario autenticado actual.
 * Consume LOCK-AUTH-10: GET /api/v1/auth/me.
 *
 * - Llama al endpoint con el token de acceso actual (vía apiClient).
 * - Si el token expiró, el interceptor de 401 del apiClient intenta refresh
 *   automáticamente.
 * - staleTime: hereda el default del QueryClient (5 min) — el perfil de
 *   usuario no cambia frecuentemente.
 *
 * Retorna:
 * - data: AuthUser | undefined — el usuario autenticado
 * - isLoading: true mientras la query está en vuelo inicial
 * - isError: true si la API devolvió error (incluyendo 401 no recuperable)
 * - refetch: función para reintentar manualmente
 */
export function useUserQuery() {
  return useQuery<AuthUser, ApiError>({
    queryKey: ["auth", "me"],
    queryFn: async () => {
      const response = await apiClient.get<MeResponse>("/api/v1/auth/me");
      return response.user;
    },
  });
}
