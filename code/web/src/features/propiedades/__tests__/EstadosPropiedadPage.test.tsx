import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, within, act } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter } from "react-router-dom";
import { EstadosPropiedadPage } from "@/features/propiedades/pages/EstadosPropiedadPage";
import type { CatalogoItem } from "@/features/propiedades/types";
import type { ApiError } from "@/types/api-error";

// Mock de los hooks de estados de propiedad para que no hagan fetch real
const mockCreateMutate = vi.fn();
const mockUpdateMutate = vi.fn();
const mockDeleteMutate = vi.fn();

let queryData: CatalogoItem[] = [];
let queryIsLoading = false;
let createIsPending = false;
let updateIsPending = false;
let deleteIsPending = false;

vi.mock("@/features/propiedades/api/property-statuses", () => ({
  usePropertyStatusesQuery: () => ({
    data: queryData,
    isLoading: queryIsLoading,
  }),
  useCreatePropertyStatusMutation: () => ({
    mutate: mockCreateMutate,
    isPending: createIsPending,
  }),
  useUpdatePropertyStatusMutation: () => ({
    mutate: mockUpdateMutate,
    isPending: updateIsPending,
  }),
  useDeletePropertyStatusMutation: () => ({
    mutate: mockDeleteMutate,
    isPending: deleteIsPending,
  }),
}));

const SYSTEM_ITEM: CatalogoItem = {
  id: "44444444-4444-4444-4444-444444444444",
  organization_id: null,
  nombre: "Disponible",
  descripcion: "Unidad lista para ocupar",
  created_by: null,
  updated_by: null,
  created_at: "2026-01-01T00:00:00Z",
  updated_at: "2026-01-01T00:00:00Z",
};

const CUSTOM_ITEM: CatalogoItem = {
  id: "55555555-5555-5555-5555-555555555555",
  organization_id: "66666666-6666-6666-6666-666666666666",
  nombre: "En remodelación",
  descripcion: "Unidad temporalmente fuera de servicio",
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
        <EstadosPropiedadPage />
      </BrowserRouter>
    </QueryClientProvider>,
  );
}

describe("EstadosPropiedadPage", () => {
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

    expect(screen.getByText("Estados de Propiedad")).toBeInTheDocument();

    const systemRow = screen.getByText("Disponible").closest("tr");
    expect(systemRow).not.toBeNull();
    expect(within(systemRow as HTMLElement).getByText("Sistema")).toBeInTheDocument();
    expect(within(systemRow as HTMLElement).getByText("Solo lectura")).toBeInTheDocument();

    const customRow = screen.getByText("En remodelación").closest("tr");
    expect(customRow).not.toBeNull();
    expect(
      within(customRow as HTMLElement).getByText("Personalizado"),
    ).toBeInTheDocument();
    expect(within(customRow as HTMLElement).getByTitle("Editar")).toBeInTheDocument();
    expect(within(customRow as HTMLElement).getByTitle("Eliminar")).toBeInTheDocument();
  });

  it("abre el diálogo 'Nuevo Estado de propiedad' al hacer click en Nuevo", async () => {
    const user = userEvent.setup();
    renderPage();

    await user.click(screen.getByRole("button", { name: /nuevo/i }));

    expect(
      screen.getByRole("heading", { name: "Nuevo Estado de propiedad" }),
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
    await user.type(screen.getByLabelText("Nombre"), "Reservado");
    await user.type(
      screen.getByLabelText("Descripción (opcional)"),
      "Unidad con apartado",
    );
    await user.click(screen.getByRole("button", { name: /guardar/i }));

    expect(mockCreateMutate).toHaveBeenCalledTimes(1);
    expect(mockCreateMutate).toHaveBeenCalledWith(
      {
        nombre: "Reservado",
        descripcion: "Unidad con apartado",
      },
      expect.objectContaining({ onSuccess: expect.any(Function) }),
    );
  });

  it("precarga los datos al editar un item personalizado", async () => {
    const user = userEvent.setup();
    renderPage();

    const customRow = screen.getByText("En remodelación").closest("tr") as HTMLElement;
    await user.click(within(customRow).getByTitle("Editar"));

    expect(
      screen.getByRole("heading", { name: "Editar Estado de propiedad" }),
    ).toBeInTheDocument();
    expect(screen.getByLabelText("Nombre")).toHaveValue("En remodelación");
    expect(screen.getByLabelText("Descripción (opcional)")).toHaveValue(
      "Unidad temporalmente fuera de servicio",
    );
  });

  it("actualiza llamando a la mutación con id y payload correctos", async () => {
    const user = userEvent.setup();
    renderPage();

    const customRow = screen.getByText("En remodelación").closest("tr") as HTMLElement;
    await user.click(within(customRow).getByTitle("Editar"));

    const nombreInput = screen.getByLabelText("Nombre");
    await user.clear(nombreInput);
    await user.type(nombreInput, "En remodelación mayor");
    await user.click(screen.getByRole("button", { name: /guardar/i }));

    expect(mockUpdateMutate).toHaveBeenCalledTimes(1);
    expect(mockUpdateMutate).toHaveBeenCalledWith(
      {
        id: CUSTOM_ITEM.id,
        data: {
          nombre: "En remodelación mayor",
          descripcion: "Unidad temporalmente fuera de servicio",
        },
      },
      expect.objectContaining({ onSuccess: expect.any(Function) }),
    );
  });

  it("abre confirmación de eliminar y llama a la mutación de delete al confirmar", async () => {
    const user = userEvent.setup();
    renderPage();

    const customRow = screen.getByText("En remodelación").closest("tr") as HTMLElement;
    await user.click(within(customRow).getByTitle("Eliminar"));

    expect(
      screen.getByText(/¿Estás seguro de eliminar/i),
    ).toBeInTheDocument();
    expect(screen.getByText(/"En remodelación"/)).toBeInTheDocument();

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

    const customRow = screen.getByText("En remodelación").closest("tr") as HTMLElement;
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
      code: "PROPERTY_STATUS_IN_USE",
      message: "No se puede eliminar: está en uso por 2 propiedades.",
      trace_id: "trace-409",
    };

    act(() => {
      onError(conflictError);
    });

    expect(
      await screen.findByText("No se puede eliminar: está en uso por 2 propiedades."),
    ).toBeInTheDocument();
    // El diálogo sigue abierto: el nombre del item todavía se muestra
    expect(screen.getByText(/"En remodelación"/)).toBeInTheDocument();
  });

  it("muestra estado de carga mientras isLoading es true", () => {
    queryIsLoading = true;
    renderPage();

    expect(screen.queryByText("En remodelación")).not.toBeInTheDocument();
  });

  it("muestra el estado vacío cuando no hay items", () => {
    queryData = [];
    renderPage();

    expect(screen.getByText("No hay elementos registrados.")).toBeInTheDocument();
  });
});
