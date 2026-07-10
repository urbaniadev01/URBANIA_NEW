import { z } from "zod";

/**
 * Tipos de catálogos de propiedad — coinciden exactamente con LOCK-PROPIEDADES-01.
 * Ver _state/contracts/CONTRACT_LOCKS.md#LOCK-PROPIEDADES-01
 */

// ── Entidad base de catálogo (compartida entre tipos y estados) ──────────

/** Item individual de catálogo — response de GET index y GET show */
export interface CatalogoItem {
  id: string;
  /** null = sistema, UUID = personalizado del tenant */
  organization_id: string | null;
  nombre: string;
  descripcion: string | null;
  created_by: string | null;
  updated_by: string | null;
  created_at: string;
  updated_at: string;
}

/** Determina si un item es del sistema (no editable ni eliminable) */
export function isSystemCatalog(item: CatalogoItem): boolean {
  return item.organization_id === null;
}

// ── Response envelopes ──────────────────────────────────────────────────

/** GET /property-types y GET /property-statuses — listado */
export interface CatalogoListResponse {
  data: CatalogoItem[];
}

/** POST /property-types y POST /property-statuses — crear */
export interface CatalogoCreateResponse {
  data: CatalogoItem;
}

/** PATCH /property-types/{id} y PATCH /property-statuses/{id} — actualizar */
export interface CatalogoUpdateResponse {
  data: CatalogoItem;
}

// ── Request DTOs ─────────────────────────────────────────────────────────

/** POST — body para crear un catálogo */
export interface CreateCatalogoRequest {
  nombre: string;
  descripcion?: string;
}

/** PATCH — body para actualizar un catálogo (al menos un campo) */
export interface UpdateCatalogoRequest {
  nombre?: string;
  descripcion?: string;
}

// ── Zod schemas para formularios ─────────────────────────────────────────

export const catalogoFormSchema = z.object({
  nombre: z
    .string()
    .min(1, "El nombre es obligatorio.")
    .max(255, "El nombre no puede exceder 255 caracteres."),
  descripcion: z
    .string()
    .max(1000, "La descripción no puede exceder 1000 caracteres.")
    .optional()
    .or(z.literal("")),
});

export type CatalogoFormValues = z.infer<typeof catalogoFormSchema>;

// ── Códigos de error ─────────────────────────────────────────────────────

/** Códigos de error específicos de los endpoints de catálogos (LOCK-PROPIEDADES-01) */
export const CATALOGO_ERROR_CODES = {
  SYSTEM_CATALOG_READONLY: "SYSTEM_CATALOG_READONLY",
  PROPERTY_TYPE_IN_USE: "PROPERTY_TYPE_IN_USE",
  PROPERTY_STATUS_IN_USE: "PROPERTY_STATUS_IN_USE",
  PROPERTY_TYPE_NAME_DUPLICATE: "PROPERTY_TYPE_NAME_DUPLICATE",
  PROPERTY_STATUS_NAME_DUPLICATE: "PROPERTY_STATUS_NAME_DUPLICATE",
  PROPERTY_TYPE_NOT_FOUND: "PROPERTY_TYPE_NOT_FOUND",
  PROPERTY_STATUS_NOT_FOUND: "PROPERTY_STATUS_NOT_FOUND",
  FORBIDDEN: "FORBIDDEN",
} as const;

export type CatalogoErrorCode =
  (typeof CATALOGO_ERROR_CODES)[keyof typeof CATALOGO_ERROR_CODES];

// ──────────────────────────────────────────────────────────────────────────
// Condominios y Torres — LOCK-PROPIEDADES-02
// ──────────────────────────────────────────────────────────────────────────

// ── Entidades ────────────────────────────────────────────────────────────

/** Item de condominio en listado (GET /condominiums) */
export interface CondominioItem {
  id: string;
  organization_id: string;
  nombre: string;
  direccion: string | null;
  nit: string | null;
  created_by: string | null;
  updated_by: string | null;
  created_at: string;
  updated_at: string;
}

