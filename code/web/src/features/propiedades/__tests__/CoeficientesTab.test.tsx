import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { CoeficientesTab } from "@/features/propiedades/components/CoeficientesTab";
import type {
  CoefficientItem,
  PropertyListItem,
  UpdateCoefficientsResponse,
} from "@/features/propiedades/types";
import type { ApiError } from "@/types/api-error";

// ── Fixtures ────────────────────────────────────────────────────────────────
// Todo lo que se referencia dentro de un factory de vi.mock debe vivir en
// vi.hoisted, porque vi.mock se iza por encima de las const del módulo.

const CONDOMINIO_ID = "condo-1";

const {
  PROP_1,
  PROP_2,
  COEFFICIENTS_MAP,
  mockFetchNextPage,
  mockPatch,
} = vi.hoisted(() => {
  type CoefficientItemHoisted = {
    id: string;
    property_id: string;
    tipo: "copropiedad" | "parqueadero" | "deposito" | "mantenimiento";
    valor: number;
    vigente_desde: string;
    vigente_hasta: string | null;
    created_by: string | null;
    updated_by: string | null;
    created_at: string;
    updated_at: string;
  };

  const CONDOMINIO_ID_H = "condo-1";

  const PROP_1_H = {
    id: "prop-1",
    condominium_id: CONDOMINIO_ID_H,
    tower_id: null,
    property_type_id: "type-1",
    property_status_id: "status-1",
    codigo: "A-101",
    piso: 1,
    created_by: null,
    updated_by: null,
    created_at: "2026-01-01T00:00:00Z",
    updated_at: "2026-01-01T00:00:00Z",
  };

  const PROP_2_H = {
    id: "prop-2",
    condominium_id: CONDOMINIO_ID_H,
    tower_id: null,
    property_type_id: "type-1",
    property_status_id: "status-1",
    codigo: "A-102",
    piso: 1,
    created_by: null,
    updated_by: null,
    created_at: "2026-01-01T00:00:00Z",
    updated_at: "2026-01-01T00:00:00Z",
  };

  function coefH(overrides: Partial<CoefficientItemHoisted>): CoefficientItemHoisted {
    return {
      id: `coef-${Math.random()}`,
      property_id: "prop-1",
      tipo: "copropiedad",
      valor: 0.5,
      vigente_desde: "2026-01-01",
      vigente_hasta: null,
      created_by: null,
      updated_by: null,
      created_at: "2026-01-01T00:00:00Z",
      updated_at: "2026-01-01T00:00:00Z",
      ...overrides,
    };
  }

  const COEFFICIENTS_MAP_H = new Map<string, CoefficientItemHoisted[]>([
    [
      "prop-1",
      [
        coefH({ id: "c1", property_id: "prop-1", tipo: "copropiedad", valor: 0.5 }),
        coefH({ id: "c2", property_id: "prop-1", tipo: "parqueadero", valor: 1 }),
      ],
    ],
    [
      "prop-2",
      [coefH({ id: "c3", property_id: "prop-2", tipo: "copropiedad", valor: 0.5 })],
    ],
  ]);

  return {
    PROP_1: PROP_1_H,
    PROP_2: PROP_2_H,
    COEFFICIENTS_MAP: COEFFICIENTS_MAP_H,
    mockFetchNextPage: vi.fn(),
    mockPatch: vi.fn(),
  };
});

function coef(overrides: Partial<CoefficientItem>): CoefficientItem {
  return {
    id: `coef-${Math.random()}`,
    property_id: "prop-1",
    tipo: "copropiedad",
    valor: 0.5,
    vigente_desde: "2026-01-01",
    vigente_hasta: null,
    created_by: null,
    updated_by: null,
    created_at: "2026-01-01T00:00:00Z",
    updated_at: "2026-01-01T00:00:00Z",
    ...overrides,
  };
}

// ── Mocks ───────────────────────────────────────────────────────────────────

vi.mock("@/features/propiedades/api/properties", () => ({
  usePropertiesInfiniteQuery: () => ({
    data: { pages: [{ data: [PROP_1, PROP_2], meta: { next_cursor: null } }] },
    isLoading: false,
    isError: false,
    fetchNextPage: mockFetchNextPage,
    hasNextPage: false,
    isFetchingNextPage: false,
  }),
  flattenProperties: (
    pages: Array<{ data: PropertyListItem[] }> | undefined,
  ) => (pages ? pages.flatMap((p) => p.data) : []),
}));

vi.mock("@/features/propiedades/api/coefficients", async (importOriginal) => {
  const actual =
    await importOriginal<typeof import("@/features/propiedades/api/coefficients")>();
  return {
    ...actual,
    useBatchPropertyCoefficientsQueries: () => ({
      coefficientsMap: COEFFICIENTS_MAP,
      isLoading: false,
      isError: false,
      errors: [],
    }),
  };
});

vi.mock("@/services/api-client", () => ({
  apiClient: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: mockPatch,
    delete: vi.fn(),
    unauthenticated: { post: vi.fn(), get: vi.fn() },
  },
}));

vi.mock("sonner", () => ({
  toast: {
    success: vi.fn(),
    error: vi.fn(),
    warning: vi.fn(),
  },
}));

function renderTab() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <CoeficientesTab condominioId={CONDOMINIO_ID} />
    </QueryClientProvider>,
  );
}

