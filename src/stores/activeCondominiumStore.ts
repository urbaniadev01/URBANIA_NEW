import { create } from "zustand";

/**
 * Store de condominio activo — coordinación cross-widget en el dashboard.
 *
 * - Quién escribe: Widget "Mis Condominios" (PROPIEDADES, B02) al hacer clic.
 * - Quiénes leen: Widgets "Unidades Recientes" y "Estructura" (PROPIEDADES, B02).
 * - Reseteo: a null en logout o cambio de organización.
 *
 * Validación server-side: todo endpoint valida que el ID pertenece al scope del
 * usuario. El store es una sugerencia de UI, no una fuente de autorización
 * (mitigación IDOR — ver PANORAMA §9.1).
 *
 * No se persiste (consistente con WEB_ARCHITECTURE.md §7).
 */
interface ActiveCondominiumState {
  activeCondominiumId: string | null;
  setActiveCondominium: (id: string) => void;
  clearActiveCondominium: () => void;
}

export const useActiveCondominiumStore = create<ActiveCondominiumState>()(
  (set) => ({
    activeCondominiumId: null,
    setActiveCondominium: (id: string) => set({ activeCondominiumId: id }),
    clearActiveCondominium: () => set({ activeCondominiumId: null }),
  }),
);
