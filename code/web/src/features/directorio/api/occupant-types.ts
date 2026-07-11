import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { apiClient } from "@/services/api-client";
import type {
  OccupantTypeItem,
  OccupantTypeListResponse,
  OccupantTypeCreateResponse,
  OccupantTypeUpdateResponse,
  CreateOccupantTypeRequest,
  UpdateOccupantTypeRequest,
} from "../types";
import { OCCUPANT_TYPE_ERROR_CODES } from "../types";
import type { ApiError } from "@/types/api-error";

const BASE_URL = "/api/v1/occupant-types";

// ── Queries ──────────────────────────────────────────────────────────────

/**
 * Hook de query para listar tipos de ocupante (sistema + tenant).
 * Consume LOCK-DIRECTORIO-01: GET /api/v1/occupant-types.
 */
export function useOccupantTypesQuery() {
  return useQuery<OccupantTypeItem[], ApiError>({
    queryKey: ["occupant-types"],
    queryFn: async () => {
      const response = await apiClient.get<OccupantTypeListResponse>(BASE_URL);
      return response.data;
    },
  });
}

// ── Mutations ────────────────────────────────────────────────────────────

/**
 * Hook de mutación para crear un tipo de ocupante.
 * Consume LOCK-DIRECTORIO-01: POST /api/v1/occupant-types.
 */
export function useCreateOccupantTypeMutation() {
  const queryClient = useQueryClient();

  return useMutation<OccupantTypeCreateResponse, ApiError, CreateOccupantTypeRequest>({
    mutationFn: (data: CreateOccupantTypeRequest) =>
      apiClient.post<OccupantTypeCreateResponse>(BASE_URL, data),

    onSuccess: (response) => {
      toast.success(`Tipo "${response.data.nombre}" creado.`);
      void queryClient.invalidateQueries({ queryKey: ["occupant-types"] });
    },

    onError: (error: ApiError) => {
      switch (error.code) {
        case OCCUPANT_TYPE_ERROR_CODES.OCCUPANT_TYPE_NAME_DUPLICATE:
          toast.error("Ya existe un tipo de ocupante con ese nombre.");
          break;
        default:
          if (error.code.startsWith("HTTP_422")) {
            toast.error(error.message || "Datos inválidos. Revisa los campos.");
          } else {
            toast.error(error.message || "Error al crear el tipo de ocupante.");
          }
          break;
      }
    },
  });
}

/**
 * Hook de mutación para actualizar un tipo de ocupante.
 * Consume LOCK-DIRECTORIO-01: PATCH /api/v1/occupant-types/{id}.
 */
export function useUpdateOccupantTypeMutation() {
  const queryClient = useQueryClient();

  return useMutation<
    OccupantTypeUpdateResponse,
    ApiError,
    { id: string; data: UpdateOccupantTypeRequest }
  >({
    mutationFn: ({ id, data }) =>
      apiClient.patch<OccupantTypeUpdateResponse>(`${BASE_URL}/${id}`, data),

    onSuccess: (response) => {
      toast.success(`Tipo "${response.data.nombre}" actualizado.`);
      void queryClient.invalidateQueries({ queryKey: ["occupant-types"] });
    },

    onError: (error: ApiError) => {
      switch (error.code) {
        case OCCUPANT_TYPE_ERROR_CODES.SYSTEM_CATALOG_READONLY:
          toast.error("No se puede modificar un catálogo del sistema.");
          break;
        case OCCUPANT_TYPE_ERROR_CODES.OCCUPANT_TYPE_NOT_FOUND:
          toast.error("El tipo de ocupante no existe.");
          break;
        case OCCUPANT_TYPE_ERROR_CODES.OCCUPANT_TYPE_NAME_DUPLICATE:
          toast.error("Ya existe un tipo de ocupante con ese nombre.");
          break;
        default:
          if (error.code.startsWith("HTTP_422")) {
            toast.error(error.message || "Datos inválidos. Revisa los campos.");
          } else {
            toast.error(error.message || "Error al actualizar el tipo de ocupante.");
          }
          break;
      }
    },
  });
}

/**
 * Hook de mutación para eliminar un tipo de ocupante.
 * Consume LOCK-DIRECTORIO-01: DELETE /api/v1/occupant-types/{id}.
 */
export function useDeleteOccupantTypeMutation() {
  const queryClient = useQueryClient();

  return useMutation<void, ApiError, string>({
    mutationFn: (id: string) => apiClient.delete(`${BASE_URL}/${id}`),

    onSuccess: () => {
      toast.success("Tipo de ocupante eliminado.");
      void queryClient.invalidateQueries({ queryKey: ["occupant-types"] });
    },

    onError: (error: ApiError) => {
      switch (error.code) {
        case OCCUPANT_TYPE_ERROR_CODES.SYSTEM_CATALOG_READONLY:
          toast.error("No se puede eliminar un catálogo del sistema.");
          break;
        case OCCUPANT_TYPE_ERROR_CODES.OCCUPANT_TYPE_IN_USE:
          toast.error(
            error.message || "No se puede eliminar: está en uso por ocupantes.",
          );
          break;
        case OCCUPANT_TYPE_ERROR_CODES.OCCUPANT_TYPE_NOT_FOUND:
          toast.error("El tipo de ocupante no existe.");
          break;
        default:
          toast.error(error.message || "Error al eliminar el tipo de ocupante.");
          break;
      }
    },
  });
}
