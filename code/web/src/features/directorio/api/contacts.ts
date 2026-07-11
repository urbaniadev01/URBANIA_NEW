import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { apiClient } from "@/services/api-client";
import type {
  ContactListResponse,
  ContactDetailResponse,
  ContactPropertiesResponse,
  CreateContactRequest,
  UpdateContactRequest,
} from "../types";
import { CONTACT_ERROR_CODES } from "../types";
import type { ApiError } from "@/types/api-error";

const BASE_URL = "/api/v1/contacts";

// ── Queries ──────────────────────────────────────────────────────────────

/**
 * Hook de query para listar contactos de la organización, con búsqueda server-side.
 * Consume LOCK-DIRECTORIO-02: GET /api/v1/contacts?search=.
 */
export function useContactsQuery(search: string) {
  return useQuery<ContactListResponse, ApiError>({
    queryKey: ["contacts", search],
    queryFn: async () => {
      const params = new URLSearchParams();
      if (search) params.set("search", search);
      const qs = params.toString();
      return apiClient.get<ContactListResponse>(`${BASE_URL}${qs ? `?${qs}` : ""}`);
    },
  });
}

/**
 * Hook de query para las unidades asociadas a un contacto.
 * Consume LOCK-DIRECTORIO-02: GET /api/v1/contacts/{id}/properties.
 */
export function useContactPropertiesQuery(contactId: string | null) {
  return useQuery<ContactPropertiesResponse, ApiError>({
    queryKey: ["contacts", contactId, "properties"],
    queryFn: () =>
      apiClient.get<ContactPropertiesResponse>(`${BASE_URL}/${contactId}/properties`),
    enabled: contactId !== null,
  });
}

// ── Mutations ────────────────────────────────────────────────────────────

/**
 * Hook de mutación para crear un contacto (siempre sin user_id).
 * Consume LOCK-DIRECTORIO-02: POST /api/v1/contacts.
 */
export function useCreateContactMutation() {
  const queryClient = useQueryClient();

  return useMutation<ContactDetailResponse, ApiError, CreateContactRequest>({
    mutationFn: (data: CreateContactRequest) =>
      apiClient.post<ContactDetailResponse>(BASE_URL, data),

    onSuccess: (response) => {
      toast.success(`Contacto "${response.data.nombre}" creado.`);
      void queryClient.invalidateQueries({ queryKey: ["contacts"] });
    },

    onError: (error: ApiError) => {
      if (error.code.startsWith("HTTP_422")) {
        toast.error(error.message || "Datos inválidos. Revisa los campos.");
      } else {
        toast.error(error.message || "Error al crear el contacto.");
      }
    },
  });
}

/**
 * Hook de mutación para actualizar un contacto.
 * Consume LOCK-DIRECTORIO-02: PATCH /api/v1/contacts/{id}.
 */
export function useUpdateContactMutation() {
  const queryClient = useQueryClient();

  return useMutation<ContactDetailResponse, ApiError, { id: string; data: UpdateContactRequest }>({
    mutationFn: ({ id, data }) =>
      apiClient.patch<ContactDetailResponse>(`${BASE_URL}/${id}`, data),

    onSuccess: (response) => {
      toast.success(`Contacto "${response.data.nombre}" actualizado.`);
      void queryClient.invalidateQueries({ queryKey: ["contacts"] });
    },

    onError: (error: ApiError) => {
      switch (error.code) {
        case CONTACT_ERROR_CODES.CONTACT_NOT_FOUND:
          toast.error("El contacto no existe.");
          break;
        default:
          if (error.code.startsWith("HTTP_422")) {
            toast.error(error.message || "Datos inválidos. Revisa los campos.");
          } else {
            toast.error(error.message || "Error al actualizar el contacto.");
          }
          break;
      }
    },
  });
}

/**
 * Hook de mutación para eliminar un contacto.
 * Consume LOCK-DIRECTORIO-02: DELETE /api/v1/contacts/{id}.
 */
export function useDeleteContactMutation() {
  const queryClient = useQueryClient();

  return useMutation<void, ApiError, string>({
    mutationFn: (id: string) => apiClient.delete(`${BASE_URL}/${id}`),

    onSuccess: () => {
      toast.success("Contacto eliminado.");
      void queryClient.invalidateQueries({ queryKey: ["contacts"] });
    },

    onError: (error: ApiError) => {
      switch (error.code) {
        case CONTACT_ERROR_CODES.CONTACT_HAS_OCCUPATIONS:
          toast.error(
            error.message ||
              "Este contacto tiene unidades asignadas, quítalas primero.",
          );
          break;
        case CONTACT_ERROR_CODES.CONTACT_NOT_FOUND:
          toast.error("El contacto no existe.");
          break;
        default:
          toast.error(error.message || "Error al eliminar el contacto.");
          break;
      }
    },
  });
}
