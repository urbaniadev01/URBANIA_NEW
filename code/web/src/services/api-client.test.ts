import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { tryRefresh } from "./api-client";
import { useAuthStore } from "@/stores/auth-store";

/**
 * Regresión: llamadores concurrentes de tryRefresh() (ej. RequireAuth
 * remontado dos veces por React StrictMode) disparaban un POST /auth/refresh
 * por cada llamador, rotando el mismo refresh_token cookie en paralelo y
 * causando un jti duplicado en el backend (RUNBOOK.md#E-007). Encontrado en
 * verificación visual real de DIRECTORIO-B05/B06/B07 (Playwright MCP).
 */
describe("tryRefresh", () => {
  beforeEach(() => {
    useAuthStore.getState().clearAccessToken();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it("deduplicates concurrent calls into a single network request", async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ access_token: "new-token" }),
    });
    vi.stubGlobal("fetch", fetchMock);

    const [a, b, c] = await Promise.all([tryRefresh(), tryRefresh(), tryRefresh()]);

    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(a).toBe(true);
    expect(b).toBe(true);
    expect(c).toBe(true);
    expect(useAuthStore.getState().accessToken).toBe("new-token");
  });

  it("allows a new request once the previous refresh has settled", async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ access_token: "new-token" }),
    });
    vi.stubGlobal("fetch", fetchMock);

    await tryRefresh();
    await tryRefresh();

    expect(fetchMock).toHaveBeenCalledTimes(2);
  });
});
