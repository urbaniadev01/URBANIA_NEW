import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter } from "react-router-dom";
import { LoginPage } from "@/features/auth/pages/LoginPage";
import type { LoginRequestDto } from "@/features/auth/types/auth.types";

// Mock del hook de login para que no haga fetch real
const mockMutate = vi.fn();

vi.mock("@/features/auth/api/login", () => ({
  useLoginMutation: () => ({
    mutate: mockMutate,
    isPending: false,
    error: null,
    isError: false,
    isSuccess: false,
    data: undefined,
    reset: vi.fn(),
  }),
}));

function renderLoginPage() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <LoginPage />
      </BrowserRouter>
    </QueryClientProvider>,
  );
}

describe("LoginPage — validación de formulario (CA5)", () => {
  beforeEach(() => {
    mockMutate.mockClear();
  });

  it("muestra errores de validación al enviar con campos vacíos", async () => {
    const user = userEvent.setup();
    renderLoginPage();

    const submitButton = screen.getByRole("button", { name: /iniciar sesión/i });
    await user.click(submitButton);

    // Deben aparecer los mensajes de validación
    expect(screen.getByText("El email es obligatorio.")).toBeInTheDocument();
    expect(
      screen.getByText("La contraseña es obligatoria."),
    ).toBeInTheDocument();

    // La mutación NUNCA debe haberse llamado con campos vacíos
    expect(mockMutate).not.toHaveBeenCalled();
  });

  it("muestra error de formato al ingresar email inválido", async () => {
    const user = userEvent.setup();
    renderLoginPage();

    const emailInput = screen.getByPlaceholderText("tu@email.com");
    const passwordInput = screen.getByPlaceholderText("••••••••");
    const submitButton = screen.getByRole("button", { name: /iniciar sesión/i });

    await user.type(emailInput, "no-es-un-email");
    await user.type(passwordInput, "password123");
    await user.click(submitButton);

    expect(screen.getByText("Ingresa un email válido.")).toBeInTheDocument();
    expect(mockMutate).not.toHaveBeenCalled();
  });

  it("llama a la mutación con datos válidos", async () => {
    const user = userEvent.setup();
    renderLoginPage();

    const emailInput = screen.getByPlaceholderText("tu@email.com");
    const passwordInput = screen.getByPlaceholderText("••••••••");
    const submitButton = screen.getByRole("button", { name: /iniciar sesión/i });

    await user.type(emailInput, "test@urbania.com");
    await user.type(passwordInput, "password123");
    await user.click(submitButton);

    expect(mockMutate).toHaveBeenCalledTimes(1);
    const expectedDto: LoginRequestDto = {
      email: "test@urbania.com",
      password: "password123",
    };
    expect(mockMutate).toHaveBeenCalledWith(expectedDto);
  });
});
