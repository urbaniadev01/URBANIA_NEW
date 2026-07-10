import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, within, act } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter } from "react-router-dom";
import { TiposPropiedadPage } from "@/features/propiedades/pages/TiposPropiedadPage";
import type { CatalogoItem } from "@/features/propiedades/types";
import type { ApiError } from "@/types/api-error";

// Mock de los hooks de tipos de propiedad para que no hagan fetch real
const mockCreateMutate = vi.fn();
const mockUpdateMutate = vi.fn();
const mockDeleteMutate = vi.fn();

let queryData: CatalogoItem[] = [];
let queryIsLoading = false;
let createIsPending = false;
let updateIsPending = false;
let deleteIsPending = false;

vi.mock("@/features/propiedades/api/property-types", () => ({
  usePropertyTypesQuery: () => ({
    data: queryData,
    isLoading: queryIsLoading,
  }),
  useCreatePropertyTypeMutation: () => ({
    mutate: mockCreateMutate,
    isPending: createIsPending,
  }),
  useUpdatePropertyTypeMutation: () => ({
    mutate: mockUpdateMutate,
    isPending: updateIsPending,
  }),
  useDeletePropertyTypeMutation: () => ({
    mutate: mockDeleteMutate,
    isPending: deleteIsPending,
  }),
}));

const SYSTEM_ITEM: CatalogoItem = {
  id: "11111111-1111-1111-1111-111111111111",
  organization_id: null,
  nombre: "Apartamento",
  descripcion: "Unidad residencial estándar",
  created_by: null,
  updated_by: null,
  created_at: "2026-01-01T00:00:00Z",
  updated_at: "2026-01-01T00:00:00Z",
};

const CUSTOM_ITEM: CatalogoItem = {
  id: "22222222-2222-2222-2222-222222222222",
  organization_id: "33333333-3333-3333-3333-333333333333",
  nombre: "Bodega premium",
  descripcion: "Bodega con climatización",
  created_by: "user-1",
  updated_by: "user-1",
  created_at: "2026-02-01T00:00:00Z",
  updated_at: "2026-02-01T00:00:00Z",
};

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <TiposPropiedadPage />
      </BrowserRouter>
    </QueryClientProvider>,
  );
}

