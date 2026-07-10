import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import { ResetPasswordPage } from "@/features/auth/pages/ResetPasswordPage";
import type { ResetPasswordRequest } from "@/features/auth/types/auth.types";

// Mock del hook de reset-password para que no haga fetch real
const mockMutate = vi.fn();
let mockFatalError: string | null = null;
let mockIsError = false;
let mockError: { code: string; message: string } | null = null;

vi.mock("@/features/auth/api/reset-password", () => ({
  useResetPasswordMutation: () => ({
    mutate: mockMutate,
    isPending: false,
    fatalError: mockFatalError,
    error: mockError,
    isError: mockIsError,
    isSuccess: false,
    data: undefined,
    reset: vi.fn(),
  }),
}));

function renderResetPasswordPage(
  token: string | null = "abc123validtoken456def789ghi012jkl345mnop678qrst90uv",
  email: string | null = "usuario@urbania.com",
) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  const params = new URLSearchParams();
  if (token) params.set("token", token);
  if (email) params.set("email", email);
  const search = params.toString();
  const path = `/reset-password${search ? `?${search}` : ""}`;

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[path]}>
        <Routes>
          <Route path="/reset-password" element={<ResetPasswordPage />} />
          <Route
            path="/login"
            element={<div data-testid="login-page">Login</div>}
          />
          <Route
            path="/forgot-password"
            element={
              <div data-testid="forgot-password-page">Forgot Password</div>
            }
          />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe("ResetPasswordPage", () => {
  beforeEach(() => {
    mockMutate.mockClear();
    mockFatalError = null;
    mockIsError = false;
    mockError = null;
  });

  // ── CA8: Params ausentes ──────────────────────────────────────────────

  describe("CA8 — faltan token o email en URL", () => {
    it("muestra error cuando falta token y email", () => {
      renderResetPasswordPage(null, null);

      expect(screen.getByText("Enlace inválido")).toBeInTheDocument();
      expect(
        screen.getByText(
          "Enlace inválido o incompleto. Solicita un nuevo enlace de recuperación.",
        ),
      ).toBeInTheDocument();
      expect(
        screen.getByRole("link", { name: /ir a recuperación/i }),
      ).toBeInTheDocument();
      // No debe renderizar formulario
      expect(
        screen.queryByLabelText("Nueva contraseña"),
      ).not.toBeInTheDocument();
    });

    it("muestra error cuando falta solo el token", () => {
      renderResetPasswordPage(null, "test@test.com");

      expect(
        screen.getByText(/enlace inválido o incompleto/i),
      ).toBeInTheDocument();
      expect(
        screen.queryByLabelText("Nueva contraseña"),
      ).not.toBeInTheDocument();
    });

    it("muestra error cuando falta solo el email", () => {
      renderResetPasswordPage("some-token", null);

      expect(
        screen.getByText(/enlace inválido o incompleto/i),
      ).toBeInTheDocument();
      expect(
        screen.queryByLabelText("Nueva contraseña"),
      ).not.toBeInTheDocument();
    });

    it("el enlace de recuperación navega a /forgot-password", async () => {
      const user = userEvent.setup();
      renderResetPasswordPage(null, null);

      const link = screen.getByRole("link", { name: /ir a recuperación/i });
      await user.click(link);

      expect(
        screen.getByTestId("forgot-password-page"),
      ).toBeInTheDocument();
    });
  });

  // ── CA7: Campos vacíos ────────────────────────────────────────────────

  describe("CA7 — validación de campos vacíos", () => {
    it("muestra errores de validación al enviar con campos vacíos", async () => {
      const user = userEvent.setup();
      renderResetPasswordPage();

      const submitButton = screen.getByRole("button", {
        name: /actualizar contraseña/i,
      });
      await user.click(submitButton);

      expect(
        screen.getByText("La contraseña debe tener al menos 8 caracteres."),
      ).toBeInTheDocument();
      expect(
        screen.getByText("La confirmación es obligatoria."),
      ).toBeInTheDocument();
      expect(mockMutate).not.toHaveBeenCalled();
    });
  });

  // ── CA5: Política de contraseña ───────────────────────────────────────

  describe("CA5 — política de contraseña no cumplida", () => {
    it("bloquea submit cuando falta mayúscula", async () => {
      const user = userEvent.setup();
      renderResetPasswordPage();

      const passwordInput = screen.getByLabelText("Nueva contraseña");
      const confirmInput = screen.getByLabelText("Confirmar contraseña");
      const submitButton = screen.getByRole("button", {
        name: /actualizar contraseña/i,
      });

      await user.type(passwordInput, "password1");
      await user.type(confirmInput, "password1");
      await user.click(submitButton);

      expect(
        screen.getByText(
          "La contraseña debe contener al menos una mayúscula.",
        ),
      ).toBeInTheDocument();
      expect(mockMutate).not.toHaveBeenCalled();
    });

    it("bloquea submit cuando falta minúscula", async () => {
      const user = userEvent.setup();
      renderResetPasswordPage();

      const passwordInput = screen.getByLabelText("Nueva contraseña");
      const confirmInput = screen.getByLabelText("Confirmar contraseña");
      const submitButton = screen.getByRole("button", {
        name: /actualizar contraseña/i,
      });

      await user.type(passwordInput, "PASSWORD1");
      await user.type(confirmInput, "PASSWORD1");
      await user.click(submitButton);

      expect(
        screen.getByText(
          "La contraseña debe contener al menos una minúscula.",
        ),
      ).toBeInTheDocument();
      expect(mockMutate).not.toHaveBeenCalled();
    });

    it("bloquea submit cuando falta número", async () => {
      const user = userEvent.setup();
      renderResetPasswordPage();

      const passwordInput = screen.getByLabelText("Nueva contraseña");
      const confirmInput = screen.getByLabelText("Confirmar contraseña");
      const submitButton = screen.getByRole("button", {
        name: /actualizar contraseña/i,
      });

      await user.type(passwordInput, "Password!");
      await user.type(confirmInput, "Password!");
      await user.click(submitButton);

      expect(
        screen.getByText("La contraseña debe contener al menos un número."),
      ).toBeInTheDocument();
      expect(mockMutate).not.toHaveBeenCalled();
    });
  });

  // ── CA6: Contraseñas no coinciden ─────────────────────────────────────

  describe("CA6 — contraseñas no coinciden", () => {
    it("bloquea submit cuando confirmación distinta", async () => {
      const user = userEvent.setup();
      renderResetPasswordPage();

      const passwordInput = screen.getByLabelText("Nueva contraseña");
      const confirmInput = screen.getByLabelText("Confirmar contraseña");
      const submitButton = screen.getByRole("button", {
        name: /actualizar contraseña/i,
      });

      await user.type(passwordInput, "Password1");
      await user.type(confirmInput, "Password2");
      await user.click(submitButton);

      expect(
        screen.getByText("Las contraseñas no coinciden."),
      ).toBeInTheDocument();
      expect(mockMutate).not.toHaveBeenCalled();
    });
  });

  // ── CA4: Checklist en tiempo real ─────────────────────────────────────

  describe("CA4 — checklist en tiempo real", () => {
    it("muestra todos los requisitos como no cumplidos al inicio", () => {
      renderResetPasswordPage();

      expect(screen.getByText("Al menos 8 caracteres")).toBeInTheDocument();
      expect(screen.getByText("Al menos una mayúscula")).toBeInTheDocument();
      expect(screen.getByText("Al menos una minúscula")).toBeInTheDocument();
      expect(screen.getByText("Al menos un número")).toBeInTheDocument();

      // Todos deben mostrar X (no cumplen) — verificamos que existan los íconos
      const xIcons = screen.getAllByLabelText("No cumple");
      expect(xIcons).toHaveLength(4);
    });

    it("actualiza checklist a medida que se escribe contraseña válida", async () => {
      const user = userEvent.setup();
      renderResetPasswordPage();

      const passwordInput = screen.getByLabelText("Nueva contraseña");

      // Escribir contraseña que cumple los 4 requisitos
      await user.type(passwordInput, "Password1");

      // Todos deben mostrar check
      const checkIcons = screen.getAllByLabelText("Cumple");
      expect(checkIcons).toHaveLength(4);
    });

    it("muestra mezcla de checks y cruces con contraseña parcial", async () => {
      const user = userEvent.setup();
      renderResetPasswordPage();

      const passwordInput = screen.getByLabelText("Nueva contraseña");

      // Solo minúsculas (8+ chars, minúsculas sí, mayúsculas no, número no)
      await user.type(passwordInput, "password");

      // Debe haber 2 X (mayúscula y número) y 2 checks (8 chars y minúscula)
      const xIcons = screen.getAllByLabelText("No cumple");
      const checkIcons = screen.getAllByLabelText("Cumple");
      expect(xIcons).toHaveLength(2);
      expect(checkIcons).toHaveLength(2);
    });
  });

  // ── CA1: Camino feliz ─────────────────────────────────────────────────

  describe("CA1 — submit exitoso con contraseña válida", () => {
    it("envía el DTO correcto con token, password y password_confirmation", async () => {
      const user = userEvent.setup();
      renderResetPasswordPage();

      const passwordInput = screen.getByLabelText("Nueva contraseña");
      const confirmInput = screen.getByLabelText("Confirmar contraseña");
      const submitButton = screen.getByRole("button", {
        name: /actualizar contraseña/i,
      });

      await user.type(passwordInput, "Password1");
      await user.type(confirmInput, "Password1");
      await user.click(submitButton);

      expect(mockMutate).toHaveBeenCalledTimes(1);

      const expectedDto: ResetPasswordRequest = {
        token: "abc123validtoken456def789ghi012jkl345mnop678qrst90uv",
        password: "Password1",
        password_confirmation: "Password1",
      };
      expect(mockMutate).toHaveBeenCalledWith(expectedDto);
    });

    it("muestra el email en el contexto de la página", () => {
      renderResetPasswordPage(
        "valid-token",
        "usuario@urbania.com",
      );

      expect(
        screen.getByText("Restableciendo contraseña para usuario@urbania.com"),
      ).toBeInTheDocument();
    });
  });

  // ── CA2/CA3: Token inválido o expirado (fatalError) ───────────────────

  describe("CA2/CA3 — token inválido o expirado", () => {
    it("CA2: muestra mensaje para token inválido y enlace /forgot-password con formulario visible", () => {
      mockFatalError = "Este enlace ya no es válido. Solicita uno nuevo.";
      renderResetPasswordPage();

      expect(screen.getByText("Enlace inválido")).toBeInTheDocument();
      expect(
        screen.getByText("Este enlace ya no es válido. Solicita uno nuevo."),
      ).toBeInTheDocument();
      expect(
        screen.getByRole("link", { name: /solicitar un nuevo enlace/i }),
      ).toBeInTheDocument();
      // El formulario permanece visible para reintentar (CA2)
      expect(
        screen.getByLabelText("Nueva contraseña"),
      ).toBeInTheDocument();
      expect(
        screen.getByLabelText("Confirmar contraseña"),
      ).toBeInTheDocument();
      expect(
        screen.getByRole("button", { name: /actualizar contraseña/i }),
      ).toBeInTheDocument();
    });

    it("CA3: muestra mensaje para token expirado con formulario visible", () => {
      mockFatalError =
        "Este enlace expiró (válido por 60 minutos). Solicita uno nuevo.";
      renderResetPasswordPage();

      expect(screen.getByText("Enlace inválido")).toBeInTheDocument();
      expect(
        screen.getByText(
          "Este enlace expiró (válido por 60 minutos). Solicita uno nuevo.",
        ),
      ).toBeInTheDocument();
      expect(
        screen.getByRole("link", { name: /solicitar un nuevo enlace/i }),
      ).toBeInTheDocument();
      // El formulario permanece visible para reintentar
      expect(
        screen.getByLabelText("Nueva contraseña"),
      ).toBeInTheDocument();
      expect(
        screen.getByLabelText("Confirmar contraseña"),
      ).toBeInTheDocument();
    });

    it("el enlace 'Solicitar un nuevo enlace' navega a /forgot-password", async () => {
      const user = userEvent.setup();
      mockFatalError = "Este enlace ya no es válido. Solicita uno nuevo.";
      renderResetPasswordPage();

      const link = screen.getByRole("link", {
        name: /solicitar un nuevo enlace/i,
      });
      await user.click(link);

      expect(
        screen.getByTestId("forgot-password-page"),
      ).toBeInTheDocument();
    });
  });

  // ── CA9/CA10: Errores no fatales ────────────────────────────────────

  describe("CA9 — TOO_MANY_REQUESTS (429)", () => {
    it("llama a la mutación y mantiene el formulario visible", async () => {
      const user = userEvent.setup();
      mockIsError = true;
      mockError = {
        code: "TOO_MANY_REQUESTS",
        message: "Demasiados intentos. Espera 15 minutos e inténtalo de nuevo.",
      };
      renderResetPasswordPage();

      const passwordInput = screen.getByLabelText("Nueva contraseña");
      const confirmInput = screen.getByLabelText("Confirmar contraseña");
      const submitButton = screen.getByRole("button", {
        name: /actualizar contraseña/i,
      });

      await user.type(passwordInput, "Password1");
      await user.type(confirmInput, "Password1");
      await user.click(submitButton);

      expect(mockMutate).toHaveBeenCalledTimes(1);
      // El formulario sigue visible (no fue reemplazado)
      expect(
        screen.getByLabelText("Nueva contraseña"),
      ).toBeInTheDocument();
      expect(
        screen.getByLabelText("Confirmar contraseña"),
      ).toBeInTheDocument();
    });
  });

  describe("CA10 — VALIDATION_ERROR (422)", () => {
    it("llama a la mutación y mantiene el formulario visible", async () => {
      const user = userEvent.setup();
      mockIsError = true;
      mockError = {
        code: "VALIDATION_ERROR",
        message: "Error del servidor",
      };
      renderResetPasswordPage();

      const passwordInput = screen.getByLabelText("Nueva contraseña");
      const confirmInput = screen.getByLabelText("Confirmar contraseña");
      const submitButton = screen.getByRole("button", {
        name: /actualizar contraseña/i,
      });

      await user.type(passwordInput, "Password1");
      await user.type(confirmInput, "Password1");
      await user.click(submitButton);

      expect(mockMutate).toHaveBeenCalledTimes(1);
      // El formulario sigue visible (no fue reemplazado)
      expect(
        screen.getByLabelText("Nueva contraseña"),
      ).toBeInTheDocument();
      expect(
        screen.getByLabelText("Confirmar contraseña"),
      ).toBeInTheDocument();
    });
  });

  // ── Renderizado ───────────────────────────────────────────────────────

  describe("renderizado del formulario", () => {
    it("renderiza campos de contraseña y confirmación", () => {
      renderResetPasswordPage();

      expect(
        screen.getByLabelText("Nueva contraseña"),
      ).toBeInTheDocument();
      expect(
        screen.getByLabelText("Confirmar contraseña"),
      ).toBeInTheDocument();
      expect(
        screen.getByRole("button", { name: /actualizar contraseña/i }),
      ).toBeInTheDocument();
    });

    it("muestra enlace 'Volver a inicio de sesión' con href /login", () => {
      renderResetPasswordPage();

      const link = screen.getByRole("link", {
        name: /volver a inicio de sesión/i,
      });
      expect(link).toBeInTheDocument();
      expect(link).toHaveAttribute("href", "/login");
    });

    it("navega a /login al hacer clic en 'Volver a inicio de sesión'", async () => {
      const user = userEvent.setup();
      renderResetPasswordPage();

      const link = screen.getByRole("link", {
        name: /volver a inicio de sesión/i,
      });
      await user.click(link);

      expect(screen.getByTestId("login-page")).toBeInTheDocument();
    });
  });
});
