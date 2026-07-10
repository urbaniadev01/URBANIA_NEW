import type { ReactNode } from "react";
import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { MemoryRouter, Routes, Route } from "react-router-dom";
import { DetalleCondominioPage } from "@/features/propiedades/pages/DetalleCondominioPage";
import type { CondominioDetail, TorreItem } from "@/features/propiedades/types";

// ── Mocks de hooks de API — sin fetch real ─────────────────────────────────

const mockCondominioQuery = vi.fn();
const mockUpdateCondominioMutate = vi.fn();
const mockDeleteCondominioMutate = vi.fn();
let mockDeleteCondominioIsPending = false;

const mockCreateTorreMutate = vi.fn();
const mockUpdateTorreMutate = vi.fn();
const mockDeleteTorreMutate = vi.fn();

vi.mock("@/features/propiedades/api/condominiums", () => ({
  useCondominioQuery: () => mockCondominioQuery(),
  useUpdateCondominioMutation: () => ({
    mutate: mockUpdateCondominioMutate,
    isPending: false,
    error: null,
    isError: false,
    isSuccess: false,
    data: undefined,
    reset: vi.fn(),
  }),
  useDeleteCondominioMutation: () => ({
    mutate: mockDeleteCondominioMutate,
    isPending: mockDeleteCondominioIsPending,
    error: null,
    isError: false,
    isSuccess: false,
    data: undefined,
    reset: vi.fn(),
  }),
}));

vi.mock("@/features/propiedades/api/towers", () => ({
  useCreateTorreMutation: () => ({
    mutate: mockCreateTorreMutate,
    isPending: false,
    error: null,
    isError: false,
    isSuccess: false,
    data: undefined,
    reset: vi.fn(),
  }),
  useUpdateTorreMutation: () => ({
    mutate: mockUpdateTorreMutate,
    isPending: false,
    error: null,
    isError: false,
    isSuccess: false,
    data: undefined,
    reset: vi.fn(),
  }),
  useDeleteTorreMutation: () => ({
    mutate: mockDeleteTorreMutate,
    isPending: false,
    error: null,
    isError: false,
    isSuccess: false,
    data: undefined,
    reset: vi.fn(),
  }),
}));

// Los tabs Unidades/Coeficientes son de otros bloques — se stubbean para que
// no interfieran (no consumen los hooks reales de propiedades/coeficientes).
vi.mock("@/features/propiedades/components/UnidadesTab", () => ({
  UnidadesTab: ({ condominioId }: { condominioId: string }): ReactNode => (
    <div>Unidades stub — {condominioId}</div>
  ),
}));

vi.mock("@/features/propiedades/components/CoeficientesTab", () => ({
  CoeficientesTab: ({ condominioId }: { condominioId: string }): ReactNode => (
    <div>Coeficientes stub — {condominioId}</div>
  ),
}));

// ── Helpers ─────────────────────────────────────────────────────────────

function buildTorre(overrides: Partial<TorreItem>): TorreItem {
  return {
    id: "torre-1",
    condominium_id: "cond-1",
    nombre: "Torre A",
    created_by: null,
    updated_by: null,
    created_at: "2026-01-01T00:00:00Z",
    updated_at: "2026-01-01T00:00:00Z",
    ...overrides,
  };
}

function buildCondominioDetail(
  overrides: Partial<CondominioDetail> = {},
): CondominioDetail {
  return {
    id: "cond-1",
    organization_id: "org-1",
    nombre: "Conjunto El Paraíso",
    direccion: "Calle 123 #45-67",
    nit: "900123456-7",
    towers: [],
    created_by: null,
    updated_by: null,
    created_at: "2026-01-01T00:00:00Z",
    updated_at: "2026-01-01T00:00:00Z",
    ...overrides,
  };
}

