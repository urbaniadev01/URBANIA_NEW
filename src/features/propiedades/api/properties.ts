import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useInfiniteQuery } from "@tanstack/react-query";
import { toast } from "sonner";
import { apiClient } from "@/services/api-client";
import type {
  PropertyListItem,
  PropertyListResponse,
  PropertyCreateResponse,
  PropertyUpdateResponse,
  CreatePropertyRequest,
  UpdatePropertyRequest,
  PropertyFilters,
} from "../types";
import { PROPERTY_ERROR_CODES } from "../types";
import type { ApiError } from "@/types/api-error";

const PAGE_SIZE = 15;

// ── Queries ──────────────────────────────────────────────────────────────

/**
 * Hook de query infinita para listar unidades de un condominio con filtros combinables.
 * Consume LOCK-PROPIEDADES-03: GET /api/v1/condominiums/{id}/properties.
 */
export function usePropertiesInfiniteQuery(
  condominiumId: string | undefined,
  filters: PropertyFilters,
) {
  return useInfiniteQuery<PropertyListResponse, ApiError>({
    queryKey: ["properties", condominiumId, filters],
    queryFn: async ({ pageParam }) => {
      const params = new URLSearchParams();
      if (filters.tower_id) params.set("tower_id", filters.tower_id);
      if (filters.type_id) params.set("type_id", filters.type_id);
      if (filters.status_id) params.set("status_id", filters.status_id);
      if (filters.search) params.set("search", filters.search);
      if (pageParam) params.set("cursor", pageParam as string);
      params.set("limit", String(PAGE_SIZE));

      const qs = params.toString();
      const url = `/api/v1/condominiums/${condominiumId}/properties${qs ? `?${qs}` : ""}`;
      return apiClient.get<PropertyListResponse>(url);
    },
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.meta.next_cursor ?? undefined,
    enabled: !!condominiumId,
    staleTime: 30_000,
  });
}

// ── Mutations: CRUD ──────────────────────────────────────────────────────

/**
 * Hook de mutación para crear una unidad.
 * Consume LOCK-PROPIEDADES-03: POST /api/v1/condominiums/{id}/properties.
 */
export function useCreatePropertyMutation() {
  const queryClient = useQueryClient();

  return useMutation<
    PropertyCreateResponse,
    ApiError,
    { condominiumId: string; data: CreatePropertyRequest }
  >({
    mutationFn: ({ condominiumId, data }) =>
      apiClient.post<PropertyCreateResponse>(
        `/api/v1/condominiums/${condominiumId}/properties`,
        data,
      ),

    onSuccess: (response, variables) => {
      toast.success(`Unidad "${response.property.codigo}" creada.`);
      void queryClient.invalidateQueries({
        queryKey: ["properties", variables.condominiumId],
      });
    },

    onError: (error: ApiError) => {
      switch (error.code) {
        case PROPERTY_ERROR_CODES.PROPERTY_CODE_DUPLICATE:
          toast.error("Ya existe una unidad con ese código en este condominio.");
          break;
        case PROPERTY_ERROR_CODES.TOWER_CONDOMINIUM_MISMATCH:
          toast.error("La torre seleccionada no pertenece a este condominio.");
          break;
        case PROPERTY_ERROR_CODES.CONDOMINIUM_NOT_FOUND:
          toast.error("El condominio no existe.");
          break;
        case PROPERTY_ERROR_CODES.FORBIDDEN:
          toast.error("No tienes permiso para crear unidades.");
          break;
        default:
          if (error.code?.startsWith("HTTP_422")) {
            toast.error(
              error.message || "Datos inválidos. Revisa los campos.",
            );
          } else {
            toast.error(error.message || "Error al crear la unidad.");
          }
          break;
      }
    },
  });
}

/**
 * Hook de mutación para actualizar una unidad.
 * Consume LOCK-PROPIEDADES-03: PATCH /api/v1/properties/{id}.
 */
export function useUpdatePropertyMutation() {
  const queryClient = useQueryClient();

  return useMutation<
    PropertyUpdateResponse,
    ApiError,
    { id: string; condominiumId: string; data: UpdatePropertyRequest }
  >({
    mutationFn: ({ id, data }) =>
      apiClient.patch<PropertyUpdateResponse>(`/api/v1/properties/${id}`, data),

    onSuccess: (response, variables) => {
      toast.success(`Unidad "${response.property.codigo}" actualizada.`);
      void queryClient.invalidateQueries({
        queryKey: ["properties", variables.condominiumId],
      });
    },

    onError: (error: ApiError) => {
      switch (error.code) {
        case PROPERTY_ERROR_CODES.PROPERTY_CODE_DUPLICATE:
          toast.error("Ya existe una unidad con ese código en este condominio.");
          break;
        case PROPERTY_ERROR_CODES.TOWER_CONDOMINIUM_MISMATCH:
          toast.error("La torre seleccionada no pertenece a este condominio.");
          break;
        case PROPERTY_ERROR_CODES.PROPERTY_NOT_FOUND:
          toast.error("La unidad no existe.");
          break;
        case PROPERTY_ERROR_CODES.FORBIDDEN:
          toast.error("No tienes permiso para modificar esta unidad.");
          break;
        default:
          if (error.code?.startsWith("HTTP_422")) {
            toast.error(
              error.message || "Datos inválidos. Revisa los campos.",
            );
          } else {
            toast.error(error.message || "Error al actualizar la unidad.");
          }
          break;
      }
    },
  });
}

