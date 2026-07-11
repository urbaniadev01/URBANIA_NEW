import { z } from "zod";
import type { CatalogoItem } from "@/features/propiedades/types";

/**
 * Tipos de DIRECTORIO. El catálogo de tipos de ocupante tiene exactamente la
 * misma forma que los catálogos de PROPIEDADES (id, organization_id, nombre,
 * descripcion, created_by, updated_by, timestamps) — reexportamos los tipos
 * genéricos de catálogo en vez de duplicarlos (ver nota de DIRECTORIO-B05).
 * Ver _state/contracts/CONTRACT_LOCKS.md#LOCK-DIRECTORIO-01
 */
export type {
  CatalogoItem as OccupantTypeItem,
  CatalogoListResponse as OccupantTypeListResponse,
  CatalogoCreateResponse as OccupantTypeCreateResponse,
  CatalogoUpdateResponse as OccupantTypeUpdateResponse,
  CreateCatalogoRequest as CreateOccupantTypeRequest,
  UpdateCatalogoRequest as UpdateOccupantTypeRequest,
  CatalogoFormValues as OccupantTypeFormValues,
} from "@/features/propiedades/types";
export { isSystemCatalog, catalogoFormSchema as occupantTypeFormSchema } from "@/features/propiedades/types";

/** Códigos de error específicos de /occupant-types (LOCK-DIRECTORIO-01) */
export const OCCUPANT_TYPE_ERROR_CODES = {
  SYSTEM_CATALOG_READONLY: "SYSTEM_CATALOG_READONLY",
  OCCUPANT_TYPE_IN_USE: "OCCUPANT_TYPE_IN_USE",
  OCCUPANT_TYPE_NAME_DUPLICATE: "OCCUPANT_TYPE_NAME_DUPLICATE",
  OCCUPANT_TYPE_NOT_FOUND: "OCCUPANT_TYPE_NOT_FOUND",
} as const;

export type OccupantTypeErrorCode =
  (typeof OCCUPANT_TYPE_ERROR_CODES)[keyof typeof OCCUPANT_TYPE_ERROR_CODES];

// ──────────────────────────────────────────────────────────────────────────
// Contactos y autoservicio — LOCK-DIRECTORIO-02
// ──────────────────────────────────────────────────────────────────────────

/** Item de contacto — response de GET/POST/PATCH /contacts y /me/contact */
export interface ContactItem {
  id: string;
  organization_id: string;
  /** null = contacto sin cuenta de usuario (propietario ausente, familiar, etc.) */
  user_id: string | null;
  nombre: string;
  email: string;
  telefono: string | null;
  created_by: string | null;
  updated_by: string | null;
  created_at: string;
  updated_at: string;
}

/** Determina si un contacto tiene cuenta de usuario asociada */
export function hasAccount(contact: ContactItem): boolean {
  return contact.user_id !== null;
}

// ── Response envelopes ──────────────────────────────────────────────────

/** GET /contacts — listado paginado */
export interface ContactListResponse {
  data: ContactItem[];
  meta: { next_cursor: string | null };
}

/** POST /contacts, PATCH /contacts/{id}, GET/PATCH /me/contact */
export interface ContactDetailResponse {
  data: ContactItem;
}

/** GET /contacts/{id}/properties — unidades asociadas (mismo shape que PROPIEDADES) */
export interface ContactPropertiesResponse {
  data: Array<{
    id: string;
    condominium_id: string;
    tower_id: string | null;
    property_type_id: string;
    property_status_id: string;
    codigo: string;
    piso: number | null;
    created_by: string | null;
    updated_by: string | null;
    created_at: string;
    updated_at: string;
  }>;
}

// ── Request DTOs ─────────────────────────────────────────────────────────

/** POST /contacts — nunca acepta user_id, el backend lo ignora si se envía */
export interface CreateContactRequest {
  nombre: string;
  email: string;
  telefono?: string;
}

/** PATCH /contacts/{id} y PATCH /me/contact */
export interface UpdateContactRequest {
  nombre?: string;
  email?: string;
  telefono?: string | null;
}

// ── Zod schema ───────────────────────────────────────────────────────────

export const contactFormSchema = z.object({
  nombre: z
    .string()
    .min(1, "El nombre es obligatorio.")
    .max(255, "El nombre no puede exceder 255 caracteres."),
  email: z
    .string()
    .min(1, "El email es obligatorio.")
    .email("El email no tiene un formato válido.")
    .max(255, "El email no puede exceder 255 caracteres."),
  telefono: z
    .string()
    .max(50, "El teléfono no puede exceder 50 caracteres.")
    .optional()
    .or(z.literal("")),
});

export type ContactFormValues = z.infer<typeof contactFormSchema>;

// ── Error codes ──────────────────────────────────────────────────────────

export const CONTACT_ERROR_CODES = {
  CONTACT_HAS_OCCUPATIONS: "CONTACT_HAS_OCCUPATIONS",
  CONTACT_NOT_FOUND: "CONTACT_NOT_FOUND",
  FORBIDDEN: "FORBIDDEN",
} as const;

export type ContactErrorCode =
  (typeof CONTACT_ERROR_CODES)[keyof typeof CONTACT_ERROR_CODES];

// ──────────────────────────────────────────────────────────────────────────
// Asignación de ocupantes — LOCK-DIRECTORIO-03
// ──────────────────────────────────────────────────────────────────────────

/** Item de asignación — response de GET/POST/PATCH de property-occupants */
export interface PropertyOccupantItem {
  id: string;
  property_id: string;
  contact_id: string;
  occupant_type_id: string;
  es_principal: boolean;
  created_by: string | null;
  updated_by: string | null;
  created_at: string;
  updated_at: string;
  /** Nunca incluye email/telefono — ver R-DIR-06 */
  contact?: { id: string; nombre: string };
  occupant_type?: CatalogoItem;
}

// ── Response envelopes ──────────────────────────────────────────────────

/** GET /properties/{id}/occupants */
export interface PropertyOccupantListResponse {
  data: PropertyOccupantItem[];
}

/** POST /properties/{id}/occupants, PATCH /property-occupants/{id} */
export interface PropertyOccupantDetailResponse {
  data: PropertyOccupantItem;
}

// ── Request DTOs ─────────────────────────────────────────────────────────

export interface CreatePropertyOccupantRequest {
  contact_id: string;
  occupant_type_id: string;
  es_principal?: boolean;
}

export interface UpdatePropertyOccupantRequest {
  occupant_type_id?: string;
  es_principal?: boolean;
}

// ── Error codes ──────────────────────────────────────────────────────────

export const PROPERTY_OCCUPANT_ERROR_CODES = {
  OCCUPANT_ASSIGNMENT_DUPLICATE: "OCCUPANT_ASSIGNMENT_DUPLICATE",
  PROPERTY_NOT_FOUND: "PROPERTY_NOT_FOUND",
  FORBIDDEN: "FORBIDDEN",
} as const;

export type PropertyOccupantErrorCode =
  (typeof PROPERTY_OCCUPANT_ERROR_CODES)[keyof typeof PROPERTY_OCCUPANT_ERROR_CODES];
