import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { apiClient } from "@/services/api-client";
import type {
  CondominioItem,
  CondominioListResponse,
  CondominioShowResponse,
  CondominioCreateResponse,
  CondominioUpdateResponse,
  CreateCondominioRequest,
  UpdateCondominioRequest,
} from "../types";
import { CONDOMINIO_ERROR_CODES } from "../types";
import type { ApiError } from "@/types/api-error";

const BASE_URL = "/api/v1/condominiums";

// ── Queries ──────────────────────────────────────────────────────────────

/**
 * Hook de query para listar condominios del tenant.
 * Consume LOCK-PROPIEDADES-02: GET /api/v1/condominiums.
 */
export function useCondominiumsQuery() {
  return useQuery<CondominioItem[], ApiError>({
    queryKey: ["condominiums"],
    queryFn: async () => {
      const response = await apiClient.get<CondominioListResponse>(BASE_URL);
      return response.data;
    },
  });
}

/**
 * Hook de query para ver detalle de un condominio (incluye torres anidadas).
 * Consume LOCK-PROPIEDADES-02: GET /api/v1/condominiums/{id}.
 */
export function useCondominioQuery(id: string | undefined) {
  return useQuery<CondominioShowResponse, ApiError>({
    queryKey: ["condominiums", id],
    queryFn: async () => {
      return apiClient.get<CondominioShowResponse>(`${BASE_URL}/${id}`);
    },
    enabled: !!id,
  });
}

// ── Mutations ────────────────────────────────────────────────────────────

/**
 * Hook de mutación para crear un condominio.
 * Consume LOCK-PROPIEDADES-02: POST /api/v1/condominiums.
 */
export function useCreateCondominioMutation() {
  const queryClient = useQueryClient();

  return useMutation<
    CondominioCreateResponse,
    ApiError,
    CreateCondominioRequest
  >({
    mutationFn: (data: CreateCondominioRequest) =>
      apiClient.post<CondominioCreateResponse>(BASE_URL, data),

    onSuccess: (response) => {
      toast.success(`Condominio "${response.condominium.nombre}" creado.`);
      void queryClient.invalidateQueries({ queryKey: ["condominiums"] });
    },

    onError: (error: ApiError) => {
      switch (error.code) {
        case CONDOMINIO_ERROR_CODES.CONDOMINIUM_NAME_DUPLICATE:
          toast.error("Ya existe un condominio con ese nombre.");
          break;
        case CONDOMINIO_ERROR_CODES.FORBIDDEN:
          toast.error("No tienes permiso para crear condominios.");
          break;
        default:
          if (error.code.startsWith("HTTP_422")) {
            toast.error(error.message || "Datos inválidos. Revisa los campos.");
          } else {
            toast.error(error.message || "Error al crear el condominio.");
          }
          break;
      }
    },
  });
}

/**
 * Hook de mutación para actualizar un condominio.
 * Consume LOCK-PROPIEDADES-02: PATCH /api/v1/condominiums/{id}.
 */
export function useUpdateCondominioMutation() {
  const queryClient = useQueryClient();

  return useMutation<
    CondominioUpdateResponse,
    ApiError,
    { id: string; data: UpdateCondominioRequest }
  >({
    mutationFn: ({ id, data }) =>
      apiClient.patch<CondominioUpdateResponse>(`${BASE_URL}/${id}`, data),

    onSuccess: (response) => {
      toast.success(`Condominio "${response.condominium.nombre}" actualizado.`);
      void queryClient.invalidateQueries({ queryKey: ["condominiums"] });
    },

    onError: (error: ApiError) => {
      switch (error.code) {
        case CONDOMINIO_ERROR_CODES.CONDOMINIUM_NOT_FOUND:
          toast.error("El condominio no existe.");
          break;
        case CONDOMINIO_ERROR_CODES.CONDOMINIUM_NAME_DUPLICATE:
          toast.error("Ya existe un condominio con ese nombre.");
          break;
        case CONDOMINIO_ERROR_CODES.FORBIDDEN:
          toast.error("No tienes permiso para modificar este condominio.");
          break;
        default:
          if (error.code.startsWith("HTTP_422")) {
            toast.error(error.message || "Datos inválidos. Revisa los campos.");
          } else {
            toast.error(
              error.message || "Error al actualizar el condominio.",
            );
          }
          break;
      }
    },
  });
}

/**
 * Hook de mutación para eliminar un condominio.
 * Consume LOCK-PROPIEDADES-02: DELETE /api/v1/condominiums/{id}.
 */
export function useDeleteCondominioMutation() {
  const queryClient = useQueryClient();

  return useMutation<void, ApiError, string>({
    mutationFn: (id: string) => apiClient.delete(`${BASE_URL}/${id}`),

    onSuccess: () => {
      toast.success("Condominio eliminado.");
      void queryClient.invalidateQueries({ queryKey: ["condominiums"] });
    },

    onError: (error: ApiError) => {
      switch (error.code) {
        case CONDOMINIO_ERROR_CODES.CONDOMINIUM_HAS_TOWERS:
          toast.error(
            error.message ||
              "No se puede eliminar: el condominio tiene torres activas.",
          );
          break;
        case CONDOMINIO_ERROR_CODES.CONDOMINIUM_HAS_PROPERTIES:
          toast.error(
            error.message ||
              "No se puede eliminar: el condominio tiene propiedades activas.",
          );
          break;
        case CONDOMINIO_ERROR_CODES.CONDOMINIUM_NOT_FOUND:
          toast.error("El condominio no existe.");
          break;
        default:
          toast.error(error.message || "Error al eliminar el condominio.");
          break;
      }
    },
  });
}
