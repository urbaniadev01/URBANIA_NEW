import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { apiClient } from "@/services/api-client";
import type { ContactDetailResponse, UpdateContactRequest } from "../types";
import type { ApiError } from "@/types/api-error";

const BASE_URL = "/api/v1/me/contact";

/**
 * Hook de query para el propio contacto del usuario autenticado — sin permisos
 * especiales (R-DIR-04).
 * Consume LOCK-DIRECTORIO-02: GET /api/v1/me/contact.
 */
export function useMeContactQuery() {
  return useQuery<ContactDetailResponse, ApiError>({
    queryKey: ["me-contact"],
    queryFn: () => apiClient.get<ContactDetailResponse>(BASE_URL),
  });
}

/**
 * Hook de mutación para actualizar el propio contacto.
 * Consume LOCK-DIRECTORIO-02: PATCH /api/v1/me/contact.
 */
export function useUpdateMeContactMutation() {
  const queryClient = useQueryClient();

  return useMutation<ContactDetailResponse, ApiError, UpdateContactRequest>({
    mutationFn: (data: UpdateContactRequest) =>
      apiClient.patch<ContactDetailResponse>(BASE_URL, data),

    onSuccess: () => {
      toast.success("Perfil actualizado.");
      void queryClient.invalidateQueries({ queryKey: ["me-contact"] });
    },

    onError: (error: ApiError) => {
      if (error.code.startsWith("HTTP_422")) {
        toast.error(error.message || "Datos inválidos. Revisa los campos.");
      } else {
        toast.error(error.message || "Error al actualizar tu perfil.");
      }
    },
  });
}
