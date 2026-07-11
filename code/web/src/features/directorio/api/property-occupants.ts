import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { apiClient } from "@/services/api-client";
import type {
  PropertyOccupantListResponse,
  PropertyOccupantDetailResponse,
  CreatePropertyOccupantRequest,
  UpdatePropertyOccupantRequest,
} from "../types";
import { PROPERTY_OCCUPANT_ERROR_CODES } from "../types";
import type { ApiError } from "@/types/api-error";

// ── Queries ──────────────────────────────────────────────────────────────

/**
 * Hook de query para los ocupantes activos de una unidad.
 * Consume LOCK-DIRECTORIO-03: GET /api/v1/properties/{id}/occupants.
 */
export function usePropertyOccupantsQuery(propertyId: string | null) {
  return useQuery<PropertyOccupantListResponse, ApiError>({
    queryKey: ["property-occupants", propertyId],
    queryFn: () =>
      apiClient.get<PropertyOccupantListResponse>(
        `/api/v1/properties/${propertyId}/occupants`,
      ),
    enabled: propertyId !== null,
  });
}

// ── Mutations ────────────────────────────────────────────────────────────

/**
 * Hook de mutación para asignar un contacto a una unidad.
 * Consume LOCK-DIRECTORIO-03: POST /api/v1/properties/{id}/occupants.
 */
export function useAssignOccupantMutation(propertyId: string | null) {
  const queryClient = useQueryClient();

  return useMutation<
    PropertyOccupantDetailResponse,
    ApiError,
    CreatePropertyOccupantRequest
  >({
    mutationFn: (data: CreatePropertyOccupantRequest) =>
      apiClient.post<PropertyOccupantDetailResponse>(
        `/api/v1/properties/${propertyId}/occupants`,
        data,
      ),

    onSuccess: () => {
      toast.success("Ocupante asignado.");
      void queryClient.invalidateQueries({
        queryKey: ["property-occupants", propertyId],
      });
    },

    onError: (error: ApiError) => {
      switch (error.code) {
        case PROPERTY_OCCUPANT_ERROR_CODES.OCCUPANT_ASSIGNMENT_DUPLICATE:
          toast.error(
            error.message || "Este contacto ya está asignado con ese tipo.",
          );
          break;
        default:
          if (error.code.startsWith("HTTP_422")) {
            toast.error(error.message || "Datos inválidos. Revisa los campos.");
          } else {
            toast.error(error.message || "Error al asignar el ocupante.");
          }
          break;
      }
    },
  });
}

/**
 * Hook de mutación para actualizar una asignación (tipo/es_principal).
 * Consume LOCK-DIRECTORIO-03: PATCH /api/v1/property-occupants/{id}.
 */
export function useUpdatePropertyOccupantMutation(propertyId: string | null) {
  const queryClient = useQueryClient();

  return useMutation<
    PropertyOccupantDetailResponse,
    ApiError,
    { id: string; data: UpdatePropertyOccupantRequest }
  >({
    mutationFn: ({ id, data }) =>
      apiClient.patch<PropertyOccupantDetailResponse>(
        `/api/v1/property-occupants/${id}`,
        data,
      ),

    onSuccess: () => {
      toast.success("Asignación actualizada.");
      void queryClient.invalidateQueries({
        queryKey: ["property-occupants", propertyId],
      });
    },

    onError: (error: ApiError) => {
      switch (error.code) {
        case PROPERTY_OCCUPANT_ERROR_CODES.OCCUPANT_ASSIGNMENT_DUPLICATE:
          toast.error(
            error.message || "Este contacto ya está asignado con ese tipo.",
          );
          break;
        default:
          if (error.code.startsWith("HTTP_422")) {
            toast.error(error.message || "Datos inválidos. Revisa los campos.");
          } else {
            toast.error(error.message || "Error al actualizar la asignación.");
          }
          break;
      }
    },
  });
}

/**
 * Hook de mutación para desasignar un ocupante.
 * Consume LOCK-DIRECTORIO-03: DELETE /api/v1/property-occupants/{id}.
 */
export function useUnassignOccupantMutation(propertyId: string | null) {
  const queryClient = useQueryClient();

  return useMutation<void, ApiError, string>({
    mutationFn: (id: string) =>
      apiClient.delete(`/api/v1/property-occupants/${id}`),

    onSuccess: () => {
      toast.success("Ocupante desasignado.");
      void queryClient.invalidateQueries({
        queryKey: ["property-occupants", propertyId],
      });
    },

    onError: (error: ApiError) => {
      toast.error(error.message || "Error al desasignar el ocupante.");
    },
  });
}
