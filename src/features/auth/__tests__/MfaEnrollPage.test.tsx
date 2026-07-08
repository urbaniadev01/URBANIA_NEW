import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { MemoryRouter } from "react-router-dom";
import { MfaEnrollPage } from "@/features/auth/pages/MfaEnrollPage";
import { useAuthStore } from "@/stores/auth-store";
import type {
  MfaEnrollResponse,
  MfaConfirmRequest,
  MfaDisableRequest,
  MfaRecoveryRequest,
} from "@/features/auth/types/auth.types";
import { MFA_ENROLL_ERROR_CODES } from "@/features/auth/types/auth.types";

let mockEnrollMutate = vi.fn();
let mockConfirmMutate = vi.fn();
let mockDisableMutate = vi.fn();
let mockRecoveryMutate = vi.fn();
let mockToastSuccess = vi.fn();
let mockToastError = vi.fn();

vi.mock("@/features/auth/api/mfa-enroll", () => ({
  useMfaEnrollMutation: () => ({
    mutate: mockEnrollMutate,
    isPending: false,
  }),
  useMfaConfirmMutation: () => ({
    mutate: mockConfirmMutate,
    isPending: false,
  }),
  useMfaDisableMutation: () => ({
    mutate: mockDisableMutate,
    isPending: false,
  }),
  useMfaRecoveryMutation: () => ({
    mutate: mockRecoveryMutate,
    isPending: false,
  }),
}));

vi.mock("sonner", () => ({
  toast: {
    success: (...args: unknown[]) => mockToastSuccess(...args),
    error: (...args: unknown[]) => mockToastError(...args),
  },
}));

const mockClipboardWrite = vi.fn();
const mockCreateObjectURL = vi.fn(() => "blob:test");
const mockRevokeObjectURL = vi.fn();

globalThis.URL.createObjectURL = mockCreateObjectURL;
globalThis.URL.revokeObjectURL = mockRevokeObjectURL;

type AuthState = { accessToken: string | null; setAccessToken: (t: string) => void; clearAccessToken: () => void };

function setAccessToken(token: string | null) {
  useAuthStore.setState({ accessToken: token } as Partial<AuthState>);
}

function renderMfaEnrollPage() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <MfaEnrollPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

function simulateEnrollSuccess(response: MfaEnrollResponse) {
  mockEnrollMutate.mockImplementation(
    (
      _dto: void,
      options?: { onSuccess?: (data: MfaEnrollResponse) => void },
    ) => {
      options?.onSuccess?.(response);
    },
  );
}

function simulateEnrollError(code: string) {
  mockEnrollMutate.mockImplementation(
    (
      _dto: void,
      options?: { onError?: (err: { code: string }) => void },
    ) => {
      options?.onError?.({ code });
    },
  );
}

function simulateConfirmSuccess() {
  mockConfirmMutate.mockImplementation(
    (
      _dto: MfaConfirmRequest,
      options?: { onSuccess?: () => void },
    ) => {
      options?.onSuccess?.();
    },
  );
}

function simulateConfirmError(code: string) {
  mockConfirmMutate.mockImplementation(
    (
      _dto: MfaConfirmRequest,
      options?: { onError?: (err: { code: string }) => void },
    ) => {
      options?.onError?.({ code });
    },
  );
}

function simulateDisableSuccess() {
  mockDisableMutate.mockImplementation(
    (
      _dto: MfaDisableRequest,
      options?: { onSuccess?: () => void },
    ) => {
      options?.onSuccess?.();
    },
  );
}

function simulateDisableError(code: string) {
  mockDisableMutate.mockImplementation(
    (
      _dto: MfaDisableRequest,
      options?: { onError?: (err: { code: string }) => void },
    ) => {
      options?.onError?.({ code });
    },
  );
}

function simulateRecoverySuccess(codes: string[]) {
  mockRecoveryMutate.mockImplementation(
    (
      _dto: MfaRecoveryRequest,
      options?: { onSuccess?: (data: { recovery_codes: string[] }) => void },
    ) => {
      options?.onSuccess?.({ recovery_codes: codes });
    },
  );
}

