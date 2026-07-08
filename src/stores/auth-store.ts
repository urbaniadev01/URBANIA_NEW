import { create } from "zustand";

/**
 * Store de autenticación — access_token solo en memoria.
 * Nunca persiste a localStorage ni sessionStorage por diseño de seguridad
 * (ver web/WEB_ARCHITECTURE.md §7 y shared/SYSTEM_CONTRACT §1).
 */
interface AuthState {
  accessToken: string | null;
  setAccessToken: (token: string) => void;
  clearAccessToken: () => void;
}

export const useAuthStore = create<AuthState>()((set) => ({
  accessToken: null,
  setAccessToken: (token: string) => set({ accessToken: token }),
  clearAccessToken: () => set({ accessToken: null }),
}));