function getSaveButton() {
  return screen.getByRole("button", { name: /guardar cambios/i });
}

describe("CoeficientesTab (PROPIEDADES-B09)", () => {
  beforeEach(() => {
    mockFetchNextPage.mockClear();
    mockPatch.mockReset();
  });

  it("render inicial: botón 'Guardar cambios' deshabilitado sin cambios", () => {
    renderTab();

    expect(getSaveButton()).toBeDisabled();
    expect(
      screen.getByLabelText("Coeficiente A-101 - Copropiedad"),
    ).toHaveValue("0.5");
    expect(
      screen.getByLabelText("Coeficiente A-102 - Copropiedad"),
    ).toHaveValue("0.5");
  });

  it("editar un valor actualiza la barra de suma en tiempo real y habilita el botón guardar", async () => {
    const user = userEvent.setup();
    renderTab();

    // Suma inicial: 0.5 + 0.5 = 100.0%
    expect(screen.getByText(/suma: 100\.0%/i)).toBeInTheDocument();

    const input = screen.getByLabelText("Coeficiente A-101 - Copropiedad");
    await user.clear(input);
    await user.type(input, "0.3");

    // Nueva suma: 0.3 + 0.5 = 80.0%
    expect(screen.getByText(/80\.0%/)).toBeInTheDocument();
    expect(getSaveButton()).toBeEnabled();
  });

  it("muestra indicador ámbar cuando la suma de copropiedad ≠ 1.0", async () => {
    const user = userEvent.setup();
    renderTab();

    const input = screen.getByLabelText("Coeficiente A-101 - Copropiedad");
    await user.clear(input);
    await user.type(input, "0.3");

    expect(screen.getByText(/se requiere 100%/i)).toBeInTheDocument();
    expect(screen.getByText(/faltante/i)).toBeInTheDocument();
  });

  it("muestra indicador verde cuando la suma de copropiedad = 1.0", () => {
    renderTab();

    expect(screen.getByText(/suma: 100\.0% ✓/i)).toBeInTheDocument();
    expect(screen.getByText(/balanceado/i)).toBeInTheDocument();
  });

  it("guardar cambios dispara el PATCH masivo con solo las filas modificadas y muestra toast de éxito", async () => {
    const user = userEvent.setup();
    const response: UpdateCoefficientsResponse = {
      data: [
        coef({ id: "c1", property_id: "prop-1", tipo: "copropiedad", valor: 0.6 }),
      ],
    };
    mockPatch.mockResolvedValueOnce(response);

    renderTab();

    const input = screen.getByLabelText("Coeficiente A-101 - Copropiedad");
    await user.clear(input);
    await user.type(input, "0.6");

    await user.click(getSaveButton());

    await waitFor(() => {
      expect(mockPatch).toHaveBeenCalledTimes(1);
    });
    expect(mockPatch).toHaveBeenCalledWith(
      `/api/v1/condominiums/${CONDOMINIO_ID}/coefficients`,
      {
        items: [{ property_id: "prop-1", tipo: "copropiedad", valor: 0.6 }],
      },
    );

    const { toast } = await import("sonner");
    await waitFor(() => {
      expect(toast.success).toHaveBeenCalledWith(
        "1 coeficiente actualizado.",
      );
    });
  });

  it("un error 422 del API no pierde los cambios en la UI", async () => {
    const user = userEvent.setup();
    const apiError: ApiError = {
      code: "HTTP_422",
      message: "Datos inválidos. Revisa los valores ingresados.",
      trace_id: "trace-422",
    };
    mockPatch.mockRejectedValueOnce(apiError);

    renderTab();

    const input = screen.getByLabelText("Coeficiente A-101 - Copropiedad");
    await user.clear(input);
    await user.type(input, "0.6");

    await user.click(getSaveButton());

    const { toast } = await import("sonner");
    await waitFor(() => {
      expect(toast.error).toHaveBeenCalledWith(apiError.message);
    });

    // El valor editado sigue presente en el input — no se perdió el cambio.
    expect(
      screen.getByLabelText("Coeficiente A-101 - Copropiedad"),
    ).toHaveValue("0.6");
    expect(getSaveButton()).toBeEnabled();
  });

  it("toggle 'Ver historial' muestra columnas vigente_desde/vigente_hasta", async () => {
    const user = userEvent.setup();
    renderTab();

    expect(
      screen.queryByRole("columnheader", { name: /vig\. desde/i }),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole("columnheader", { name: /vig\. hasta/i }),
    ).not.toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: /ver historial/i }));

    expect(
      screen.getByRole("columnheader", { name: /vig\. desde/i }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("columnheader", { name: /vig\. hasta/i }),
    ).toBeInTheDocument();
  });

  it("el selector de tipo filtra la tabla", async () => {
    const user = userEvent.setup();
    renderTab();

    expect(
      screen.getByLabelText("Coeficiente A-101 - Copropiedad"),
    ).toBeInTheDocument();

    await user.selectOptions(screen.getByLabelText(/tipo:/i), "parqueadero");

    expect(
      screen.queryByLabelText("Coeficiente A-101 - Copropiedad"),
    ).not.toBeInTheDocument();
    expect(
      screen.getByLabelText("Coeficiente A-101 - Parqueadero"),
    ).toHaveValue("1");
    expect(
      screen.getByLabelText("Coeficiente A-102 - Parqueadero"),
    ).toHaveValue("");
  });
});