/** Item de torre en listado (GET /condominiums/{id}/towers o anidado en detail) */
export interface TorreItem {
  id: string;
  condominium_id: string;
  nombre: string;
  created_by: string | null;
  updated_by: string | null;
  created_at: string;
  updated_at: string;
}

/** Detalle de condominio — incluye torres anidadas (GET /condominiums/{id}) */
export interface CondominioDetail {
  id: string;
  organization_id: string;
  nombre: string;
  direccion: string | null;
  nit: string | null;
  towers: TorreItem[];
  created_by: string | null;
  updated_by: string | null;
  created_at: string;
  updated_at: string;
}

// ── Response envelopes ──────────────────────────────────────────────────

/** GET /condominiums */
export interface CondominioListResponse {
  data: CondominioItem[];
}

/** GET /condominiums/{id} */
export interface CondominioShowResponse {
  condominium: CondominioDetail;
}

/** POST /condominiums */
export interface CondominioCreateResponse {
  condominium: CondominioItem;
}

/** PATCH /condominiums/{id} */
export interface CondominioUpdateResponse {
  condominium: CondominioItem;
}

/** GET /condominiums/{id}/towers */
export interface TorreListResponse {
  data: TorreItem[];
}

/** POST /condominiums/{id}/towers */
export interface TorreCreateResponse {
  tower: TorreItem;
}

/** PATCH /towers/{id} */
export interface TorreUpdateResponse {
  tower: TorreItem;
}

// ── Request DTOs ─────────────────────────────────────────────────────────

export interface CreateCondominioRequest {
  nombre: string;
  direccion?: string;
  nit?: string;
}

export interface UpdateCondominioRequest {
  nombre?: string;
  direccion?: string;
  nit?: string;
}

export interface CreateTorreRequest {
  nombre: string;
}

export interface UpdateTorreRequest {
  nombre: string;
}

// ── Zod schemas ──────────────────────────────────────────────────────────

export const condominioFormSchema = z.object({
  nombre: z
    .string()
    .min(1, "El nombre es obligatorio.")
    .max(255, "El nombre no puede exceder 255 caracteres."),
  direccion: z
    .string()
    .max(500, "La dirección no puede exceder 500 caracteres.")
    .optional()
    .or(z.literal("")),
  nit: z
    .string()
    .max(50, "El NIT no puede exceder 50 caracteres.")
    .optional()
    .or(z.literal("")),
});

export type CondominioFormValues = z.infer<typeof condominioFormSchema>;

export const torreFormSchema = z.object({
  nombre: z
    .string()
    .min(1, "El nombre es obligatorio.")
    .max(255, "El nombre no puede exceder 255 caracteres."),
});

export type TorreFormValues = z.infer<typeof torreFormSchema>;

// ── Error codes ──────────────────────────────────────────────────────────

export const CONDOMINIO_ERROR_CODES = {
  CONDOMINIUM_NAME_DUPLICATE: "CONDOMINIUM_NAME_DUPLICATE",
  TOWER_NAME_DUPLICATE: "TOWER_NAME_DUPLICATE",
  CONDOMINIUM_HAS_TOWERS: "CONDOMINIUM_HAS_TOWERS",
  CONDOMINIUM_HAS_PROPERTIES: "CONDOMINIUM_HAS_PROPERTIES",
  TOWER_HAS_PROPERTIES: "TOWER_HAS_PROPERTIES",
  CONDOMINIUM_NOT_FOUND: "CONDOMINIUM_NOT_FOUND",
  TOWER_NOT_FOUND: "TOWER_NOT_FOUND",
  FORBIDDEN: "FORBIDDEN",
} as const;

export type CondominioErrorCode =
  (typeof CONDOMINIO_ERROR_CODES)[keyof typeof CONDOMINIO_ERROR_CODES];

// ──────────────────────────────────────────────────────────────────────────
// Unidades (Properties) — LOCK-PROPIEDADES-03
// ──────────────────────────────────────────────────────────────────────────

// ── Entidades ────────────────────────────────────────────────────────────