describe("TiposPropiedadPage", () => {
  beforeEach(() => {
    mockCreateMutate.mockClear();
    mockUpdateMutate.mockClear();
    mockDeleteMutate.mockClear();
    queryData = [SYSTEM_ITEM, CUSTOM_ITEM];
    queryIsLoading = false;
    createIsPending = false;
    updateIsPending = false;
    deleteIsPending = false;
  });

  it("renderiza la tabla con el item de sistema (badge Sistema) y el personalizado (badge Personalizado)", () => {
    renderPage();

    expect(screen.getByText("Tipos de Propiedad")).toBeInTheDocument();

    const systemRow = screen.getByText("Apartamento").closest("tr");
    expect(systemRow).not.toBeNull();
    expect(within(systemRow as HTMLElement).getByText("Sistema")).toBeInTheDocument();
    expect(within(systemRow as HTMLElement).getByText("Solo lectura")).toBeInTheDocument();

    const customRow = screen.getByText("Bodega premium").closest("tr");
    expect(customRow).not.toBeNull();
    expect(
      within(customRow as HTMLElement).getByText("Personalizado"),
    ).toBeInTheDocument();
    expect(within(customRow as HTMLElement).getByTitle("Editar")).toBeInTheDocument();
    expect(within(customRow as HTMLElement).getByTitle("Eliminar")).toBeInTheDocument();
  });

  it("abre el diálogo 'Nuevo Tipo de propiedad' al hacer click en Nuevo", async () => {
    const user = userEvent.setup();
    renderPage();

    await user.click(screen.getByRole("button", { name: /nuevo/i }));

    expect(
      screen.getByRole("heading", { name: "Nuevo Tipo de propiedad" }),
    ).toBeInTheDocument();
    expect(screen.getByLabelText("Nombre")).toHaveValue("");
  });

  it("bloquea el submit y muestra error de validación cuando el nombre está vacío", async () => {
    const user = userEvent.setup();
    renderPage();

    await user.click(screen.getByRole("button", { name: /nuevo/i }));
    await user.click(screen.getByRole("button", { name: /guardar/i }));

    expect(screen.getByText("El nombre es obligatorio.")).toBeInTheDocument();
    expect(mockCreateMutate).not.toHaveBeenCalled();
  });

  it("crea exitosamente llamando a la mutación con el payload correcto", async () => {
    const user = userEvent.setup();
    renderPage();

    await user.click(screen.getByRole("button", { name: /nuevo/i }));
    await user.type(screen.getByLabelText("Nombre"), "Local comercial");
    await user.type(
      screen.getByLabelText("Descripción (opcional)"),
      "Local en primer piso",
    );
    await user.click(screen.getByRole("button", { name: /guardar/i }));

    expect(mockCreateMutate).toHaveBeenCalledTimes(1);
    expect(mockCreateMutate).toHaveBeenCalledWith(
      {
        nombre: "Local comercial",
        descripcion: "Local en primer piso",
      },
      expect.objectContaining({ onSuccess: expect.any(Function) }),
    );
  });

  it("precarga los datos al editar un item personalizado", async () => {
    const user = userEvent.setup();
    renderPage();

    const customRow = screen.getByText("Bodega premium").closest("tr") as HTMLElement;
    await user.click(within(customRow).getByTitle("Editar"));

    expect(
      screen.getByRole("heading", { name: "Editar Tipo de propiedad" }),
    ).toBeInTheDocument();
    expect(screen.getByLabelText("Nombre")).toHaveValue("Bodega premium");
    expect(screen.getByLabelText("Descripción (opcional)")).toHaveValue(
      "Bodega con climatización",
    );
  });

  it("actualiza llamando a la mutación con id y payload correctos", async () => {
    const user = userEvent.setup();
    renderPage();

    const customRow = screen.getByText("Bodega premium").closest("tr") as HTMLElement;
    await user.click(within(customRow).getByTitle("Editar"));

    const nombreInput = screen.getByLabelText("Nombre");
    await user.clear(nombreInput);
    await user.type(nombreInput, "Bodega premium XL");
    await user.click(screen.getByRole("button", { name: /guardar/i }));

    expect(mockUpdateMutate).toHaveBeenCalledTimes(1);
    expect(mockUpdateMutate).toHaveBeenCalledWith(
      {
        id: CUSTOM_ITEM.id,
        data: {
          nombre: "Bodega premium XL",
          descripcion: "Bodega con climatización",
        },
      },
      expect.objectContaining({ onSuccess: expect.any(Function) }),
    );
  });

  it("abre confirmación de eliminar y llama a la mutación de delete al confirmar", async () => {
    const user = userEvent.setup();
    renderPage();

    const customRow = screen.getByText("Bodega premium").closest("tr") as HTMLElement;
    await user.click(within(customRow).getByTitle("Eliminar"));

    expect(
      screen.getByText(/¿Estás seguro de eliminar/i),
    ).toBeInTheDocument();
    expect(screen.getByText(/"Bodega premium"/)).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: /^eliminar$/i }));

    expect(mockDeleteMutate).toHaveBeenCalledTimes(1);
    expect(mockDeleteMutate).toHaveBeenCalledWith(
      CUSTOM_ITEM.id,
      expect.objectContaining({
        onSuccess: expect.any(Function),
        onError: expect.any(Function),
      }),
    );
  });

  it("muestra el mensaje de warning en el diálogo de confirmación cuando el delete falla con 409 IN_USE, sin cerrarlo", async () => {
    const user = userEvent.setup();
    renderPage();

    const customRow = screen.getByText("Bodega premium").closest("tr") as HTMLElement;
    await user.click(within(customRow).getByTitle("Eliminar"));
    await user.click(screen.getByRole("button", { name: /^eliminar$/i }));

    // Capturamos el onError pasado a mutate() y lo invocamos manualmente
    // simulando la respuesta 409 del backend.
    const call = mockDeleteMutate.mock.calls[0] as [
      string,
      { onSuccess: () => void; onError: (error: ApiError) => void },
    ];
    const { onError } = call[1];

    const conflictError: ApiError = {
      code: "PROPERTY_TYPE_IN_USE",
      message: "No se puede eliminar: está en uso por 3 propiedades.",
      trace_id: "trace-409",
    };

    act(() => {
      onError(conflictError);
    });

    expect(
      await screen.findByText("No se puede eliminar: está en uso por 3 propiedades."),
    ).toBeInTheDocument();
    // El diálogo sigue abierto: el nombre del item todavía se muestra
    expect(screen.getByText(/"Bodega premium"/)).toBeInTheDocument();
  });

  it("muestra estado de carga mientras isLoading es true", () => {
    queryIsLoading = true;
    renderPage();

    expect(screen.queryByText("Bodega premium")).not.toBeInTheDocument();
  });

  it("muestra el estado vacío cuando no hay items", () => {
    queryData = [];
    renderPage();

    expect(screen.getByText("No hay elementos registrados.")).toBeInTheDocument();
  });
});
