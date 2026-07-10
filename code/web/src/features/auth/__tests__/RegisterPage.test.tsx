import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import { RegisterPage } from "@/features/auth/pages/RegisterPage";
import type { RegisterRequestDto } from "@/features/auth/types/auth.types";

// Mock del hook de registro para que no haga fetch real
const mockMutate = vi.fn();

vi.mock("@/features/auth/api/register", () => ({
  useRegisterMutation: () => ({
    mutate: mockMutate,
    isPending: false,
    error: null,
    isError: false,
    isSuccess: false,
    data: undefined,
    reset: vi.fn(),
  }),
}));

function renderRegisterPage(token: string) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[`/register/${token}`]}>
        <Routes>
          <Route path="/register/:token?" element={<RegisterPage />} />
          <Route path="/login" element={<div data-testid="login-page">Login</div>} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe("RegisterPage — validación de formulario", () => {
  const validToken = "abc123-valid-token";

  beforeEach(() => {
    mockMutate.mockClear();
  });

  it("renderiza el formulario con campos de nombre, contraseña, confirmación y teléfono", () => {
    renderRegisterPage(validToken);

    expect(screen.getByLabelText("Nombre completo")).toBeInTheDocument();
    expect(screen.getByLabelText(/^Contraseña$/)).toBeInTheDocument();
    expect(screen.getByLabelText("Confirmar contraseña")).toBeInTheDocument();
    expect(screen.getByLabelText(/Teléfono/)).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: /crear cuenta/i }),
    ).toBeInTheDocument();
  });

  it("muestra pantalla de enlace inválido cuando no hay token", () => {
    const queryClient = new QueryClient({
      defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
    });

    render(
      <QueryClientProvider client={queryClient}>
        <MemoryRouter initialEntries={["/register"]}>
          <Routes>
            <Route path="/register/:token?" element={<RegisterPage />} />
          </Routes>
        </MemoryRouter>
      </QueryClientProvider>,
    );

    expect(screen.getByText("Enlace inválido")).toBeInTheDocument();
  });

  describe("CA4 — validación de contraseñas", () => {
    it("muestra errores de validación al enviar con campos vacíos", async () => {
      const user = userEvent.setup();
      renderRegisterPage(validToken);

      const submitButton = screen.getByRole("button", { name: /crear cuenta/i });
      await user.click(submitButton);

      expect(
        screen.getByText("El nombre debe tener al menos 2 caracteres."),
      ).toBeInTheDocument();
      expect(
        screen.getByText("La contraseña debe tener al menos 8 caracteres."),
      ).toBeInTheDocument();
      expect(
        screen.getByText("Confirma tu contraseña."),
      ).toBeInTheDocument();

      // La mutación NUNCA debe haberse llamado con campos vacíos
      expect(mockMutate).not.toHaveBeenCalled();
    });

    it("bloquea el submit cuando las contraseñas no coinciden", async () => {
      const user = userEvent.setup();
      renderRegisterPage(validToken);

      const nameInput = screen.getByLabelText("Nombre completo");
      const passwordInput = screen.getByLabelText(/^Contraseña$/);
      const confirmInput = screen.getByLabelText("Confirmar contraseña");
      const submitButton = screen.getByRole("button", { name: /crear cuenta/i });

      await user.type(nameInput, "Juan Pérez");
      await user.type(passwordInput, "Password1");
      await user.type(confirmInput, "Password2");
      await user.click(submitButton);

      expect(
        screen.getByText("Las contraseñas no coinciden."),
      ).toBeInTheDocument();
      expect(mockMutate).not.toHaveBeenCalled();
    });

    it("valida complejidad de contraseña: mínimo 8 caracteres, 1 mayúscula, 1 minúscula, 1 número", async () => {
      const user = userEvent.setup();
      renderRegisterPage(validToken);

      const nameInput = screen.getByLabelText("Nombre completo");
      const passwordInput = screen.getByLabelText(/^Contraseña$/);
      const submitButton = screen.getByRole("button", { name: /crear cuenta/i });

      await user.type(nameInput, "Juan Pérez");
      // Contraseña sin mayúscula
      await user.type(passwordInput, "password1");
      await user.click(submitButton);

      expect(
        screen.getByText(
          "La contraseña debe contener al menos una mayúscula.",
        ),
      ).toBeInTheDocument();
      expect(mockMutate).not.toHaveBeenCalled();
    });
  });

  it("el campo teléfono es opcional y puede dejarse vacío", async () => {
    const user = userEvent.setup();
    renderRegisterPage(validToken);

    const nameInput = screen.getByLabelText("Nombre completo");
    const passwordInput = screen.getByLabelText(/^Contraseña$/);
    const confirmInput = screen.getByLabelText("Confirmar contraseña");
    const submitButton = screen.getByRole("button", { name: /crear cuenta/i });

    await user.type(nameInput, "Juan Pérez");
    await user.type(passwordInput, "Password1");
    await user.type(confirmInput, "Password1");
    await user.click(submitButton);

    expect(mockMutate).toHaveBeenCalledTimes(1);
    const expectedDto: RegisterRequestDto = {
      invitation_token: validToken,
      name: "Juan Pérez",
      password: "Password1",
      phone: undefined,
    };
    expect(mockMutate).toHaveBeenCalledWith(expectedDto);
  });

  it("incluye el teléfono en el request cuando se ingresa", async () => {
    const user = userEvent.setup();
    renderRegisterPage(validToken);

    const nameInput = screen.getByLabelText("Nombre completo");
    const passwordInput = screen.getByLabelText(/^Contraseña$/);
    const confirmInput = screen.getByLabelText("Confirmar contraseña");
    const phoneInput = screen.getByLabelText(/Teléfono/);
    const submitButton = screen.getByRole("button", { name: /crear cuenta/i });

    await user.type(nameInput, "María López");
    await user.type(passwordInput, "Secure99");
    await user.type(confirmInput, "Secure99");
    await user.type(phoneInput, "+51999888777");
    await user.click(submitButton);

    expect(mockMutate).toHaveBeenCalledTimes(1);
    const expectedDto: RegisterRequestDto = {
      invitation_token: validToken,
      name: "María López",
      password: "Secure99",
      phone: "+51999888777",
    };
    expect(mockMutate).toHaveBeenCalledWith(expectedDto);
  });
});