/** Item de unidad en listado (GET /condominiums/{id}/properties) — sin area_m2 (R-10) */
export interface PropertyListItem {
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
}

/** Detalle de unidad (GET /properties/{id}, POST/PATCH response) — incluye area_m2 */
export interface PropertyDetail {
  id: string;
  condominium_id: string;
  tower_id: string | null;
  property_type_id: string;
  property_status_id: string;
  codigo: string;
  piso: number | null;
  area_m2: number | null;
  created_by: string | null;
  updated_by: string | null;
  created_at: string;
  updated_at: string;
  // Relaciones anidadas (solo en GET show, no en POST/PATCH response body)
  type?: { id: string; nombre: string };
  status?: { id: string; nombre: string };
  tower?: { id: string; nombre: string };
  condominium?: { id: string; nombre: string };
}

// ── Response envelopes ──────────────────────────────────────────────────

/** GET /condominiums/{id}/properties — listado con cursor-based pagination */
export interface PropertyListResponse {
  data: PropertyListItem[];
  meta: {
    next_cursor: string | null;
  };
}

/** POST /condominiums/{id}/properties */
export interface PropertyCreateResponse {
  property: PropertyDetail;
}

/** PATCH /properties/{id} */
export interface PropertyUpdateResponse {
  property: PropertyDetail;
}

// ── Request DTOs ─────────────────────────────────────────────────────────

export interface CreatePropertyRequest {
  codigo: string;
  tower_id?: string | null;
  property_type_id: string;
  property_status_id: string;
  piso?: number | null;
  area_m2?: number | null;
}

export interface UpdatePropertyRequest {
  codigo?: string;
  tower_id?: string | null;
  property_type_id?: string;
  property_status_id?: string;
  piso?: number | null;
  area_m2?: number | null;
}

// ── Filtros ──────────────────────────────────────────────────────────────

export interface PropertyFilters {
  tower_id?: string;
  type_id?: string;
  status_id?: string;
  search?: string;
}

// ── Zod schema ───────────────────────────────────────────────────────────

export const unidadFormSchema = z.object({
  codigo: z
    .string()
    .min(1, "El código es obligatorio.")
    .max(255, "El código no puede exceder 255 caracteres."),
  tower_id: z.string().optional().or(z.literal("")),
  property_type_id: z
    .string()
    .min(1, "El tipo de propiedad es obligatorio."),
  property_status_id: z
    .string()
    .min(1, "El estado es obligatorio."),
  piso: z.coerce
    .number()
    .int("El piso debe ser un número entero.")
    .optional()
    .nullable(),
  area_m2: z.coerce
    .number()
    .min(0, "El área no puede ser negativa.")
    .optional()
    .nullable(),
});

export type UnidadFormValues = z.infer<typeof unidadFormSchema>;

// ── Error codes ──────────────────────────────────────────────────────────

export const PROPERTY_ERROR_CODES = {
  PROPERTY_CODE_DUPLICATE: "PROPERTY_CODE_DUPLICATE",
  TOWER_CONDOMINIUM_MISMATCH: "TOWER_CONDOMINIUM_MISMATCH",
  PROPERTY_HAS_OCCUPANTS: "PROPERTY_HAS_OCCUPANTS",
  PROPERTY_NOT_FOUND: "PROPERTY_NOT_FOUND",
  CONDOMINIUM_NOT_FOUND: "CONDOMINIUM_NOT_FOUND",
  FORBIDDEN: "FORBIDDEN",
} as const;

export type PropertyErrorCode =
  (typeof PROPERTY_ERROR_CODES)[keyof typeof PROPERTY_ERROR_CODES];

// ──────────────────────────────────────────────────────────────────────────
// Coeficientes y Tree — LOCK-PROPIEDADES-04
// ──────────────────────────────────────────────────────────────────────────

// ── Tipos de coeficiente ─────────────────────────────────────────────────

/** Set cerrado de tipos de coeficiente (R-06-bis) */
export const COEFFICIENT_TYPES = [
  "copropiedad",
  "parqueadero",
  "deposito",
  "mantenimiento",
] as const;

