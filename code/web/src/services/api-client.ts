import type { ApiError, NetworkError } from "@/types/api-error";
import { useAuthStore } from "@/stores/auth-store";

/**
 * Cliente HTTP central — todo feature pasa por aquí.
 * Responsabilidades (ver web/WEB_API_CLIENT.md §1):
 * 1. Adjunta Authorization: Bearer <access_token> desde Zustand (nunca localStorage)
 * 2. Traduce errores API al formato ApiError estándar
 * 3. Interceptor de 401: intenta refresh una vez (excluye /auth/refresh del loop)
 *
 * Uso: import { apiClient } from "@/services/api-client";
 *      const data = await apiClient.post<T>("/api/v1/auth/login", body);
 */

const BASE_URL = "";

type HttpMethod = "GET" | "POST" | "PUT" | "PATCH" | "DELETE";

interface RequestOptions {
  method: HttpMethod;
  body?: unknown;
  excludeAuth?: boolean;
}

async function request<T>(url: string, options: RequestOptions): Promise<T> {
  const { method, body, excludeAuth = false } = options;

  const headers: Record<string, string> = {
    "Content-Type": "application/json",
    Accept: "application/json",
  };

  // Adjuntar token de acceso desde el store (nunca localStorage)
  if (!excludeAuth) {
    const token = useAuthStore.getState().accessToken;
    if (token) {
      headers["Authorization"] = `Bearer ${token}`;
    }
  }

  const fetchOptions: RequestInit = {
    method,
    headers,
    credentials: "include", // Para cookies httpOnly (refresh_token)
  };

  if (body !== undefined) {
    fetchOptions.body = JSON.stringify(body);
  }

  const response = await fetch(`${BASE_URL}${url}`, fetchOptions);

  // 401 interceptor — solo para requests autenticadas (excluye login, refresh, etc.)
  if (response.status === 401 && !excludeAuth && !url.includes("/auth/refresh")) {
    const refreshed = await tryRefresh();
    if (refreshed) {
      // Reintentar con el token nuevo
      const newToken = useAuthStore.getState().accessToken;
      if (newToken) {
        headers["Authorization"] = `Bearer ${newToken}`;
      }
      const retryResponse = await fetch(`${BASE_URL}${url}`, {
        ...fetchOptions,
        headers,
      });

      if (!retryResponse.ok) {
        throw await parseError(retryResponse);
      }

      return retryResponse.json() as Promise<T>;
    }

    // Refresh falló — limpiar store y dejar que la UI redirija
    useAuthStore.getState().clearAccessToken();
    throw createApiError(
      "REFRESH_FAILED",
      "Sesión expirada. Inicia sesión de nuevo.",
    );
  }

  if (!response.ok) {
    throw await parseError(response);
  }

  // Para respuestas 204 No Content (logout, etc.)
  if (response.status === 204) {
    return undefined as T;
  }

  return response.json() as Promise<T>;
}

async function tryRefresh(): Promise<boolean> {
  try {
    const response = await fetch(`${BASE_URL}/api/v1/auth/refresh`, {
      method: "POST",
      headers: {
        Accept: "application/json",
      },
      credentials: "include",
    });

    if (!response.ok) return false;

    const data: { access_token: string } = await response.json();
    useAuthStore.getState().setAccessToken(data.access_token);
    return true;
  } catch {
    return false;
  }
}

async function parseError(response: Response): Promise<ApiError> {
  try {
    const body = await response.text();
    // Intentar parsear como JSON (formato ApiError)
    if (body) {
      const json: unknown = JSON.parse(body);
      if (
        json &&
        typeof json === "object" &&
        "error" in json &&
        json.error &&
        typeof json.error === "object"
      ) {
        const err = json.error as Record<string, unknown>;
        return createApiError(
          String(err.code ?? "UNKNOWN"),
          String(err.message ?? "Error desconocido"),
          typeof err.trace_id === "string" ? err.trace_id : undefined,
        );
      }
    }
  } catch {
    // Falló el parse — seguir con error genérico
  }

  return createApiError(
    `HTTP_${response.status}`,
    `Error del servidor (${response.status})`,
  );
}

function createApiError(
  code: string,
  message: string,
  trace_id?: string,
): ApiError {
  return {
    code,
    message,
    trace_id: trace_id ?? "",
  };
}

export const apiClient = {
  get<T>(url: string): Promise<T> {
    return request<T>(url, { method: "GET" });
  },

  post<T>(url: string, body?: unknown): Promise<T> {
    return request<T>(url, { method: "POST", body });
  },

  put<T>(url: string, body?: unknown): Promise<T> {
    return request<T>(url, { method: "PUT", body });
  },

  patch<T>(url: string, body?: unknown): Promise<T> {
    return request<T>(url, { method: "PATCH", body });
  },

  delete<T>(url: string): Promise<T> {
    return request<T>(url, { method: "DELETE" });
  },

  /** Llamada sin token — usada por /auth/refresh y endpoints públicos */
  unauthenticated: {
    post<T>(url: string, body?: unknown): Promise<T> {
      return request<T>(url, { method: "POST", body, excludeAuth: true });
    },

    get<T>(url: string): Promise<T> {
      return request<T>(url, { method: "GET", excludeAuth: true });
    },
  },
};

export type { ApiError, NetworkError };
