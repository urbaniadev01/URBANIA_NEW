import { useMutation } from "@tanstack/react-query";
import { apiClient } from "@/services/api-client";
import type {
  MfaEnrollResponse,
  MfaConfirmRequest,
  MfaConfirmResponse,
  MfaDisableRequest,
  MfaDisableResponse,
  MfaRecoveryRequest,
  MfaRecoveryResponse,
} from "../types/auth.types";
import { MFA_ENROLL_ERROR_CODES } from "../types/auth.types";
import type { ApiError } from "@/types/api-error";

const MFA_ENROLL_URL = "/api/v1/auth/mfa/enroll";
const MFA_CONFIRM_URL = "/api/v1/auth/mfa/confirm";
const MFA_DISABLE_URL = "/api/v1/auth/mfa/disable";
const MFA_RECOVERY_URL = "/api/v1/auth/mfa/recovery";

export function useMfaEnrollMutation() {
  return useMutation<MfaEnrollResponse, ApiError, void>({
    mutationFn: () =>
      apiClient.post<MfaEnrollResponse>(MFA_ENROLL_URL),
    retry: false,
    onError: (error: ApiError) => {
      switch (error.code) {
        case MFA_ENROLL_ERROR_CODES.MFA_ALREADY_ENABLED:
        case MFA_ENROLL_ERROR_CODES.TOO_MANY_REQUESTS:
        case MFA_ENROLL_ERROR_CODES.UNAUTHENTICATED:
        case MFA_ENROLL_ERROR_CODES.REFRESH_FAILED:
          break;
        default:
          break;
      }
    },
  });
}

export function useMfaConfirmMutation() {
  return useMutation<MfaConfirmResponse, ApiError, MfaConfirmRequest>({
    mutationFn: (data: MfaConfirmRequest) =>
      apiClient.post<MfaConfirmResponse>(MFA_CONFIRM_URL, data),
    retry: false,
    onError: (error: ApiError) => {
      switch (error.code) {
        case MFA_ENROLL_ERROR_CODES.MFA_CODE_INVALID:
        case MFA_ENROLL_ERROR_CODES.MFA_ENROLLMENT_EXPIRED:
        case MFA_ENROLL_ERROR_CODES.MFA_ENROLLMENT_NOT_FOUND:
        case MFA_ENROLL_ERROR_CODES.TOO_MANY_REQUESTS:
        case MFA_ENROLL_ERROR_CODES.UNAUTHENTICATED:
        case MFA_ENROLL_ERROR_CODES.REFRESH_FAILED:
          break;
        default:
          break;
      }
    },
  });
}

export function useMfaDisableMutation() {
  return useMutation<MfaDisableResponse, ApiError, MfaDisableRequest>({
    mutationFn: (data: MfaDisableRequest) =>
      apiClient.post<MfaDisableResponse>(MFA_DISABLE_URL, data),
    retry: false,
    onError: (error: ApiError) => {
      switch (error.code) {
        case MFA_ENROLL_ERROR_CODES.MFA_CODE_INVALID:
        case MFA_ENROLL_ERROR_CODES.MFA_NOT_ENABLED:
        case MFA_ENROLL_ERROR_CODES.TOO_MANY_REQUESTS:
        case MFA_ENROLL_ERROR_CODES.UNAUTHENTICATED:
        case MFA_ENROLL_ERROR_CODES.REFRESH_FAILED:
          break;
        default:
          break;
      }
    },
  });
}

export function useMfaRecoveryMutation() {
  return useMutation<MfaRecoveryResponse, ApiError, MfaRecoveryRequest>({
    mutationFn: (data: MfaRecoveryRequest) =>
      apiClient.post<MfaRecoveryResponse>(MFA_RECOVERY_URL, data),
    retry: false,
    onError: (error: ApiError) => {
      switch (error.code) {
        case MFA_ENROLL_ERROR_CODES.MFA_CODE_INVALID:
        case MFA_ENROLL_ERROR_CODES.MFA_NOT_ENABLED:
        case MFA_ENROLL_ERROR_CODES.TOO_MANY_REQUESTS:
        case MFA_ENROLL_ERROR_CODES.UNAUTHENTICATED:
        case MFA_ENROLL_ERROR_CODES.REFRESH_FAILED:
          break;
        default:
          break;
      }
    },
  });
}