export type CoefficientType = (typeof COEFFICIENT_TYPES)[number];

/** Etiqueta legible para cada tipo de coeficiente */
export const COEFFICIENT_TYPE_LABELS: Record<CoefficientType, string> = {
  copropiedad: "Copropiedad",
  parqueadero: "Parqueadero",
  deposito: "Depósito",
  mantenimiento: "Mantenimiento",
};

// ── Entidad de coeficiente ───────────────────────────────────────────────

/** Item individual de coeficiente — response de GET /properties/{id}/coefficients */
export interface CoefficientItem {
  id: string;
  property_id: string;
  tipo: CoefficientType;
  valor: number;
  vigente_desde: string;   // "2026-07-08"
  vigente_hasta: string | null; // null = vigente actualmente
  created_by: string | null;
  updated_by: string | null;
  created_at: string;
  updated_at: string;
}

/** Determina si un coeficiente es el vigente actual */
export function isVigente(c: CoefficientItem): boolean {
  return c.vigente_hasta === null;
}

// ── Response envelopes ──────────────────────────────────────────────────

/** GET /properties/{id}/coefficients */
export interface CoefficientListResponse {
  data: CoefficientItem[];
}

/** PATCH /condominiums/{id}/coefficients — request body */
export interface UpdateCoefficientsRequest {
  items: Array<{
    property_id: string;
    tipo: CoefficientType;
    valor: number;
  }>;
}

/** PATCH /condominiums/{id}/coefficients — response */
export interface UpdateCoefficientsResponse {
  data: CoefficientItem[];
  warnings?: Array<{
    code: "COEFFICIENT_SUM_MISMATCH";
    detail: {
      condominium_id: string;
      sum: number;
    };
  }>;
}

// ── Tree ─────────────────────────────────────────────────────────────────

/** Torre en el tree del condominio */
export interface TreeTower {
  id: string;
  nombre: string;
  properties_count: number;
}

/** Response de GET /condominiums/{id}/tree */
export interface CondominioTreeResponse {
  tree: {
    id: string;
    nombre: string;
    organization_id: string;
    towers: TreeTower[];
    untowered_properties_count: number;
  };
}

// ── Ítem compuesto para la tabla de coeficientes ────────────────────────

/** Ítem compuesto que une unidad + coeficiente vigente (o placeholder) para la tabla */
export interface CoefficientRow {
  /** ID de la unidad (property_id) */
  property_id: string;
  /** Código de la unidad (ej. "A-101") */
  codigo: string;
  /** ID de la torre (null si sin torre) */
  tower_id: string | null;
  /** Nombre de la torre */
  tower_nombre: string | null;
  /** Tipo de coeficiente */
  tipo: CoefficientType;
  /** Valor del coeficiente vigente (null si no tiene) */
  valor: string;        // string para el input — editable
  /** Valor original (para diff) */
  originalValor: number | null;
  /** Coeficiente vigente actual (para vigencia) */
  vigente?: CoefficientItem | null;
  /** Históricos de este tipo para esta unidad */
  historicos: CoefficientItem[];
  /** Si el valor fue modificado por el usuario */
  modified: boolean;
}

// ── Error codes ──────────────────────────────────────────────────────────

export const COEFFICIENT_ERROR_CODES = {
  COEFFICIENT_OUT_OF_RANGE: "COEFFICIENT_OUT_OF_RANGE",
  COEFFICIENT_INVALID_TYPE: "COEFFICIENT_INVALID_TYPE",
  PROPERTY_NOT_IN_CONDOMINIUM: "PROPERTY_NOT_IN_CONDOMINIUM",
  PROPERTY_NOT_FOUND: "PROPERTY_NOT_FOUND",
  CONDOMINIUM_NOT_FOUND: "CONDOMINIUM_NOT_FOUND",
  COEFFICIENT_SUM_MISMATCH: "COEFFICIENT_SUM_MISMATCH",
} as const;

export type CoefficientErrorCode =
  (typeof COEFFICIENT_ERROR_CODES)[keyof typeof COEFFICIENT_ERROR_CODES];
