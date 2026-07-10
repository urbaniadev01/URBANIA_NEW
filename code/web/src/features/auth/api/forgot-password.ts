import { useMutation } from "@tanstack/react-query";
import { toast } from "sonner";
import { apiClient } from "@/services/api-client";
import type {
  ForgotPasswordRequest,
  ForgotPasswordResponse,
} from "../types/auth.types";
import type { ApiError } from "@/types/api-error";

const FORGOT_PASSWORD_URL = "/api/v1/auth/forgot-password";

/**
 * Hook de mutación para solicitud de recuperación de contraseña.
 * Consume LOCK-AUTH-09 (POST /api/v1/auth/forgot-password).
 *
 * - Éxito (200): la página muestra el mensaje genérico y oculta el formulario.
 * - Error TOO_MANY_REQUESTS (429): toast con mensaje de rate limiting.
 * - Otros errores: toast con mensaje genérico.
 *
 * La API siempre responde 200 con el mismo mensaje exista o no el email
 * (anti-enumeración). La UI no distingue casos.
 */
export function useForgotPasswordMutation() {
  return useMutation<ForgotPasswordResponse, ApiError, ForgotPasswordRequest>({
    mutationFn: (data: ForgotPasswordRequest) =>
      apiClient.unauthenticated.post<ForgotPasswordResponse>(
        FORGOT_PASSWORD_URL,
        data,
      ),

    onError: (error: ApiError) => {
      if (error.code === "TOO_MANY_REQUESTS") {
        toast.error(
          "Demasiadas solicitudes. Espera e inténtalo de nuevo más tarde.",
        );
      } else {
        toast.error(
          error.message ||
            "Error al procesar la solicitud. Intenta de nuevo.",
        );
      }
    },
  });
}
