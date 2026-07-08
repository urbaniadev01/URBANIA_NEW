import { z } from "zod";

/**
 * Tipos de autenticación — coinciden exactamente con LOCK-AUTH-02.
 * Ver _state/contracts/CONTRACT_LOCKS.md#LOCK-AUTH-02
 */

/** POST /api/v1/auth/login — request body (LOCK-AUTH-02) */
export interface LoginRequestDto {
  email: string;
  password: string;
}

/** POST /api/v1/auth/login — response 200 (LOCK-AUTH-02, actualizado por AUTH-B08) */
export interface LoginResponse {
  mfa_required: boolean;
  mfa_token?: string;
  access_token?: string;
  token_type?: "Bearer";
  expires_in?: 900;
}

/**
 * Códigos de error específicos del endpoint login (LOCK-AUTH-02).
 * Usados para diferenciar el mensaje de UI según el código devuelto.
 */
export const LOGIN_ERROR_CODES = {
  INVALID_CREDENTIALS: "INVALID_CREDENTIALS",
  ACCOUNT_NOT_ACTIVE: "ACCOUNT_NOT_ACTIVE",
  VALIDATION_ERROR: "VALIDATION_ERROR",
} as const;

export type LoginErrorCode =
  (typeof LOGIN_ERROR_CODES)[keyof typeof LOGIN_ERROR_CODES];

// ── Registro (LOCK-AUTH-01) ──────────────────────────────────────────────

/** POST /api/v1/auth/register — request body (LOCK-AUTH-01) */
export interface RegisterRequestDto {
  invitation_token: string;
  password: string;
  name: string;
  phone?: string;
}

/** POST /api/v1/auth/register — response 201 (LOCK-AUTH-01) */
export interface RegisterResponse {
  message: string;
  user: {
    id: number;
    email: string;
    name: string;
    estado: string;
    organization_id: number;
    created_at: string;
  };
}

/** Códigos de error específicos del endpoint register (LOCK-AUTH-01) */
export const REGISTER_ERROR_CODES = {
  INVITATION_TOKEN_INVALID: "INVITATION_TOKEN_INVALID",
  EMAIL_ALREADY_REGISTERED: "EMAIL_ALREADY_REGISTERED",
  VALIDATION_ERROR: "VALIDATION_ERROR",
} as const;

export type RegisterErrorCode =
  (typeof REGISTER_ERROR_CODES)[keyof typeof REGISTER_ERROR_CODES];

// ── MFA Verify (LOCK-AUTH-08) ─────────────────────────────────────────────

/** POST /api/v1/auth/mfa/verify — request body (LOCK-AUTH-08) */
export interface MfaVerifyRequest {
  code: string;
}

/** POST /api/v1/auth/mfa/verify — response 200 (LOCK-AUTH-08) */
export interface MfaVerifyResponse {
  access_token: string;
  token_type: "Bearer";
  expires_in: 900;
}

/** Códigos de error específicos del endpoint mfa/verify (LOCK-AUTH-08) */
export const MFA_VERIFY_ERROR_CODES = {
  MFA_TOKEN_INVALID: "MFA_TOKEN_INVALID",
  MFA_CODE_INVALID: "MFA_CODE_INVALID",
  MFA_RECOVERY_CODE_USED: "MFA_RECOVERY_CODE_USED",
  TOO_MANY_REQUESTS: "TOO_MANY_REQUESTS",
} as const;

export type MfaVerifyErrorCode =
  (typeof MFA_VERIFY_ERROR_CODES)[keyof typeof MFA_VERIFY_ERROR_CODES];

// ── MFA Enroll (LOCK-AUTH-08) ─────────────────────────────────────────────

/** POST /api/v1/auth/mfa/enroll — response 201 (LOCK-AUTH-08) */
export interface MfaEnrollResponse {
  qr_code: string;
  recovery_codes: string[];
  enrollment_token: string;
}

/** POST /api/v1/auth/mfa/confirm — request body (LOCK-AUTH-08) */
export interface MfaConfirmRequest {
  code: string;
}