/**
 * Hook de mutación para eliminar una unidad.
 * Consume LOCK-PROPIEDADES-03: DELETE /api/v1/properties/{id}.
 */
export function useDeletePropertyMutation() {
  const queryClient = useQueryClient();

  return useMutation<
    void,
    ApiError,
    { id: string; condominiumId: string }
  >({
    mutationFn: ({ id }) => apiClient.delete(`/api/v1/properties/${id}`),

    onSuccess: (_data, variables) => {
      toast.success("Unidad eliminada.");
      void queryClient.invalidateQueries({
        queryKey: ["properties", variables.condominiumId],
      });
    },

    onError: (error: ApiError) => {
      switch (error.code) {
        case PROPERTY_ERROR_CODES.PROPERTY_HAS_OCCUPANTS:
          toast.error(
            error.message ||
              "No se puede eliminar: la unidad tiene ocupantes activos.",
          );
          break;
        case PROPERTY_ERROR_CODES.PROPERTY_NOT_FOUND:
          toast.error("La unidad no existe.");
          break;
        default:
          toast.error(error.message || "Error al eliminar la unidad.");
          break;
      }
    },
  });
}

// ── Batch operations ─────────────────────────────────────────────────────

/** Resultado individual de una operación batch */
export interface BatchOperationResult {
  propertyId: string;
  codigo: string;
  success: boolean;
  error?: string;
}

/**
 * Hook de mutación para cambio de estado en lote.
 * Aplica PATCH a cada unidad y reporta resultados individuales.
 */
export function useBatchUpdateStatusMutation() {
  const queryClient = useQueryClient();

  return useMutation<
    BatchOperationResult[],
    ApiError,
    {
      condominiumId: string;
      items: Array<{ id: string; codigo: string }>;
      statusId: string;
    }
  >({
    mutationFn: async ({ items, statusId }) => {
      const results = await Promise.allSettled(
        items.map(async (item) => {
          await apiClient.patch(`/api/v1/properties/${item.id}`, {
            property_status_id: statusId,
          });
          return item;
        }),
      );

      return results.map((result, index) => {
        if (result.status === "fulfilled") {
          return {
            propertyId: result.value.id,
            codigo: result.value.codigo,
            success: true,
          };
        }
        return {
          propertyId: items[index]!.id,
          codigo: items[index]!.codigo,
          success: false,
          error:
            result.reason instanceof Error
              ? result.reason.message
              : "Error desconocido",
        };
      });
    },

    onSuccess: (results, variables) => {
      const succeeded = results.filter((r) => r.success).length;
      const failed = results.filter((r) => !r.success).length;

      if (failed === 0) {
        toast.success(`${succeeded} ${succeeded === 1 ? "unidad actualizada" : "unidades actualizadas"}.`);
      } else {
        toast.warning(
          `${succeeded} ${succeeded === 1 ? "actualizada" : "actualizadas"}, ${failed} ${failed === 1 ? "falló" : "fallaron"}.`,
        );
        // Mostrar errores individuales
        for (const r of results) {
          if (!r.success) {
            toast.error(`"${r.codigo}": ${r.error}`);
          }
        }
      }
      void queryClient.invalidateQueries({
        queryKey: ["properties", variables.condominiumId],
      });
    },
  });
}

/**
 * Hook de mutación para eliminación en lote.
 * Aplica DELETE a cada unidad y reporta resultados individuales.
 */
export function useBatchDeleteMutation() {
  const queryClient = useQueryClient();

  return useMutation<
    BatchOperationResult[],
    ApiError,
    {
      condominiumId: string;
      items: Array<{ id: string; codigo: string }>;
    }
  >({
    mutationFn: async ({ items }) => {
      const results = await Promise.allSettled(
        items.map(async (item) => {
          await apiClient.delete(`/api/v1/properties/${item.id}`);
          return item;
        }),
      );

      return results.map((result, index) => {
        if (result.status === "fulfilled") {
          return {
            propertyId: result.value.id,
            codigo: result.value.codigo,
            success: true,
          };
        }
        return {
          propertyId: items[index]!.id,
          codigo: items[index]!.codigo,
          success: false,
          error:
            result.reason instanceof Error
              ? result.reason.message
              : "Error desconocido",
        };
      });
    },

    onSuccess: (results, variables) => {
      const succeeded = results.filter((r) => r.success).length;
      const failed = results.filter((r) => !r.success).length;

      if (failed === 0) {
        toast.success(`${succeeded} ${succeeded === 1 ? "unidad eliminada" : "unidades eliminadas"}.`);
      } else {
        if (succeeded > 0) {
          toast.success(`${succeeded} ${succeeded === 1 ? "eliminada" : "eliminadas"}.`);
        }
        toast.warning(`${failed} ${failed === 1 ? "unidad no pudo eliminarse" : "unidades no pudieron eliminarse"}.`);
        for (const r of results) {
          if (!r.success) {
            toast.error(`"${r.codigo}": ${r.error}`);
          }
        }
      }
      void queryClient.invalidateQueries({
        queryKey: ["properties", variables.condominiumId],
      });
    },
  });
}

// ── Helpers ──────────────────────────────────────────────────────────────

/** Aplana todas las páginas de useInfiniteQuery en un solo arreglo */
export function flattenProperties(
  pages: PropertyListResponse[] | undefined,
): PropertyListItem[] {
  if (!pages) return [];
  return pages.flatMap((page) => page.data);
}
