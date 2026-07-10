/**
 * Bootstrap — punto único de importación de side-effect modules del dashboard.
 *
 * Cada feature que quiera aportar widgets al dashboard agrega UNA línea aquí.
 * El archivo se importa una sola vez desde el entry point de la app (main.tsx o App.tsx).
 *
 * Garantía zero-touch (PANORAMA §7.4):
 * Agregar un feature nuevo al dashboard requiere exactamente UNA línea de import aquí.
 * Ningún archivo del core del dashboard se modifica.
 *
 * Orden: primero el registry (para que esté disponible cuando los features registren),
 * luego los features en orden alfabético.
 */

// ── Core del dashboard (siempre primero) ──────────────────────────────────
import "@/features/dashboard/registry";

// ── Features (una línea por feature — se agregan en B02, B03, ...) ─────────
import "@/features/dashboard/widgets"; // B03 — widgets core + placeholders
import "@/features/propiedades/dashboard";   // B02 — widgets de PROPIEDADES
// import "@/features/directorio/dashboard";    // Cuando DIRECTORIO esté SHIPPED
// import "@/features/cobranza/dashboard";      // Cuando COBRANZA esté SHIPPED
