import { useMutation, useQuery, useQueries, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { apiClient } from "@/services/api-client";
import type {
  CoefficientItem,
  CoefficientListResponse,
  CondominioTreeResponse,
  UpdateCoefficientsRequest,
  UpdateCoefficientsResponse,
} from "../types";
import { COEFFICIENT_ERROR_CODES } from "../types";
import type { ApiError } from "@/types/api-error";

// ── Queries ──────────────────────────────────────────────────────────────

/**
 * Hook de query para obtener el tree de un condominio.
 * Consume LOCK-PROPIEDADES-04: GET /api/v1/condominiums/{id}/tree.
 */
export function useCondominioTreeQuery(condominiumId: string | undefined) {
  return useQuery<CondominioTreeResponse, ApiError>({
    queryKey: ["condominiums", condominiumId, "tree"],
    queryFn: async () => {
      return apiClient.get<CondominioTreeResponse>(
        `/api/v1/condominiums/${condominiumId}/tree`,
      );
    },
    enabled: !!condominiumId,
    staleTime: 5 * 60 * 1000,
  });
}

/**
 * Hook de query para obtener los coeficientes de una propiedad específica.
 * Consume LOCK-PROPIEDADES-04: GET /api/v1/properties/{id}/coefficients.
 */
export function usePropertyCoefficientsQuery(propertyId: string | undefined) {
  return useQuery<CoefficientItem[], ApiError>({
    queryKey: ["properties", propertyId, "coefficients"],
    queryFn: async () => {
      const response = await apiClient.get<CoefficientListResponse>(
        `/api/v1/properties/${propertyId}/coefficients`,
      );
      return response.data;
    },
    enabled: !!propertyId,
    staleTime: 60_000, // 1 min — los coeficientes cambian poco
  });
}

/**
 * Hook que obtiene los coeficientes de múltiples propiedades en paralelo.
 * Usa useQueries de TanStack Query para paralelizar las llamadas.
 */
export function useBatchPropertyCoefficientsQueries(propertyIds: string[]) {
  return useQueries({
    queries: propertyIds.map((propertyId) => ({
      queryKey: ["properties", propertyId, "coefficients"] as const,
      queryFn: async (): Promise<CoefficientItem[]> => {
        const response = await apiClient.get<CoefficientListResponse>(
          `/api/v1/properties/${propertyId}/coefficients`,
        );
        return response.data;
      },
      enabled: propertyIds.length > 0 && !!propertyId,
      staleTime: 60_000,
    })),
    combine: (results) => {
      // Construir un mapa property_id → coefficients[]
      const map = new Map<string, CoefficientItem[]>();
      for (let i = 0; i < results.length; i++) {
        const result = results[i]!;
        if (result.data) {
          map.set(propertyIds[i]!, result.data);
        }
      }
      return {
        coefficientsMap: map,
        isLoading: results.some((r) => r.isLoading),
        isError: results.some((r) => r.isError),
        errors: results.filter((r) => r.error != null).map((r) => r.error),
      };
    },
  });
}

// ── Mutations ────────────────────────────────────────────────────────────

/**
 * Hook de mutación para guardar coeficientes masivamente.
 * Consume LOCK-PROPIEDADES-04: PATCH /api/v1/condominiums/{id}/coefficients.
 *
 * Comportamiento:
 * - Atómico: si un item falla, rollback completo en el servidor.
 * - R-05: Coeficiente vigente único — el servidor cierra el anterior.
 * - R-06: Warning COEFFICIENT_SUM_MISMATCH si suma copropiedad ≠ 1.0 (no bloqueante).
 */
export function useUpdateCoefficientsMutation(condominiumId: string | undefined) {
  const queryClient = useQueryClient();

  return useMutation<
    UpdateCoefficientsResponse,
    ApiError,
    UpdateCoefficientsRequest
  >({
    mutationFn: (data: UpdateCoefficientsRequest) =>
      apiClient.patch<UpdateCoefficientsResponse>(
        `/api/v1/condominiums/${condominiumId}/coefficients`,
        data,
      ),

    onSuccess: (response) => {
      // Verificar warnings de suma de copropiedad
      if (response.warnings && response.warnings.length > 0) {
        for (const warning of response.warnings) {
          if (warning.code === COEFFICIENT_ERROR_CODES.COEFFICIENT_SUM_MISMATCH) {
            const pct = (warning.detail.sum * 100).toFixed(1);
            toast.warning(
              `Coeficientes guardados, pero la suma de copropiedad es ${pct}% (debe ser 100%).`,
            );
            return; // un solo toast de warning es suficiente
          }
        }
      }

      const count = response.data.length;
      toast.success(
        `${count} ${count === 1 ? "coeficiente actualizado" : "coeficientes actualizados"}.`,
      );

      // Invalidar todas las queries de coeficientes de este condominio
      void queryClient.invalidateQueries({
        queryKey: ["properties"],
        predicate: (query) => {
          const key = query.queryKey;
          return (
            Array.isArray(key) &&
            key.length === 3 &&
            key[0] === "properties" &&
            key[2] === "coefficients"
          );
        },
      });
    },

    onError: (error: ApiError) => {
      switch (error.code) {
        case COEFFICIENT_ERROR_CODES.COEFFICIENT_OUT_OF_RANGE:
          toast.error(
            error.message ||
              "Uno o más valores están fuera del rango permitido (0–1).",
          );
          break;
        case COEFFICIENT_ERROR_CODES.COEFFICIENT_INVALID_TYPE:
          toast.error(
            error.message ||
              "Tipo de coeficiente inválido. Use: copropiedad, parqueadero, deposito, mantenimiento.",
          );
          break;
        case COEFFICIENT_ERROR_CODES.PROPERTY_NOT_IN_CONDOMINIUM:
          toast.error(
            error.message ||
              "Una o más unidades no pertenecen a este condominio.",
          );
          break;
        case COEFFICIENT_ERROR_CODES.PROPERTY_NOT_FOUND:
          toast.error("Una o más unidades no existen.");
          break;
        case COEFFICIENT_ERROR_CODES.CONDOMINIUM_NOT_FOUND:
          toast.error("El condominio no existe.");
          break;
        default:
          if (error.code?.startsWith("HTTP_422")) {
            toast.error(
              error.message || "Datos inválidos. Revisa los valores ingresados.",
            );
          } else {
            toast.error(error.message || "Error al guardar los coeficientes.");
          }
          break;
      }
    },
  });
}
