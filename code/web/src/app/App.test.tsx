import { describe, it, expect, beforeEach, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import { App } from "./App";
import { useAuthStore } from "@/stores/auth-store";
import { apiClient } from "@/services/api-client";

type AuthState = { accessToken: string | null; setAccessToken: (t: string) => void; clearAccessToken: () => void };

describe("App", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    // RequireAuth guard checks accessToken — simulate an authenticated session
    useAuthStore.setState({ accessToken: "test-token" } as Partial<AuthState>);
    // Mock GET /auth/me — el test no tiene API real
    vi.spyOn(apiClient, "get").mockResolvedValue({
      user: {
        id: "00000000-0000-0000-0000-000000000001",
        email: "test@urbania.test",
        name: "Test User",
        role: "admin" as const,
        permissions: ["admin.access"],
      },
    });
  });

  it("renders the dashboard shell with user greeting and panel heading at root path", async () => {
    render(<App />);
    // Esperar que la query de /auth/me se resuelva
    await waitFor(() => {
      expect(
        screen.getByRole("heading", { name: "Panel" }),
      ).toBeInTheDocument();
    });
    // Verificar que el saludo muestra el nombre del usuario de la API
    expect(screen.getByText("Buenos días, Test User")).toBeInTheDocument();
    // Verificar texto del subtítulo
    expect(
      screen.getByText("Resumen de tu actividad y accesos rápidos"),
    ).toBeInTheDocument();
  });
});
