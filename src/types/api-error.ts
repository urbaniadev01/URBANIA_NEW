/**
 * Formato de error único de la API (ver api/API_CONTRACT.md §2).
 * Se usa en todas las respuestas de error del cliente HTTP.
 */
export interface ApiError {
  /** Código estable en SCREAMING_SNAKE_CASE — usar para decisiones de UI, no message */
  code: string;
  /** Texto para humano — puede cambiar de redacción, no usar para lógica */
  message: string;
  /** UUID v7 de la request — para correlación con logs del servidor */
  trace_id: string;
}

/** Errores de red / parsing que no son una ApiError del servidor */
export interface NetworkError {
  code: "NETWORK_ERROR" | "PARSE_ERROR";
  message: string;
  trace_id: "";
}
