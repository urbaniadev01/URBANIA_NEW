import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { apiClient } from "@/services/api-client";
import type {
  TorreItem,
  TorreListResponse,
  TorreCreateResponse,
  TorreUpdateResponse,
  CreateTorreRequest,
  UpdateTorreRequest,
} from "../types";
import { CONDOMINIO_ERROR_CODES } from "../types";
import type { ApiError } from "@/types/api-error";

// ── Queries ──────────────────────────────────────────────────────────────

/**
 * Hook de query para listar torres de un condominio.
 * Consume LOCK-PROPIEDADES-02: GET /api/v1/condominiums/{id}/towers.
 */
export function useTorresQuery(condominiumId: string | undefined) {
  return useQuery<TorreItem[], ApiError>({
    queryKey: ["condominiums", condominiumId, "towers"],
    queryFn: async () => {
      const response = await apiClient.get<TorreListResponse>(
        `/api/v1/condominiums/${condominiumId}/towers`,
      );
      return response.data;
    },
    enabled: !!condominiumId,
  });
}

/**
 * Hook de query para ver detalle de una torre individual.
 * Consume LOCK-PROPIEDADES-02: GET /api/v1/towers/{id}.
 */
export function useTorreQuery(towerId: string | undefined) {
  return useQuery<TorreItem, ApiError>({
    queryKey: ["towers", towerId],
    queryFn: async () => {
      const response = await apiClient.get<{ tower: TorreItem }>(
        `/api/v1/towers/${towerId}`,
      );
      return response.tower;
    },
    enabled: !!towerId,
  });
}

// ── Mutations ────────────────────────────────────────────────────────────

/**
 * Hook de mutación para crear una torre bajo un condominio.
 * Consume LOCK-PROPIEDADES-02: POST /api/v1/condominiums/{id}/towers.
 */
export function useCreateTorreMutation() {
  const queryClient = useQueryClient();

  return useMutation<
    TorreCreateResponse,
    ApiError,
    { condominiumId: string; data: CreateTorreRequest }
  >({
    mutationFn: ({ condominiumId, data }) =>
      apiClient.post<TorreCreateResponse>(
        `/api/v1/condominiums/${condominiumId}/towers`,
        data,
      ),

    onSuccess: (response, variables) => {
      toast.success(`Torre "${response.tower.nombre}" creada.`);
      void queryClient.invalidateQueries({
        queryKey: ["condominiums", variables.condominiumId, "towers"],
      });
      void queryClient.invalidateQueries({
        queryKey: ["condominiums", variables.condominiumId],
      });
    },

    onError: (error: ApiError) => {
      switch (error.code) {
        case CONDOMINIO_ERROR_CODES.TOWER_NAME_DUPLICATE:
          toast.error("Ya existe una torre con ese nombre en este condominio.");
          break;
        case CONDOMINIO_ERROR_CODES.CONDOMINIUM_NOT_FOUND:
          toast.error("El condominio no existe.");
          break;
        case CONDOMINIO_ERROR_CODES.FORBIDDEN:
          toast.error("No tienes permiso para crear torres.");
          break;
        default:
          if (error.code.startsWith("HTTP_422")) {
            toast.error(error.message || "Datos inválidos. Revisa los campos.");
          } else {
            toast.error(error.message || "Error al crear la torre.");
          }
          break;
      }
    },
  });
}

/**
 * Hook de mutación para actualizar una torre.
 * Consume LOCK-PROPIEDADES-02: PATCH /api/v1/towers/{id}.
 */
export function useUpdateTorreMutation() {
  const queryClient = useQueryClient();

  return useMutation<
    TorreUpdateResponse,
    ApiError,
    { id: string; condominiumId: string; data: UpdateTorreRequest }
  >({
    mutationFn: ({ id, data }) =>
      apiClient.patch<TorreUpdateResponse>(`/api/v1/towers/${id}`, data),

    onSuccess: (response, variables) => {
      toast.success(`Torre "${response.tower.nombre}" actualizada.`);
      void queryClient.invalidateQueries({
        queryKey: ["condominiums", variables.condominiumId, "towers"],
      });
      void queryClient.invalidateQueries({
        queryKey: ["condominiums", variables.condominiumId],
      });
    },

    onError: (error: ApiError) => {
      switch (error.code) {
        case CONDOMINIO_ERROR_CODES.TOWER_NOT_FOUND:
          toast.error("La torre no existe.");
          break;
        case CONDOMINIO_ERROR_CODES.TOWER_NAME_DUPLICATE:
          toast.error("Ya existe una torre con ese nombre en este condominio.");
          break;
        case CONDOMINIO_ERROR_CODES.FORBIDDEN:
          toast.error("No tienes permiso para modificar esta torre.");
          break;
        default:
          if (error.code.startsWith("HTTP_422")) {
            toast.error(error.message || "Datos inválidos. Revisa los campos.");
          } else {
            toast.error(error.message || "Error al actualizar la torre.");
          }
          break;
      }
    },
  });
}

/**
 * Hook de mutación para eliminar una torre.
 * Consume LOCK-PROPIEDADES-02: DELETE /api/v1/towers/{id}.
 */
export function useDeleteTorreMutation() {
  const queryClient = useQueryClient();

  return useMutation<
    void,
    ApiError,
    { id: string; condominiumId: string }
  >({
    mutationFn: ({ id }) => apiClient.delete(`/api/v1/towers/${id}`),

    onSuccess: (_data, variables) => {
      toast.success("Torre eliminada.");
      void queryClient.invalidateQueries({
        queryKey: ["condominiums", variables.condominiumId, "towers"],
      });
      void queryClient.invalidateQueries({
        queryKey: ["condominiums", variables.condominiumId],
      });
    },

    onError: (error: ApiError) => {
      switch (error.code) {
        case CONDOMINIO_ERROR_CODES.TOWER_HAS_PROPERTIES:
          toast.error(
            error.message ||
              "No se puede eliminar: la torre tiene unidades activas.",
          );
          break;
        case CONDOMINIO_ERROR_CODES.TOWER_NOT_FOUND:
          toast.error("La torre no existe.");
          break;
        default:
          toast.error(error.message || "Error al eliminar la torre.");
          break;
      }
    },
  });
}
