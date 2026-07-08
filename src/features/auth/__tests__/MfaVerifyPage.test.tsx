import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { MemoryRouter } from "react-router-dom";
import { MfaVerifyPage } from "@/features/auth/pages/MfaVerifyPage";
import type { MfaVerifyRequest } from "@/features/auth/types/auth.types";
import { MFA_VERIFY_ERROR_CODES } from "@/features/auth/types/auth.types";

const mockMutate = vi.fn();
const mockToastError = vi.fn();

vi.mock("@/features/auth/api/mfa-verify", () => ({
  useMfaVerifyMutation: () => ({
    mutate: mockMutate,
    isPending: false,
    error: null,
    isError: false,
    isSuccess: false,
    data: undefined,
    reset: vi.fn(),
  }),
}));

vi.mock("sonner", () => ({
  toast: {
    error: (...args: unknown[]) => mockToastError(...args),
  },
}));

function renderMfaVerifyPage() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <MfaVerifyPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe("MfaVerifyPage — validación de formulario (CA7, CA8)", () => {
  beforeEach(() => {
    mockMutate.mockClear();
    mockToastError.mockClear();
  });

  it("CA7: muestra error al enviar con campo vacío", async () => {
    const user = userEvent.setup();
    renderMfaVerifyPage();

    const submitButton = screen.getByRole("button", { name: /verificar/i });
    await user.click(submitButton);

    expect(screen.getByText("El código es obligatorio.")).toBeInTheDocument();
    expect(mockMutate).not.toHaveBeenCalled();
  });

  it("CA8: muestra error con formato inválido — solo 4 dígitos", async () => {
    const user = userEvent.setup();
    renderMfaVerifyPage();

    const input = screen.getByPlaceholderText("000000 o XXXXX-XXXXX");
    const submitButton = screen.getByRole("button", { name: /verificar/i });

    await user.type(input, "1234");
    await user.click(submitButton);

    expect(
      screen.getByText(
        "Ingresa un código TOTP de 6 dígitos o un código de respaldo (formato XXXXX-XXXXX).",
      ),
    ).toBeInTheDocument();
    expect(mockMutate).not.toHaveBeenCalled();
  });

  it("CA8: muestra error con formato inválido — 7 dígitos", async () => {
    const user = userEvent.setup();
    renderMfaVerifyPage();

    const input = screen.getByPlaceholderText("000000 o XXXXX-XXXXX");
    const submitButton = screen.getByRole("button", { name: /verificar/i });

    await user.type(input, "1234567");
    await user.click(submitButton);

    expect(
      screen.getByText(
        "Ingresa un código TOTP de 6 dígitos o un código de respaldo (formato XXXXX-XXXXX).",
      ),
    ).toBeInTheDocument();
    expect(mockMutate).not.toHaveBeenCalled();
  });

  it("CA8: muestra error con texto no numérico sin guión", async () => {
    const user = userEvent.setup();
    renderMfaVerifyPage();

    const input = screen.getByPlaceholderText("000000 o XXXXX-XXXXX");
    const submitButton = screen.getByRole("button", { name: /verificar/i });

    await user.type(input, "abcdef");
    await user.click(submitButton);

    expect(
      screen.getByText(
        "Ingresa un código TOTP de 6 dígitos o un código de respaldo (formato XXXXX-XXXXX).",
      ),
    ).toBeInTheDocument();
    expect(mockMutate).not.toHaveBeenCalled();
  });
});