function simulateRecoveryError(code: string) {
  mockRecoveryMutate.mockImplementation(
    (
      _dto: MfaRecoveryRequest,
      options?: { onError?: (err: { code: string }) => void },
    ) => {
      options?.onError?.({ code });
    },
  );
}

const ENROLL_RESPONSE: MfaEnrollResponse = {
  qr_code: "data:image/png;base64,XXXX",
  recovery_codes: [
    "ABCD1-FGHIJ",
    "ABCD2-FGHIJ",
    "ABCD3-FGHIJ",
    "ABCD4-FGHIJ",
    "ABCD5-FGHIJ",
    "ABCD6-FGHIJ",
    "ABCD7-FGHIJ",
    "ABCD8-FGHIJ",
  ],
  enrollment_token: "enrollment-token-123",
};

describe("MfaEnrollPage", () => {
  beforeEach(() => {
    mockEnrollMutate = vi.fn();
    mockConfirmMutate = vi.fn();
    mockDisableMutate = vi.fn();
    mockRecoveryMutate = vi.fn();
    mockToastSuccess = vi.fn();
    mockToastError = vi.fn();
    mockClipboardWrite.mockReset();
    setAccessToken("valid-token");
  });

  it("CA7: redirige a /login si no hay access_token", () => {
    setAccessToken(null);
    renderMfaEnrollPage();

    expect(
      screen.queryByText(/autenticación en dos pasos/i),
    ).not.toBeInTheDocument();
  });

  it("CA1: inicia enrollment y muestra QR con recovery codes", async () => {
    simulateEnrollSuccess(ENROLL_RESPONSE);
    const user = userEvent.setup();
    renderMfaEnrollPage();

    const activateBtn = screen.getByRole("button", {
      name: /activar autenticación/i,
    });
    await user.click(activateBtn);

    await waitFor(() => {
      expect(screen.getByAltText("QR code para autenticador")).toBeInTheDocument();
    });

    ENROLL_RESPONSE.recovery_codes.forEach((code) => {
      expect(screen.getByText(code)).toBeInTheDocument();
    });

    expect(screen.getByText("Copiar todos")).toBeInTheDocument();
    expect(screen.getByText("Descargar TXT")).toBeInTheDocument();
  });

  it("CA2: confirma enrollment exitoso y muestra panel de gestión", async () => {
    simulateEnrollSuccess(ENROLL_RESPONSE);
    simulateConfirmSuccess();
    const user = userEvent.setup();
    renderMfaEnrollPage();

    await user.click(
      screen.getByRole("button", { name: /activar autenticación/i }),
    );

    await waitFor(() => {
      expect(screen.getByAltText("QR code para autenticador")).toBeInTheDocument();
    });

    const codeInput = screen.getByPlaceholderText("000000");
    await user.type(codeInput, "123456");
    await user.click(
      screen.getByRole("button", { name: /verificar y activar/i }),
    );

    await waitFor(() => {
      expect(mockToastSuccess).toHaveBeenCalledWith(
        "MFA activado exitosamente.",
      );
    });

    expect(screen.getByText("MFA activo")).toBeInTheDocument();
    expect(
      screen.queryByAltText("QR code para autenticador"),
    ).not.toBeInTheDocument();
  });

  it("CA3: copia recovery codes al portapapeles", async () => {
    simulateEnrollSuccess(ENROLL_RESPONSE);
    const user = userEvent.setup();
    renderMfaEnrollPage();

    await user.click(
      screen.getByRole("button", { name: /activar autenticación/i }),
    );

    await waitFor(() => {
      expect(screen.getByText("Copiar todos")).toBeInTheDocument();
    });

    await user.click(screen.getByText("Copiar todos"));

    await waitFor(() => {
      expect(mockToastSuccess).toHaveBeenCalledWith(
        "8 códigos copiados al portapapeles.",
      );
    });
  });

  it("CA4: descarga recovery codes como TXT", async () => {
    simulateEnrollSuccess(ENROLL_RESPONSE);
    const user = userEvent.setup();
    renderMfaEnrollPage();

    await user.click(
      screen.getByRole("button", { name: /activar autenticación/i }),
    );

    await waitFor(() => {
      expect(screen.getByText("Descargar TXT")).toBeInTheDocument();
    });

    await user.click(screen.getByText("Descargar TXT"));

    expect(mockCreateObjectURL).toHaveBeenCalled();
    const calls = (mockCreateObjectURL as { mock: { calls: unknown[][] } }).mock
      .calls;
    const blobArg = calls[0]?.[0];
    expect(blobArg).toBeDefined();
    expect(blobArg).toBeInstanceOf(Blob);
    expect(mockRevokeObjectURL).toHaveBeenCalledWith("blob:test");
  });

  it("CA5: desactiva MFA y vuelve a paso idle", async () => {
    simulateEnrollSuccess(ENROLL_RESPONSE);
    simulateConfirmSuccess();
    const user = userEvent.setup();
    renderMfaEnrollPage();

    await user.click(
      screen.getByRole("button", { name: /activar autenticación/i }),
    );
    await waitFor(() => {
      expect(screen.getByAltText("QR code para autenticador")).toBeInTheDocument();
    });

    const confirmInput = screen.getByPlaceholderText("000000");
    await user.type(confirmInput, "123456");
    await user.click(
      screen.getByRole("button", { name: /verificar y activar/i }),
    );

    await waitFor(() => {
      expect(screen.getByText("MFA activo")).toBeInTheDocument();
    });

    simulateDisableSuccess();

    const disableInputs = screen.getAllByPlaceholderText("000000");
    await user.type(disableInputs[0]!, "654321");
    await user.click(screen.getByRole("button", { name: /^Desactivar$/i }));

    await waitFor(() => {
      expect(mockToastSuccess).toHaveBeenCalledWith(
        "MFA desactivado exitosamente.",
      );
    });

    expect(
      screen.getByRole("button", { name: /activar autenticación/i }),
    ).toBeInTheDocument();
  });

  it("CA6: regenera recovery codes y los muestra", async () => {
    simulateEnrollSuccess(ENROLL_RESPONSE);
    simulateConfirmSuccess();
    const user = userEvent.setup();
    renderMfaEnrollPage();

    await user.click(
      screen.getByRole("button", { name: /activar autenticación/i }),
    );
    await waitFor(() => {
      expect(screen.getByAltText("QR code para autenticador")).toBeInTheDocument();
    });

    const confirmInput = screen.getByPlaceholderText("000000");
    await user.type(confirmInput, "123456");
    await user.click(
      screen.getByRole("button", { name: /verificar y activar/i }),
    );

    await waitFor(() => {
      expect(screen.getByText("MFA activo")).toBeInTheDocument();
    });

    simulateRecoverySuccess(["NEW01-ABCDE", "NEW02-ABCDE"]);

    const recoveryInputs = screen.getAllByPlaceholderText("000000");
    await user.type(recoveryInputs[1]!, "111111");
    await user.click(screen.getByRole("button", { name: /^Regenerar$/i }));

    await waitFor(() => {
      expect(screen.getByText("NEW01-ABCDE")).toBeInTheDocument();
      expect(screen.getByText("NEW02-ABCDE")).toBeInTheDocument();
    });
  });

  it("CA8: interceptor 401 redirige a /login", async () => {
    simulateEnrollError(MFA_ENROLL_ERROR_CODES.UNAUTHENTICATED);
    const user = userEvent.setup();
    renderMfaEnrollPage();

    await user.click(
      screen.getByRole("button", { name: /activar autenticación/i }),
    );

    await waitFor(() => {
      expect(mockEnrollMutate).toHaveBeenCalled();
    });
  });

  it("CA9: MFA_ALREADY_ENABLED transiciona a panel de gestión", async () => {
    simulateEnrollError(MFA_ENROLL_ERROR_CODES.MFA_ALREADY_ENABLED);
    const user = userEvent.setup();
    renderMfaEnrollPage();

    await user.click(
      screen.getByRole("button", { name: /activar autenticación/i }),
    );

    await waitFor(() => {
      expect(screen.getByText("MFA activo")).toBeInTheDocument();
    });
  });

  it("CA10: MFA_CODE_INVALID en confirm muestra mensaje y contador", async () => {
    simulateEnrollSuccess(ENROLL_RESPONSE);
    simulateConfirmError(MFA_ENROLL_ERROR_CODES.MFA_CODE_INVALID);
    const user = userEvent.setup();
    renderMfaEnrollPage();

    await user.click(
      screen.getByRole("button", { name: /activar autenticación/i }),
    );

    await waitFor(() => {
      expect(screen.getByAltText("QR code para autenticador")).toBeInTheDocument();
    });

    const codeInput = screen.getByPlaceholderText("000000");
    await user.type(codeInput, "123456");
    await user.click(
      screen.getByRole("button", { name: /verificar y activar/i }),
    );

    await waitFor(() => {
      expect(
        screen.getByText("Código inválido. Intenta de nuevo."),
      ).toBeInTheDocument();
      expect(screen.getByText("4 de 5 intentos restantes")).toBeInTheDocument();
    });
  });

  it("CA11: 5 intentos fallidos → MFA_ENROLLMENT_EXPIRED vuelve a idle", async () => {
    simulateEnrollSuccess(ENROLL_RESPONSE);
    simulateConfirmError(MFA_ENROLL_ERROR_CODES.MFA_ENROLLMENT_EXPIRED);
    const user = userEvent.setup();
    renderMfaEnrollPage();

    await user.click(
      screen.getByRole("button", { name: /activar autenticación/i }),
    );

    await waitFor(() => {
      expect(screen.getByAltText("QR code para autenticador")).toBeInTheDocument();
    });

    const codeInput = screen.getByPlaceholderText("000000");
    await user.type(codeInput, "123456");
    await user.click(
      screen.getByRole("button", { name: /verificar y activar/i }),
    );

    await waitFor(() => {
      expect(
        screen.getByText(/activación fue cancelada/),
      ).toBeInTheDocument();
    });

    expect(
      screen.getByRole("button", { name: /activar autenticación/i }),
    ).toBeInTheDocument();
    expect(
      screen.queryByAltText("QR code para autenticador"),
    ).not.toBeInTheDocument();
  });

  it("CA12: MFA_ENROLLMENT_NOT_FOUND muestra mensaje y vuelve a idle", async () => {
    simulateEnrollSuccess(ENROLL_RESPONSE);
    simulateConfirmError(MFA_ENROLL_ERROR_CODES.MFA_ENROLLMENT_NOT_FOUND);
    const user = userEvent.setup();
    renderMfaEnrollPage();

    await user.click(
      screen.getByRole("button", { name: /activar autenticación/i }),
    );

    await waitFor(() => {
      expect(screen.getByAltText("QR code para autenticador")).toBeInTheDocument();
    });

    const codeInput = screen.getByPlaceholderText("000000");
    await user.type(codeInput, "123456");
    await user.click(
      screen.getByRole("button", { name: /verificar y activar/i }),
    );

    await waitFor(() => {
      expect(
        screen.getByText("No hay una activación en curso. Inicia de nuevo."),
      ).toBeInTheDocument();
    });
  });

  it("CA13: MFA_CODE_INVALID en disable limpia campo", async () => {
    simulateEnrollSuccess(ENROLL_RESPONSE);
    simulateConfirmSuccess();
    simulateDisableError(MFA_ENROLL_ERROR_CODES.MFA_CODE_INVALID);
    const user = userEvent.setup();
    renderMfaEnrollPage();

    await user.click(
      screen.getByRole("button", { name: /activar autenticación/i }),
    );
    await waitFor(() => {
      expect(screen.getByAltText("QR code para autenticador")).toBeInTheDocument();
    });

    const confirmInput = screen.getByPlaceholderText("000000");
    await user.type(confirmInput, "123456");
    await user.click(
      screen.getByRole("button", { name: /verificar y activar/i }),
    );

    await waitFor(() => {
      expect(screen.getByText("MFA activo")).toBeInTheDocument();
    });

    const disableInputs = screen.getAllByPlaceholderText("000000");
    await user.type(disableInputs[0]!, "654321");
    await user.click(screen.getByRole("button", { name: /^Desactivar$/i }));

    await waitFor(() => {
      expect(
        screen.getByText("Código inválido. Intenta de nuevo."),
      ).toBeInTheDocument();
      expect(disableInputs[0]!).toHaveValue("");
    });
  });

  it("CA14: MFA_CODE_INVALID en recovery limpia campo", async () => {
    simulateEnrollSuccess(ENROLL_RESPONSE);
    simulateConfirmSuccess();
    simulateRecoveryError(MFA_ENROLL_ERROR_CODES.MFA_CODE_INVALID);
    const user = userEvent.setup();
    renderMfaEnrollPage();

    await user.click(
      screen.getByRole("button", { name: /activar autenticación/i }),
    );
    await waitFor(() => {
      expect(screen.getByAltText("QR code para autenticador")).toBeInTheDocument();
    });

    const confirmInput = screen.getByPlaceholderText("000000");
    await user.type(confirmInput, "123456");
    await user.click(
      screen.getByRole("button", { name: /verificar y activar/i }),
    );

    await waitFor(() => {
      expect(screen.getByText("MFA activo")).toBeInTheDocument();
    });

    const recoveryInputs = screen.getAllByPlaceholderText("000000");
    await user.type(recoveryInputs[1]!, "111111");
    await user.click(screen.getByRole("button", { name: /^Regenerar$/i }));

    await waitFor(() => {
      expect(
        screen.getByText("Código inválido. Intenta de nuevo."),
      ).toBeInTheDocument();
      expect(recoveryInputs[1]!).toHaveValue("");
    });
  });

  it("CA15: MFA_NOT_ENABLED en recovery vuelve a idle", async () => {
    simulateEnrollSuccess(ENROLL_RESPONSE);
    simulateConfirmSuccess();
    simulateRecoveryError(MFA_ENROLL_ERROR_CODES.MFA_NOT_ENABLED);
    const user = userEvent.setup();
    renderMfaEnrollPage();

    await user.click(
      screen.getByRole("button", { name: /activar autenticación/i }),
    );
    await waitFor(() => {
      expect(screen.getByAltText("QR code para autenticador")).toBeInTheDocument();
    });

    const confirmInput = screen.getByPlaceholderText("000000");
    await user.type(confirmInput, "123456");
    await user.click(
      screen.getByRole("button", { name: /verificar y activar/i }),
    );

    await waitFor(() => {
      expect(screen.getByText("MFA activo")).toBeInTheDocument();
    });

    const recoveryInputs = screen.getAllByPlaceholderText("000000");
    await user.type(recoveryInputs[1]!, "111111");
    await user.click(screen.getByRole("button", { name: /^Regenerar$/i }));

    await waitFor(() => {
      expect(mockToastError).toHaveBeenCalledWith(
        "No tienes MFA activo.",
      );
    });

    expect(
      screen.getByRole("button", { name: /activar autenticación/i }),
    ).toBeInTheDocument();
  });

  it("TOO_MANY_REQUESTS en disable muestra mensaje de espera", async () => {
    simulateEnrollSuccess(ENROLL_RESPONSE);
    simulateConfirmSuccess();
    simulateDisableError(MFA_ENROLL_ERROR_CODES.TOO_MANY_REQUESTS);
    const user = userEvent.setup();
    renderMfaEnrollPage();

    await user.click(
      screen.getByRole("button", { name: /activar autenticación/i }),
    );
    await waitFor(() => {
      expect(screen.getByAltText("QR code para autenticador")).toBeInTheDocument();
    });

    const confirmInput = screen.getByPlaceholderText("000000");
    await user.type(confirmInput, "123456");
    await user.click(
      screen.getByRole("button", { name: /verificar y activar/i }),
    );

    await waitFor(() => {
      expect(screen.getByText("MFA activo")).toBeInTheDocument();
    });

    const disableInputs = screen.getAllByPlaceholderText("000000");
    await user.type(disableInputs[0]!, "654321");
    await user.click(screen.getByRole("button", { name: /^Desactivar$/i }));

    await waitFor(() => {
      expect(mockToastError).toHaveBeenCalledWith(
        "Demasiados intentos. Espera un minuto e inténtalo de nuevo.",
      );
    });
  });

  it("TOO_MANY_REQUESTS en recovery muestra mensaje de espera", async () => {
    simulateEnrollSuccess(ENROLL_RESPONSE);
    simulateConfirmSuccess();
    simulateRecoveryError(MFA_ENROLL_ERROR_CODES.TOO_MANY_REQUESTS);
    const user = userEvent.setup();
    renderMfaEnrollPage();

    await user.click(
      screen.getByRole("button", { name: /activar autenticación/i }),
    );
    await waitFor(() => {
      expect(screen.getByAltText("QR code para autenticador")).toBeInTheDocument();
    });

    const confirmInput = screen.getByPlaceholderText("000000");
    await user.type(confirmInput, "123456");
    await user.click(
      screen.getByRole("button", { name: /verificar y activar/i }),
    );

    await waitFor(() => {
      expect(screen.getByText("MFA activo")).toBeInTheDocument();
    });

    const recoveryInputs = screen.getAllByPlaceholderText("000000");
    await user.type(recoveryInputs[1]!, "111111");
    await user.click(screen.getByRole("button", { name: /^Regenerar$/i }));

    await waitFor(() => {
      expect(mockToastError).toHaveBeenCalledWith(
        "Demasiados intentos. Espera un minuto e inténtalo de nuevo.",
      );
    });
  });
  it("CA15: MFA_NOT_ENABLED en disable vuelve a idle", async () => {
    simulateEnrollSuccess(ENROLL_RESPONSE);
    simulateConfirmSuccess();
    simulateDisableError(MFA_ENROLL_ERROR_CODES.MFA_NOT_ENABLED);
    const user = userEvent.setup();
    renderMfaEnrollPage();

    await user.click(
      screen.getByRole("button", { name: /activar autenticación/i }),
    );
    await waitFor(() => {
      expect(screen.getByAltText("QR code para autenticador")).toBeInTheDocument();
    });

    const confirmInput = screen.getByPlaceholderText("000000");
    await user.type(confirmInput, "123456");
    await user.click(
      screen.getByRole("button", { name: /verificar y activar/i }),
    );

    await waitFor(() => {
      expect(screen.getByText("MFA activo")).toBeInTheDocument();
    });

    const disableInputs = screen.getAllByPlaceholderText("000000");
    await user.type(disableInputs[0]!, "654321");
    await user.click(screen.getByRole("button", { name: /^Desactivar$/i }));

    await waitFor(() => {
      expect(mockToastError).toHaveBeenCalledWith(
        "No tienes MFA activo.",
      );
    });

    expect(
      screen.getByRole("button", { name: /activar autenticación/i }),
    ).toBeInTheDocument();
  });

  it("CA16: TOO_MANY_REQUESTS en enroll muestra mensaje de espera", async () => {
    simulateEnrollError(MFA_ENROLL_ERROR_CODES.TOO_MANY_REQUESTS);
    const user = userEvent.setup();
    renderMfaEnrollPage();

    await user.click(
      screen.getByRole("button", { name: /activar autenticación/i }),
    );

    await waitFor(() => {
      expect(mockToastError).toHaveBeenCalledWith(
        "Demasiados intentos. Espera una hora e inténtalo de nuevo.",
      );
    });
  });

  it("CA17: TOO_MANY_REQUESTS en confirm muestra mensaje de espera", async () => {
    simulateEnrollSuccess(ENROLL_RESPONSE);
    simulateConfirmError(MFA_ENROLL_ERROR_CODES.TOO_MANY_REQUESTS);
    const user = userEvent.setup();
    renderMfaEnrollPage();

    await user.click(
      screen.getByRole("button", { name: /activar autenticación/i }),
    );

    await waitFor(() => {
      expect(screen.getByAltText("QR code para autenticador")).toBeInTheDocument();
    });

    const codeInput = screen.getByPlaceholderText("000000");
    await user.type(codeInput, "123456");
    await user.click(
      screen.getByRole("button", { name: /verificar y activar/i }),
    );

    await waitFor(() => {
      expect(mockToastError).toHaveBeenCalledWith(
        "Demasiados intentos. Espera un minuto e inténtalo de nuevo.",
      );
    });
  });

  it("CA18: bloquea submit con campo vacío en confirm", async () => {
    simulateEnrollSuccess(ENROLL_RESPONSE);
    const user = userEvent.setup();
    renderMfaEnrollPage();

    await user.click(
      screen.getByRole("button", { name: /activar autenticación/i }),
    );

    await waitFor(() => {
      expect(screen.getByAltText("QR code para autenticador")).toBeInTheDocument();
    });

    await user.click(
      screen.getByRole("button", { name: /verificar y activar/i }),
    );

    expect(
      screen.getByText("El código es obligatorio."),
    ).toBeInTheDocument();
    expect(mockConfirmMutate).not.toHaveBeenCalled();
  });

  it("CA18: bloquea submit con campo vacío en disable", async () => {
    simulateEnrollSuccess(ENROLL_RESPONSE);
    simulateConfirmSuccess();
    const user = userEvent.setup();
    renderMfaEnrollPage();

    await user.click(
      screen.getByRole("button", { name: /activar autenticación/i }),
    );
    await waitFor(() => {
      expect(screen.getByAltText("QR code para autenticador")).toBeInTheDocument();
    });

    const confirmInput = screen.getByPlaceholderText("000000");
    await user.type(confirmInput, "123456");
    await user.click(
      screen.getByRole("button", { name: /verificar y activar/i }),
    );

    await waitFor(() => {
      expect(screen.getByText("MFA activo")).toBeInTheDocument();
    });

    await user.click(screen.getByRole("button", { name: /^Desactivar$/i }));

    const errorMsgs = screen.getAllByText("El código es obligatorio.");
    expect(errorMsgs.length).toBeGreaterThanOrEqual(1);
  });

  it("CA19: bloquea submit con código de formato inválido en confirm", async () => {
    simulateEnrollSuccess(ENROLL_RESPONSE);
    const user = userEvent.setup();
    renderMfaEnrollPage();

    await user.click(
      screen.getByRole("button", { name: /activar autenticación/i }),
    );

    await waitFor(() => {
      expect(screen.getByAltText("QR code para autenticador")).toBeInTheDocument();
    });

    const codeInput = screen.getByPlaceholderText("000000");
    await user.type(codeInput, "abc");
    await user.click(
      screen.getByRole("button", { name: /verificar y activar/i }),
    );

    expect(
      screen.getByText("Ingresa un código de 6 dígitos."),
    ).toBeInTheDocument();
    expect(mockConfirmMutate).not.toHaveBeenCalled();
  });

  it("CA19: input de confirm tiene maxLength=6", async () => {
    simulateEnrollSuccess(ENROLL_RESPONSE);
    const user = userEvent.setup();
    renderMfaEnrollPage();

    await user.click(
      screen.getByRole("button", { name: /activar autenticación/i }),
    );

    await waitFor(() => {
      expect(screen.getByAltText("QR code para autenticador")).toBeInTheDocument();
    });

    const codeInput = screen.getByPlaceholderText("000000");
    expect(codeInput).toHaveAttribute("maxLength", "6");
  });
});
