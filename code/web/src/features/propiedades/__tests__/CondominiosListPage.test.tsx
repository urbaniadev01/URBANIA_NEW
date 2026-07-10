import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { MemoryRouter, Routes, Route, useParams } from "react-router-dom";
import { CondominiosListPage } from "@/features/propiedades/pages/CondominiosListPage";
import type { CondominioItem } from "@/features/propiedades/types";

// ── Mocks de hooks de API — sin fetch real ─────────────────────────────────

const mockCondominiumsQuery = vi.fn();
const mockCreateMutate = vi.fn();

vi.mock("@/features/propiedades/api/condominiums", () => ({
  useCondominiumsQuery: () => mockCondominiumsQuery(),
  useCreateCondominioMutation: () => ({
    mutate: mockCreateMutate,
    isPending: false,
    error: null,
    isError: false,
    isSuccess: false,
    data: undefined,
    reset: vi.fn(),
  }),
}));

// ── Helpers ─────────────────────────────────────────────────────────────

function buildCondominio(overrides: Partial<CondominioItem>): CondominioItem {
  return {
    id: "cond-1",
    organization_id: "org-1",
    nombre: "Conjunto A",
    direccion: null,
    nit: null,
    created_by: null,
    updated_by: null,
    created_at: "2026-01-01T00:00:00Z",
    updated_at: "2026-01-01T00:00:00Z",
    ...overrides,
  };
}

/** Ruta de detalle "probe" — solo para verificar que la navegación ocurrió. */
function DetalleProbe(): React.ReactNode {
  const { id } = useParams<{ id: string }>();
  return <div>Detalle del condominio: {id}</div>;
}

function renderListPage(initialEntries: string[] = ["/condominios"]) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={initialEntries}>
        <Routes>
          <Route path="/condominios" element={<CondominiosListPage />} />
          <Route path="/condominios/:id" element={<DetalleProbe />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe("CondominiosListPage", () => {
  beforeEach(() => {
    mockCondominiumsQuery.mockReset();
    mockCreateMutate.mockReset();
    mockCreateMutate.mockImplementation(() => {
      // No-op por defecto — cada test configura el comportamiento que necesita.
    });
  });

  it("renderiza el grid de cards con los condominios recibidos", () => {
    mockCondominiumsQuery.mockReturnValue({
      data: [
        buildCondominio({ id: "cond-1", nombre: "Conjunto A" }),
        buildCondominio({ id: "cond-2", nombre: "Conjunto B" }),
      ],
      isLoading: false,
    });

    renderListPage();

    expect(screen.getByText("Conjunto A")).toBeInTheDocument();
    expect(screen.getByText("Conjunto B")).toBeInTheDocument();
  });

  it("la búsqueda filtra los condominios localmente por nombre", async () => {
    const user = userEvent.setup();
    mockCondominiumsQuery.mockReturnValue({
      data: [
        buildCondominio({ id: "cond-1", nombre: "Conjunto A" }),
        buildCondominio({ id: "cond-2", nombre: "Conjunto B" }),
        buildCondominio({ id: "cond-3", nombre: "Edificio C" }),
      ],
      isLoading: false,
    });

    renderListPage();

    const searchInput = screen.getByPlaceholderText("Buscar por nombre...");
    await user.type(searchInput, "conjunto");

    expect(screen.getByText("Conjunto A")).toBeInTheDocument();
    expect(screen.getByText("Conjunto B")).toBeInTheDocument();
    expect(screen.queryByText("Edificio C")).not.toBeInTheDocument();
  });

  it("muestra error de validación al crear un condominio sin nombre", async () => {
    const user = userEvent.setup();
    mockCondominiumsQuery.mockReturnValue({ data: [], isLoading: false });

    renderListPage();

    await user.click(screen.getByRole("button", { name: /crear primero/i }));
    await user.click(screen.getByRole("button", { name: /^guardar$/i }));

    expect(screen.getByText("El nombre es obligatorio.")).toBeInTheDocument();
    expect(mockCreateMutate).not.toHaveBeenCalled();
  });

  it("crea un condominio exitosamente y cierra el sheet", async () => {
    const user = userEvent.setup();
    mockCondominiumsQuery.mockReturnValue({ data: [], isLoading: false });
    mockCreateMutate.mockImplementation((_payload, opts) => {
      opts?.onSuccess?.();
    });

    renderListPage();

    await user.click(screen.getByRole("button", { name: /nuevo condominio/i }));
    await user.type(
      screen.getByPlaceholderText('Ej: "Conjunto El Paraíso"'),
      "Conjunto Nuevo",
    );
    await user.click(screen.getByRole("button", { name: /^guardar$/i }));

    expect(mockCreateMutate).toHaveBeenCalledTimes(1);
    expect(mockCreateMutate).toHaveBeenCalledWith(
      {
        nombre: "Conjunto Nuevo",
        direccion: undefined,
        nit: undefined,
      },
      expect.objectContaining({ onSuccess: expect.any(Function) }),
    );

    // El sheet se cierra tras el onSuccess — el título del Sheet ya no debería estar visible.
    expect(
      screen.queryByRole("heading", { name: "Nuevo condominio" }),
    ).not.toBeInTheDocument();
  });

  it("no cierra el sheet cuando la creación falla (ej. nombre duplicado 422)", async () => {
    const user = userEvent.setup();
    mockCondominiumsQuery.mockReturnValue({ data: [], isLoading: false });
    // Simula un error del servidor: mutate se llama pero onSuccess nunca se invoca
    // (el toast de error lo maneja el hook real, fuera del alcance de este test).
    mockCreateMutate.mockImplementation(() => {
      // no-op: simula que la mutación falló y no llamó a onSuccess
    });

    renderListPage();

    await user.click(screen.getByRole("button", { name: /nuevo condominio/i }));
    await user.type(
      screen.getByPlaceholderText('Ej: "Conjunto El Paraíso"'),
      "Conjunto Repetido",
    );
    await user.click(screen.getByRole("button", { name: /^guardar$/i }));

    expect(mockCreateMutate).toHaveBeenCalledTimes(1);
    // El sheet permanece abierto porque no se ejecutó onSuccess (título del Sheet, no el botón).
    expect(
      screen.getByRole("heading", { name: "Nuevo condominio" }),
    ).toBeInTheDocument();
  });

  it("al hacer click en una card navega al detalle del condominio", async () => {
    const user = userEvent.setup();
    mockCondominiumsQuery.mockReturnValue({
      data: [buildCondominio({ id: "cond-42", nombre: "Conjunto Click" })],
      isLoading: false,
    });

    renderListPage();

    await user.click(screen.getByRole("button", { name: /Conjunto Click/i }));

    expect(
      screen.getByText("Detalle del condominio: cond-42"),
    ).toBeInTheDocument();
  });
});
