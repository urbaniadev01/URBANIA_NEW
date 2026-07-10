import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import { ForgotPasswordPage } from "@/features/auth/pages/ForgotPasswordPage";
import type { ForgotPasswordRequest } from "@/features/auth/types/auth.types";

// Mock del hook de forgot-password para que no haga fetch real
const mockMutate = vi.fn();

vi.mock("@/features/auth/api/forgot-password", () => ({
  useForgotPasswordMutation: () => ({
    mutate: mockMutate,
    isPending: false,
    error: null,
    isError: false,
    isSuccess: false,
    data: undefined,
    reset: vi.fn(),
  }),
}));

function renderForgotPasswordPage() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={["/forgot-password"]}>
        <Routes>
          <Route path="/forgot-password" element={<ForgotPasswordPage />} />
          <Route
            path="/login"
            element={<div data-testid="login-page">Login</div>}
          />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe("ForgotPasswordPage — validación de formulario", () => {
  beforeEach(() => {
    mockMutate.mockClear();
  });

  it("muestra error de validación al enviar con campo vacío (CA4)", async () => {
    const user = userEvent.setup();
    renderForgotPasswordPage();

    const submitButton = screen.getByRole("button", {
      name: /enviar enlace/i,
    });
    await user.click(submitButton);

    expect(screen.getByText("El email es obligatorio.")).toBeInTheDocument();
    expect(mockMutate).not.toHaveBeenCalled();
  });

  it("muestra error de formato al ingresar email inválido (CA3)", async () => {
    const user = userEvent.setup();
    renderForgotPasswordPage();

    const emailInput = screen.getByPlaceholderText("tu@email.com");
    const submitButton = screen.getByRole("button", {
      name: /enviar enlace/i,
    });

    await user.type(emailInput, "no-es-un-email");
    await user.click(submitButton);

    expect(screen.getByText("Ingresa un email válido.")).toBeInTheDocument();
    expect(mockMutate).not.toHaveBeenCalled();
  });

  it("llama a la mutación con el DTO correcto al enviar email válido", async () => {
    const user = userEvent.setup();
    renderForgotPasswordPage();

    const emailInput = screen.getByPlaceholderText("tu@email.com");
    const submitButton = screen.getByRole("button", {
      name: /enviar enlace/i,
    });

    await user.type(emailInput, "test@urbania.com");
    await user.click(submitButton);

    expect(mockMutate).toHaveBeenCalledTimes(1);
    const expectedDto: ForgotPasswordRequest = {
      email: "test@urbania.com",
    };
    expect(mockMutate).toHaveBeenCalledWith(
      expectedDto,
      expect.objectContaining({ onSuccess: expect.any(Function) }),
    );
  });

  it("muestra el enlace 'Volver a inicio de sesión' con href /login (CA6)", () => {
    renderForgotPasswordPage();

    const link = screen.getByRole("link", {
      name: /volver a inicio de sesión/i,
    });
    expect(link).toBeInTheDocument();
    expect(link).toHaveAttribute("href", "/login");
  });

  it("navega a /login al hacer clic en 'Volver a inicio de sesión'", async () => {
    const user = userEvent.setup();
    renderForgotPasswordPage();

    const link = screen.getByRole("link", {
      name: /volver a inicio de sesión/i,
    });
    await user.click(link);

    expect(screen.getByTestId("login-page")).toBeInTheDocument();
  });
});
