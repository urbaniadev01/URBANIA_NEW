import { useState } from "react";
import { useMutation } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
import { toast } from "sonner";
import { apiClient } from "@/services/api-client";
import type {
  ResetPasswordRequest,
  ResetPasswordResponse,
} from "../types/auth.types";
import { RESET_PASSWORD_ERROR_CODES } from "../types/auth.types";
import type { ApiError } from "@/types/api-error";

const RESET_PASSWORD_URL = "/api/v1/auth/reset-password";

/**
 * Hook de mutación para aplicar nueva contraseña con token de recuperación.
 * Consume LOCK-AUTH-09 (POST /api/v1/auth/reset-password).
 *
 * - Éxito (200): redirige a /login con toast de éxito.
 * - Error RESET_TOKEN_INVALID (422): muestra mensaje con enlace a /forgot-password.
 * - Error RESET_TOKEN_EXPIRED (422): muestra mensaje con enlace a /forgot-password.
 * - Error TOO_MANY_REQUESTS (429): toast de rate limiting.
 * - Error VALIDATION_ERROR (422): muestra mensaje del campo fallido.
 *
 * Devuelve `fatalError` (string | null) para que la página reemplace
 * el formulario por un mensaje de error + enlace cuando el token es inválido/expirado.
 */
export function useResetPasswordMutation() {
  const navigate = useNavigate();
  const [fatalError, setFatalError] = useState<string | null>(null);

  const mutation = useMutation<
    ResetPasswordResponse,
    ApiError,
    ResetPasswordRequest
  >({
    mutationFn: (data: ResetPasswordRequest) =>
      apiClient.unauthenticated.post<ResetPasswordResponse>(
        RESET_PASSWORD_URL,
        data,
      ),

    onSuccess: () => {
      toast.success(
        "Contraseña actualizada exitosamente. Inicia sesión con tu nueva contraseña.",
      );
      navigate("/login");
    },

    onError: (error: ApiError) => {
      switch (error.code) {
        case RESET_PASSWORD_ERROR_CODES.RESET_TOKEN_INVALID:
          setFatalError(
            "Este enlace ya no es válido. Solicita uno nuevo.",
          );
          break;

        case RESET_PASSWORD_ERROR_CODES.RESET_TOKEN_EXPIRED:
          setFatalError(
            "Este enlace expiró (válido por 60 minutos). Solicita uno nuevo.",
          );
          break;

        case RESET_PASSWORD_ERROR_CODES.TOO_MANY_REQUESTS:
          toast.error(
            "Demasiados intentos. Espera 15 minutos e inténtalo de nuevo.",
          );
          break;

        case RESET_PASSWORD_ERROR_CODES.VALIDATION_ERROR:
          toast.error(error.message || "Datos inválidos. Revisa los campos.");
          break;

        default:
          toast.error(
            error.message || "Error al procesar la solicitud. Intenta de nuevo.",
          );
          break;
      }
    },
  });

  return { ...mutation, fatalError };
}
