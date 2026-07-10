import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { apiClient } from "@/services/api-client";
import type {
  CatalogoItem,
  CatalogoListResponse,
  CatalogoCreateResponse,
  CatalogoUpdateResponse,
  CreateCatalogoRequest,
  UpdateCatalogoRequest,
} from "../types";
import { CATALOGO_ERROR_CODES } from "../types";
import type { ApiError } from "@/types/api-error";

const BASE_URL = "/api/v1/property-types";

// ── Queries ──────────────────────────────────────────────────────────────

/**
 * Hook de query para listar tipos de propiedad (sistema + tenant).
 * Consume LOCK-PROPIEDADES-01: GET /api/v1/property-types.
 */
export function usePropertyTypesQuery() {
  return useQuery<CatalogoItem[], ApiError>({
    queryKey: ["property-types"],
    queryFn: async () => {
      const response = await apiClient.get<CatalogoListResponse>(BASE_URL);
      return response.data;
    },
  });
}

// ── Mutations ────────────────────────────────────────────────────────────

/**
 * Hook de mutación para crear un tipo de propiedad.
 * Consume LOCK-PROPIEDADES-01: POST /api/v1/property-types.
 */
export function useCreatePropertyTypeMutation() {
  const queryClient = useQueryClient();

  return useMutation<CatalogoCreateResponse, ApiError, CreateCatalogoRequest>({
    mutationFn: (data: CreateCatalogoRequest) =>
      apiClient.post<CatalogoCreateResponse>(BASE_URL, data),

    onSuccess: (response) => {
      toast.success(`Tipo "${response.data.nombre}" creado.`);
      void queryClient.invalidateQueries({ queryKey: ["property-types"] });
    },

    onError: (error: ApiError) => {
      switch (error.code) {
        case CATALOGO_ERROR_CODES.PROPERTY_TYPE_NAME_DUPLICATE:
          toast.error("Ya existe un tipo de propiedad con ese nombre.");
          break;
        case CATALOGO_ERROR_CODES.FORBIDDEN:
          toast.error("No tienes permiso para crear tipos de propiedad.");
          break;
        default:
          if (error.code.startsWith("HTTP_422")) {
            toast.error(error.message || "Datos inválidos. Revisa los campos.");
          } else {
            toast.error(error.message || "Error al crear el tipo de propiedad.");
          }
          break;
      }
    },
  });
}

/**
 * Hook de mutación para actualizar un tipo de propiedad.
 * Consume LOCK-PROPIEDADES-01: PATCH /api/v1/property-types/{id}.
 */
export function useUpdatePropertyTypeMutation() {
  const queryClient = useQueryClient();

  return useMutation<
    CatalogoUpdateResponse,
    ApiError,
    { id: string; data: UpdateCatalogoRequest }
  >({
    mutationFn: ({ id, data }) =>
      apiClient.patch<CatalogoUpdateResponse>(`${BASE_URL}/${id}`, data),

    onSuccess: (response) => {
      toast.success(`Tipo "${response.data.nombre}" actualizado.`);
      void queryClient.invalidateQueries({ queryKey: ["property-types"] });
    },

    onError: (error: ApiError) => {
      switch (error.code) {
        case CATALOGO_ERROR_CODES.SYSTEM_CATALOG_READONLY:
          toast.error("No se puede modificar un catálogo del sistema.");
          break;
        case CATALOGO_ERROR_CODES.PROPERTY_TYPE_NOT_FOUND:
          toast.error("El tipo de propiedad no existe.");
          break;
        case CATALOGO_ERROR_CODES.PROPERTY_TYPE_NAME_DUPLICATE:
          toast.error("Ya existe un tipo de propiedad con ese nombre.");
          break;
        default:
          if (error.code.startsWith("HTTP_422")) {
            toast.error(error.message || "Datos inválidos. Revisa los campos.");
          } else {
            toast.error(
              error.message || "Error al actualizar el tipo de propiedad.",
            );
          }
          break;
      }
    },
  });
}

/**
 * Hook de mutación para eliminar un tipo de propiedad.
 * Consume LOCK-PROPIEDADES-01: DELETE /api/v1/property-types/{id}.
 */
export function useDeletePropertyTypeMutation() {
  const queryClient = useQueryClient();

  return useMutation<void, ApiError, string>({
    mutationFn: (id: string) =>
      apiClient.delete(`${BASE_URL}/${id}`),

    onSuccess: () => {
      toast.success("Tipo de propiedad eliminado.");
      void queryClient.invalidateQueries({ queryKey: ["property-types"] });
    },

    onError: (error: ApiError) => {
      switch (error.code) {
        case CATALOGO_ERROR_CODES.SYSTEM_CATALOG_READONLY:
          toast.error("No se puede eliminar un catálogo del sistema.");
          break;
        case CATALOGO_ERROR_CODES.PROPERTY_TYPE_IN_USE:
          toast.error(
            error.message || "No se puede eliminar: está en uso por propiedades.",
          );
          break;
        case CATALOGO_ERROR_CODES.PROPERTY_TYPE_NOT_FOUND:
          toast.error("El tipo de propiedad no existe.");
          break;
        default:
          toast.error(
            error.message || "Error al eliminar el tipo de propiedad.",
          );
          break;
      }
    },
  });
}
