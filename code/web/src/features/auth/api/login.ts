import { useMutation } from "@tanstack/react-query";
import { useNavigate, useLocation } from "react-router-dom";
import { toast } from "sonner";
import { apiClient } from "@/services/api-client";
import { useAuthStore } from "@/stores/auth-store";
import type { LoginRequestDto, LoginResponse } from "../types/auth.types";
import { LOGIN_ERROR_CODES } from "../types/auth.types";
import type { ApiError } from "@/types/api-error";

const LOGIN_URL = "/api/v1/auth/login";

/**
 * Hook de mutación para login.
 * Consume LOCK-AUTH-02 (POST /api/v1/auth/login).
 *
 * - Éxito (200): guarda access_token en Zustand (memoria), redirige a /dashboard.
 * - Error: muestra toast con mensaje apropiado según el código de error de la API.
 */
export function useLoginMutation() {
  const navigate = useNavigate();
  const location = useLocation();
  const setAccessToken = useAuthStore((s) => s.setAccessToken);

  const from = (location.state as { from?: string })?.from;

  return useMutation<LoginResponse, ApiError, LoginRequestDto>({
    mutationFn: (data: LoginRequestDto) =>
      apiClient.unauthenticated.post<LoginResponse>(LOGIN_URL, data),

    onSuccess: (response: LoginResponse) => {
      // Si la API indica que se requiere MFA, redirigir al flujo MFA
      // (el mfa_token ya fue seteado como cookie httpOnly por el backend)
      if (response.mfa_required) {
        navigate("/mfa/verify");
        return;
      }

      // Login directo (sin MFA): guardar token y redirigir al destino original o dashboard
      if (response.access_token) {
        setAccessToken(response.access_token);
      }
      navigate(from || "/dashboard", { replace: true });
    },

    onError: (error: ApiError) => {
      switch (error.code) {
        case LOGIN_ERROR_CODES.INVALID_CREDENTIALS:
          toast.error("Email o contraseña incorrectos.");
          break;

        case LOGIN_ERROR_CODES.ACCOUNT_NOT_ACTIVE:
          toast.error(
            "Tu cuenta no está activa. Contacta al administrador.",
          );
          break;

        case LOGIN_ERROR_CODES.VALIDATION_ERROR:
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
              error.message || "Error al iniciar sesión. Intenta de nuevo.",
            );
          }
          break;
      }
    },
  });
}