/** POST /api/v1/auth/mfa/confirm — response 200 (LOCK-AUTH-08) */
export interface MfaConfirmResponse {
  message: string;
}

/** POST /api/v1/auth/mfa/disable — request body (LOCK-AUTH-08) */
export interface MfaDisableRequest {
  code: string;
}

/** POST /api/v1/auth/mfa/disable — response 200 (LOCK-AUTH-08) */
export interface MfaDisableResponse {
  message: string;
}

/** POST /api/v1/auth/mfa/recovery — request body (LOCK-AUTH-08) */
export interface MfaRecoveryRequest {
  code: string;
}

/** POST /api/v1/auth/mfa/recovery — response 200 (LOCK-AUTH-08) */
export interface MfaRecoveryResponse {
  recovery_codes: string[];
}

/** Códigos de error específicos de los endpoints mfa/enroll (LOCK-AUTH-08) */
export const MFA_ENROLL_ERROR_CODES = {
  UNAUTHENTICATED: "UNAUTHENTICATED",
  REFRESH_FAILED: "REFRESH_FAILED",
  MFA_ALREADY_ENABLED: "MFA_ALREADY_ENABLED",
  MFA_NOT_ENABLED: "MFA_NOT_ENABLED",
  MFA_CODE_INVALID: "MFA_CODE_INVALID",
  MFA_ENROLLMENT_NOT_FOUND: "MFA_ENROLLMENT_NOT_FOUND",
  MFA_ENROLLMENT_EXPIRED: "MFA_ENROLLMENT_EXPIRED",
  TOO_MANY_REQUESTS: "TOO_MANY_REQUESTS",
} as const;

export type MfaEnrollErrorCode =
  (typeof MFA_ENROLL_ERROR_CODES)[keyof typeof MFA_ENROLL_ERROR_CODES];

// ── Forgot Password (LOCK-AUTH-09) ──────────────────────────────────────────

/** POST /api/v1/auth/forgot-password — request body (LOCK-AUTH-09) */
export interface ForgotPasswordRequest {
  email: string;
}

/** POST /api/v1/auth/forgot-password — response 200 (LOCK-AUTH-09) */
export interface ForgotPasswordResponse {
  message: string;
}

// ── Reset Password (LOCK-AUTH-09) ─────────────────────────────────────────

/** POST /api/v1/auth/reset-password — request body (LOCK-AUTH-09) */
export interface ResetPasswordRequest {
  token: string;
  password: string;
  password_confirmation: string;
}

/** POST /api/v1/auth/reset-password — response 200 (LOCK-AUTH-09) */
export interface ResetPasswordResponse {
  message: string;
}

/** Códigos de error específicos del endpoint reset-password (LOCK-AUTH-09) */
export const RESET_PASSWORD_ERROR_CODES = {
  RESET_TOKEN_INVALID: "RESET_TOKEN_INVALID",
  RESET_TOKEN_EXPIRED: "RESET_TOKEN_EXPIRED",
  VALIDATION_ERROR: "VALIDATION_ERROR",
  TOO_MANY_REQUESTS: "TOO_MANY_REQUESTS",
} as const;

export type ResetPasswordErrorCode =
  (typeof RESET_PASSWORD_ERROR_CODES)[keyof typeof RESET_PASSWORD_ERROR_CODES];

// ── Password schema compartido ─────────────────────────────────────────

/**
 * Esquema Zod reutilizable para validación de contraseña.
 * Requisitos: mínimo 8 caracteres, 1 mayúscula, 1 minúscula, 1 número.
 *
 * Usado por RegisterPage (AUTH-B07) y ResetPasswordPage (AUTH-B13).
 * Cada formulario agrega su propio `.refine()` para confirmación si aplica.
 */
export const passwordSchema = z
  .string()
  .min(8, "La contraseña debe tener al menos 8 caracteres.")
  .regex(/[A-Z]/, "La contraseña debe contener al menos una mayúscula.")
  .regex(/[a-z]/, "La contraseña debe contener al menos una minúscula.")
  .regex(/[0-9]/, "La contraseña debe contener al menos un número.");
