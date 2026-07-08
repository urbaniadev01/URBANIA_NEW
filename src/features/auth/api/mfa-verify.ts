import { useMutation } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
import { toast } from "sonner";
import { apiClient } from "@/services/api-client";
import { useAuthStore } from "@/stores/auth-store";
import type { MfaVerifyRequest, MfaVerifyResponse } from "../types/auth.types";
import { MFA_VERIFY_ERROR_CODES } from "../types/auth.types";
import type { ApiError } from "@/types/api-error";

const MFA_VERIFY_URL = "/api/v1/auth/mfa/verify";

/**
 * Hook de mutación para verificación MFA durante login.
 * Consume LOCK-AUTH-08 (POST /api/v1/auth/mfa/verify).
 *
 * Usa apiClient.unauthenticated.post() porque este endpoint autentica via cookie
 * httpOnly `mfa_token` — NO usa `Authorization: Bearer`. El flag `excludeAuth`
 * evita adjuntar el access_token de Zustand y también evita que el interceptor
 * de 401 intente refresh (el 401 aquí significa mfa_token expirado, no
 * access_token expirado — son dominios distintos).
 *
 * - Éxito (200): guarda access_token en Zustand (memoria), redirige al dashboard /.
 * - Error 401 (MFA_TOKEN_INVALID): notifica y deja que la página muestre enlace a /login.
 * - Error 422 (MFA_CODE_INVALID, MFA_RECOVERY_CODE_USED): mensaje específico.
 * - Error 429 (TOO_MANY_REQUESTS): notifica rate limit.
 */
export function useMfaVerifyMutation() {
  const navigate = useNavigate();
  const setAccessToken = useAuthStore((s) => s.setAccessToken);

  return useMutation<MfaVerifyResponse, ApiError, MfaVerifyRequest>({
    mutationFn: (data: MfaVerifyRequest) =>
      apiClient.unauthenticated.post<MfaVerifyResponse>(MFA_VERIFY_URL, data),

    onSuccess: (response: MfaVerifyResponse) => {
      setAccessToken(response.access_token);
      navigate("/dashboard");
    },

    onError: (error: ApiError) => {
      switch (error.code) {
        case MFA_VERIFY_ERROR_CODES.MFA_TOKEN_INVALID:
          toast.error(
            "Tu sesión de verificación expiró. Vuelve a iniciar sesión.",
          );
          break;

        case MFA_VERIFY_ERROR_CODES.MFA_CODE_INVALID:
          toast.error("Código inválido. Intenta de nuevo.");
          break;

        case MFA_VERIFY_ERROR_CODES.MFA_RECOVERY_CODE_USED:
          toast.error("Este código de respaldo ya fue utilizado.");
          break;

        case MFA_VERIFY_ERROR_CODES.TOO_MANY_REQUESTS:
          toast.error(
            "Demasiados intentos. Espera un minuto e inténtalo de nuevo.",
          );
          break;

        default:
          if (error.code.startsWith("HTTP_429")) {
            toast.error(
              "Demasiados intentos. Espera un minuto e inténtalo de nuevo.",
            );
          } else {
            toast.error(
              error.message || "Error al verificar. Intenta de nuevo.",
            );
          }
          break;
      }
    },
  });
}