function renderDetallePage(condominioId = "cond-1") {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[`/condominios/${condominioId}`]}>
        <Routes>
          <Route path="/condominios" element={<div>Lista de condominios</div>} />
          <Route
            path="/condominios/:id"
            element={<DetalleCondominioPage />}
          />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe("DetalleCondominioPage", () => {
  beforeEach(() => {
    mockCondominioQuery.mockReset();
    mockUpdateCondominioMutate.mockReset();
    mockDeleteCondominioMutate.mockReset();
    mockDeleteCondominioIsPending = false;
    mockCreateTorreMutate.mockReset();
    mockUpdateTorreMutate.mockReset();
    mockDeleteTorreMutate.mockReset();
  });

  it("renderiza el breadcrumb y el tab Torres activo por defecto", () => {
    mockCondominioQuery.mockReturnValue({
      data: { condominium: buildCondominioDetail({ towers: [] }) },
      isLoading: false,
      isError: false,
    });

    renderDetallePage();

    // Breadcrumb
    expect(screen.getByRole("link", { name: /condominios/i })).toBeInTheDocument();
    expect(
      screen.getByRole("heading", { name: "Conjunto El Paraíso" }),
    ).toBeInTheDocument();

    // Tab Torres activo por defecto
    const torresTab = screen.getByRole("tab", { name: /torres/i });
    expect(torresTab).toHaveAttribute("aria-selected", "true");
    expect(screen.getByText("No hay torres registradas.")).toBeInTheDocument();
  });

  it("crea una torre exitosamente", async () => {
    const user = userEvent.setup();
    mockCondominioQuery.mockReturnValue({
      data: { condominium: buildCondominioDetail({ towers: [] }) },
      isLoading: false,
      isError: false,
    });
    mockCreateTorreMutate.mockImplementation((_payload, opts) => {
      opts?.onSuccess?.();
    });

    renderDetallePage();

    await user.click(screen.getByRole("button", { name: /nueva torre/i }));
    await user.type(
      screen.getByPlaceholderText('Ej: "Torre A"'),
      "Torre Nueva",
    );
    await user.click(screen.getByRole("button", { name: /^guardar$/i }));

    expect(mockCreateTorreMutate).toHaveBeenCalledTimes(1);
    expect(mockCreateTorreMutate).toHaveBeenCalledWith(
      {
        condominiumId: "cond-1",
        data: { nombre: "Torre Nueva" },
      },
      expect.objectContaining({ onSuccess: expect.any(Function) }),
    );
    expect(
      screen.queryByRole("heading", { name: "Nueva torre" }),
    ).not.toBeInTheDocument();
  });

  it("elimina una torre exitosamente", async () => {
    const user = userEvent.setup();
    mockCondominioQuery.mockReturnValue({
      data: {
        condominium: buildCondominioDetail({
          towers: [buildTorre({ id: "torre-1", nombre: "Torre Única" })],
        }),
      },
      isLoading: false,
      isError: false,
    });
    mockDeleteTorreMutate.mockImplementation((_payload, opts) => {
      opts?.onSuccess?.();
    });

    renderDetallePage();

    await user.click(screen.getByTitle("Eliminar torre"));

    const dialog = await screen.findByRole("dialog", { name: /eliminar torre/i });
    expect(within(dialog).getByText(/Torre Única/)).toBeInTheDocument();

    await user.click(within(dialog).getByRole("button", { name: "Eliminar" }));

    expect(mockDeleteTorreMutate).toHaveBeenCalledTimes(1);
    expect(mockDeleteTorreMutate).toHaveBeenCalledWith(
      { id: "torre-1", condominiumId: "cond-1" },
      expect.objectContaining({
        onSuccess: expect.any(Function),
        onError: expect.any(Function),
      }),
    );
  });

  it("el tab Configuración muestra los datos del condominio y permite editar", async () => {
    const user = userEvent.setup();
    mockCondominioQuery.mockReturnValue({
      data: {
        condominium: buildCondominioDetail({
          nombre: "Conjunto Config",
          direccion: "Av. Siempre Viva 123",
          nit: "800999999-1",
          towers: [],
        }),
      },
      isLoading: false,
      isError: false,
    });
    mockUpdateCondominioMutate.mockImplementation((_payload, opts) => {
      opts?.onSuccess?.();
    });

    renderDetallePage();

    await user.click(screen.getByRole("tab", { name: /configuración/i }));

    expect(screen.getByLabelText("Nombre")).toHaveValue("Conjunto Config");
    expect(screen.getByLabelText("Dirección")).toHaveValue(
      "Av. Siempre Viva 123",
    );
    expect(screen.getByLabelText("NIT")).toHaveValue("800999999-1");

    await user.click(screen.getByRole("button", { name: /editar condominio/i }));

    const nombreInput = screen.getByPlaceholderText('Ej: "Conjunto El Paraíso"');
    await user.clear(nombreInput);
    await user.type(nombreInput, "Conjunto Config Editado");
    await user.click(screen.getByRole("button", { name: /^guardar$/i }));

    expect(mockUpdateCondominioMutate).toHaveBeenCalledTimes(1);
    expect(mockUpdateCondominioMutate).toHaveBeenCalledWith(
      {
        id: "cond-1",
        data: expect.objectContaining({ nombre: "Conjunto Config Editado" }),
      },
      expect.objectContaining({ onSuccess: expect.any(Function) }),
    );
  });

  it("bloquea eliminar condominio (botón disabled) cuando tiene torres", async () => {
    const user = userEvent.setup();
    mockCondominioQuery.mockReturnValue({
      data: {
        condominium: buildCondominioDetail({
          towers: [buildTorre({ id: "torre-1", nombre: "Torre A" })],
        }),
      },
      isLoading: false,
      isError: false,
    });

    renderDetallePage();

    await user.click(screen.getByRole("tab", { name: /configuración/i }));

    const deleteButton = screen.getByRole("button", {
      name: /eliminar condominio/i,
    });
    expect(deleteButton).toBeDisabled();
    expect(
      screen.getByText(/debes eliminar todas las torres/i),
    ).toBeInTheDocument();
  });

  it("elimina el condominio exitosamente cuando no tiene torres", async () => {
    const user = userEvent.setup();
    mockCondominioQuery.mockReturnValue({
      data: { condominium: buildCondominioDetail({ towers: [] }) },
      isLoading: false,
      isError: false,
    });
    mockDeleteCondominioMutate.mockImplementation((_id, opts) => {
      opts?.onSuccess?.();
    });

    renderDetallePage();

    await user.click(screen.getByRole("tab", { name: /configuración/i }));

    const deleteButton = screen.getByRole("button", {
      name: /eliminar condominio/i,
    });
    expect(deleteButton).not.toBeDisabled();
    await user.click(deleteButton);

    const dialog = await screen.findByRole("dialog", {
      name: /eliminar condominio/i,
    });
    await user.click(within(dialog).getByRole("button", { name: "Eliminar" }));

    expect(mockDeleteCondominioMutate).toHaveBeenCalledTimes(1);
    expect(mockDeleteCondominioMutate).toHaveBeenCalledWith(
      "cond-1",
      expect.objectContaining({
        onSuccess: expect.any(Function),
        onError: expect.any(Function),
      }),
    );

    // Tras el onSuccess, la página navega a /condominios.
    expect(screen.getByText("Lista de condominios")).toBeInTheDocument();
  });
});
