import { useMutation } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
import { toast } from "sonner";
import { apiClient } from "@/services/api-client";
import type {
  RegisterRequestDto,
  RegisterResponse,
} from "../types/auth.types";
import { REGISTER_ERROR_CODES } from "../types/auth.types";
import type { ApiError } from "@/types/api-error";

const REGISTER_URL = "/api/v1/auth/register";

/**
 * Hook de mutación para registro por invitación.
 * Consume LOCK-AUTH-01 (POST /api/v1/auth/register).
 *
 * - Éxito (201): redirige a /login con toast de éxito (sin auto-login).
 * - Error 403 INVITATION_TOKEN_INVALID: mensaje genérico (no distingue expirado/consumido).
 * - Error 409 EMAIL_ALREADY_REGISTERED: sugiere iniciar sesión.
 * - Error 422 VALIDATION_ERROR: muestra mensaje de validación del servidor.
 */
export function useRegisterMutation() {
  const navigate = useNavigate();

  return useMutation<RegisterResponse, ApiError, RegisterRequestDto>({
    mutationFn: (data: RegisterRequestDto) =>
      apiClient.unauthenticated.post<RegisterResponse>(REGISTER_URL, data),

    onSuccess: () => {
      toast.success("Cuenta creada, inicia sesión");
      navigate("/login");
    },

    onError: (error: ApiError) => {
      switch (error.code) {
        case REGISTER_ERROR_CODES.INVITATION_TOKEN_INVALID:
          toast.error(
            "La invitación no es válida o ya fue utilizada.",
          );
          break;

        case REGISTER_ERROR_CODES.EMAIL_ALREADY_REGISTERED:
          toast.error(
            "Este email ya está registrado. Inicia sesión en lugar de registrarte.",
          );
          break;

        case REGISTER_ERROR_CODES.VALIDATION_ERROR:
          toast.error(error.message || "Datos inválidos. Revisa los campos.");
          break;

        default:
          // 429, errores de red, etc.
          if (error.code.startsWith("HTTP_429")) {
            toast.error(
              "Demasiados intentos. Espera un minuto antes de volver a intentarlo.",
            );
          } else {
            toast.error(
              error.message || "Error al registrar. Intenta de nuevo.",
            );
          }
          break;
      }
    },
  });
}