describe("MfaVerifyPage — envío de formulario válido (CA1, CA2)", () => {
  beforeEach(() => {
    mockMutate.mockClear();
    mockToastError.mockClear();
  });

  it("CA1: envía código TOTP de 6 dígitos a la mutación", async () => {
    const user = userEvent.setup();
    renderMfaVerifyPage();

    const input = screen.getByPlaceholderText("000000 o XXXXX-XXXXX");
    const submitButton = screen.getByRole("button", { name: /verificar/i });

    await user.type(input, "123456");
    await user.click(submitButton);

    expect(mockMutate).toHaveBeenCalledTimes(1);
    const expectedDto: MfaVerifyRequest = { code: "123456" };
    expect(mockMutate).toHaveBeenCalledWith(expectedDto, expect.any(Object));
  });

  it("CA2: envía recovery code (formato XXXXX-XXXXX) a la mutación", async () => {
    const user = userEvent.setup();
    renderMfaVerifyPage();

    const input = screen.getByPlaceholderText("000000 o XXXXX-XXXXX");
    const submitButton = screen.getByRole("button", { name: /verificar/i });

    await user.type(input, "ABC12-DEF34");
    await user.click(submitButton);

    expect(mockMutate).toHaveBeenCalledTimes(1);
    const expectedDto: MfaVerifyRequest = { code: "ABC12-DEF34" };
    expect(mockMutate).toHaveBeenCalledWith(expectedDto, expect.any(Object));
  });

  it("convierte recovery code a mayúsculas antes de enviar", async () => {
    const user = userEvent.setup();
    renderMfaVerifyPage();

    const input = screen.getByPlaceholderText("000000 o XXXXX-XXXXX");
    const submitButton = screen.getByRole("button", { name: /verificar/i });

    await user.type(input, "abc12-def34");
    await user.click(submitButton);

    expect(mockMutate).toHaveBeenCalledTimes(1);
    const expectedDto: MfaVerifyRequest = { code: "ABC12-DEF34" };
    expect(mockMutate).toHaveBeenCalledWith(expectedDto, expect.any(Object));
  });
});

describe("MfaVerifyPage — manejo de errores de API (CA3, CA4, CA5, CA6)", () => {
  beforeEach(() => {
    mockMutate.mockClear();
    mockToastError.mockClear();
  });

  function simulateApiError(errorCode: string) {
    mockMutate.mockImplementation(
      (
        _dto: MfaVerifyRequest,
        options?: { onError?: (err: { code: string }) => void },
      ) => {
        if (options?.onError) {
          options.onError({ code: errorCode });
        }
      },
    );
  }

  it("CA3: MFA_TOKEN_INVALID muestra pantalla de sesión expirada con enlace a /login", async () => {
    simulateApiError(MFA_VERIFY_ERROR_CODES.MFA_TOKEN_INVALID);
    const user = userEvent.setup();
    renderMfaVerifyPage();

    const input = screen.getByPlaceholderText("000000 o XXXXX-XXXXX");
    const submitButton = screen.getByRole("button", { name: /verificar/i });

    await user.type(input, "123456");
    await user.click(submitButton);

    await waitFor(() => {
      expect(
        screen.getByText(
          "Tu sesión de verificación expiró. Vuelve a iniciar sesión.",
        ),
      ).toBeInTheDocument();
    });

    const loginLink = screen.getByRole("link", {
      name: /ir al inicio de sesión/i,
    });
    expect(loginLink).toBeInTheDocument();
    expect(loginLink).toHaveAttribute("href", "/login");
  });

  it("CA4: MFA_CODE_INVALID limpia el campo para reintentar", async () => {
    simulateApiError(MFA_VERIFY_ERROR_CODES.MFA_CODE_INVALID);
    const user = userEvent.setup();
    renderMfaVerifyPage();

    const input = screen.getByPlaceholderText("000000 o XXXXX-XXXXX");
    const submitButton = screen.getByRole("button", { name: /verificar/i });

    await user.type(input, "123456");
    await user.click(submitButton);

    await waitFor(() => {
      expect(input).toHaveValue("");
    });
  });

  it("CA5: MFA_RECOVERY_CODE_USED limpia el campo", async () => {
    simulateApiError(MFA_VERIFY_ERROR_CODES.MFA_RECOVERY_CODE_USED);
    const user = userEvent.setup();
    renderMfaVerifyPage();

    const input = screen.getByPlaceholderText("000000 o XXXXX-XXXXX");
    const submitButton = screen.getByRole("button", { name: /verificar/i });

    await user.type(input, "ABC12-DEF34");
    await user.click(submitButton);

    await waitFor(() => {
      expect(input).toHaveValue("");
    });
  });

  it("CA6: TOO_MANY_REQUESTS deshabilita el botón", async () => {
    simulateApiError(MFA_VERIFY_ERROR_CODES.TOO_MANY_REQUESTS);
    const user = userEvent.setup();
    renderMfaVerifyPage();

    const input = screen.getByPlaceholderText("000000 o XXXXX-XXXXX");
    const submitButton = screen.getByRole("button", { name: /verificar/i });

    await user.type(input, "123456");
    await user.click(submitButton);

    await waitFor(() => {
      expect(submitButton).toBeDisabled();
    });
  });
});
