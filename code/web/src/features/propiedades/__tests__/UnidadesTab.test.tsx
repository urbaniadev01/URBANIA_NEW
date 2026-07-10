import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { UnidadesTab } from "@/features/propiedades/components/UnidadesTab";
import type {
  PropertyListItem,
  PropertyListResponse,
  TorreItem,
  CatalogoItem,
} from "@/features/propiedades/types";

// ── Mock de sonner (toast) ───────────────────────────────────────────────
vi.mock("sonner", () => ({
  toast: {
    success: vi.fn(),
    error: vi.fn(),
    warning: vi.fn(),
  },
}));
import { toast } from "sonner";

// ── Mocks de hooks de API ────────────────────────────────────────────────
const mockUsePropertiesInfiniteQuery = vi.fn();
const mockCreateMutate = vi.fn();
const mockUpdateMutate = vi.fn();
const mockDeleteMutate = vi.fn();
const mockBatchStatusMutate = vi.fn();
const mockBatchDeleteMutate = vi.fn();

vi.mock("@/features/propiedades/api/properties", async (importOriginal) => {
  const actual = await importOriginal<
    typeof import("@/features/propiedades/api/properties")
  >();
  return {
    ...actual,
    usePropertiesInfiniteQuery: (
      condominiumId: string | undefined,
      filters: unknown,
    ) => mockUsePropertiesInfiniteQuery(condominiumId, filters),
    useCreatePropertyMutation: () => ({
      mutate: mockCreateMutate,
      isPending: false,
    }),
    useUpdatePropertyMutation: () => ({
      mutate: mockUpdateMutate,
      isPending: false,
    }),
    useDeletePropertyMutation: () => ({
      mutate: mockDeleteMutate,
      isPending: false,
    }),
    useBatchUpdateStatusMutation: () => ({
      mutate: mockBatchStatusMutate,
      isPending: false,
    }),
    useBatchDeleteMutation: () => ({
      mutate: mockBatchDeleteMutate,
      isPending: false,
    }),
  };
});

const mockUseTorresQuery = vi.fn();
vi.mock("@/features/propiedades/api/towers", () => ({
  useTorresQuery: (condominiumId: string | undefined) =>
    mockUseTorresQuery(condominiumId),
}));

const mockUsePropertyTypesQuery = vi.fn();
vi.mock("@/features/propiedades/api/property-types", () => ({
  usePropertyTypesQuery: () => mockUsePropertyTypesQuery(),
}));

const mockUsePropertyStatusesQuery = vi.fn();
vi.mock("@/features/propiedades/api/property-statuses", () => ({
  usePropertyStatusesQuery: () => mockUsePropertyStatusesQuery(),
}));

// ── Fixtures ──────────────────────────────────────────────────────────────
const CONDOMINIO_ID = "cond-1";

const torres: TorreItem[] = [
  {
    id: "torre-1",
    condominium_id: CONDOMINIO_ID,
    nombre: "Torre A",
    created_by: null,
    updated_by: null,
    created_at: "",
    updated_at: "",
  },
  {
    id: "torre-2",
    condominium_id: CONDOMINIO_ID,
    nombre: "Torre B",
    created_by: null,
    updated_by: null,
    created_at: "",
    updated_at: "",
  },
];

const tipos: CatalogoItem[] = [
  {
    id: "tipo-1",
    organization_id: null,
    nombre: "Apartamento",
    descripcion: null,
    created_by: null,
    updated_by: null,
    created_at: "",
    updated_at: "",
  },
];

const estados: CatalogoItem[] = [
  {
    id: "estado-1",
    organization_id: null,
    nombre: "Ocupado",
    descripcion: null,
    created_by: null,
    updated_by: null,
    created_at: "",
    updated_at: "",
  },
  {
    id: "estado-2",
    organization_id: null,
    nombre: "Vacante",
    descripcion: null,
    created_by: null,
    updated_by: null,
    created_at: "",
    updated_at: "",
  },
];

const unidad1: PropertyListItem = {
  id: "unit-1",
  condominium_id: CONDOMINIO_ID,
  tower_id: "torre-1",
  property_type_id: "tipo-1",
  property_status_id: "estado-1",
  codigo: "A-101",
  piso: 1,
  created_by: null,
  updated_by: null,
  created_at: "",
  updated_at: "",
};

const unidad2: PropertyListItem = {
  id: "unit-2",
  condominium_id: CONDOMINIO_ID,
  tower_id: "torre-2",
  property_type_id: "tipo-1",
  property_status_id: "estado-2",
  codigo: "B-202",
  piso: 2,
  created_by: null,
  updated_by: null,
  created_at: "",
  updated_at: "",
};

function makePagesData(items: PropertyListItem[]) {
  const page: PropertyListResponse = {
    data: items,
    meta: { next_cursor: null },
  };
  return { pages: [page], pageParams: [undefined] };
}

function renderUnidadesTab() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <UnidadesTab condominioId={CONDOMINIO_ID} />
    </QueryClientProvider>,
  );
}

describe("UnidadesTab", () => {
  beforeEach(() => {
    vi.clearAllMocks();

    mockUsePropertiesInfiniteQuery.mockReturnValue({
      data: makePagesData([unidad1, unidad2]),
      isLoading: false,
      isError: false,
      fetchNextPage: vi.fn(),
      hasNextPage: false,
      isFetchingNextPage: false,
    });
    mockUseTorresQuery.mockReturnValue({ data: torres });
    mockUsePropertyTypesQuery.mockReturnValue({ data: tipos });
    mockUsePropertyStatusesQuery.mockReturnValue({ data: estados });
  });

  // CA1 / CA15 ─────────────────────────────────────────────────────────────
  it("renderiza la tabla con las unidades y sin columna area_m2", () => {
    renderUnidadesTab();

    expect(screen.getByText("A-101")).toBeInTheDocument();
    expect(screen.getByText("B-202")).toBeInTheDocument();

    const headers = screen.getAllByRole("columnheader").map((h) => h.textContent);
    expect(headers).toEqual(
      expect.arrayContaining(["Código", "Torre", "Tipo", "Estado", "Piso", "Acciones"]),
    );
    expect(headers.some((h) => /área|area_m2/i.test(h ?? ""))).toBe(false);
    expect(screen.queryByText(/área/i)).not.toBeInTheDocument();
  });

  // CA2 ────────────────────────────────────────────────────────────────────
  it("actualiza la query al filtrar por torre", async () => {
    const user = userEvent.setup();
    renderUnidadesTab();

    // Primer render: filtros vacíos (sin tower_id todavía).
    expect(mockUsePropertiesInfiniteQuery).toHaveBeenLastCalledWith(
      CONDOMINIO_ID,
      expect.not.objectContaining({ tower_id: expect.anything() }),
    );

    const towerSelect = screen.getAllByRole("combobox")[0]!;
    await user.selectOptions(towerSelect, "torre-1");

    await waitFor(() => {
      expect(mockUsePropertiesInfiniteQuery).toHaveBeenLastCalledWith(
        CONDOMINIO_ID,
        expect.objectContaining({ tower_id: "torre-1" }),
      );
    });
  });

  // CA5 / CA6 ──────────────────────────────────────────────────────────────
  it("crear unidad: valida campos requeridos y luego envía la mutación con datos válidos", async () => {
    mockCreateMutate.mockImplementation((_payload, options) => {
      options?.onSuccess?.();
    });
    const user = userEvent.setup();
    renderUnidadesTab();

    await user.click(screen.getByRole("button", { name: /nueva unidad/i }));
    expect(
      screen.getByRole("heading", { name: "Nueva unidad" }),
    ).toBeInTheDocument();

    // Enviar vacío → error de validación Zod, sin llamar a la mutación.
    await user.click(screen.getByRole("button", { name: /guardar/i }));
    expect(
      screen.getByText("El código es obligatorio."),
    ).toBeInTheDocument();
    expect(mockCreateMutate).not.toHaveBeenCalled();

    // Completar el formulario.
    await user.type(screen.getByPlaceholderText('Ej: "A-101"'), "C-303");

    const selects = screen.getAllByRole("combobox");
    // Dentro del Sheet: [torre, tipo, estado]
    const sheetSelects = selects.slice(-3);
    await user.selectOptions(sheetSelects[0]!, "torre-1");
    await user.selectOptions(sheetSelects[1]!, "tipo-1");
    await user.selectOptions(sheetSelects[2]!, "estado-1");

    await user.click(screen.getByRole("button", { name: /guardar/i }));

    await waitFor(() => {
      expect(mockCreateMutate).toHaveBeenCalledTimes(1);
    });
    const [payload] = mockCreateMutate.mock.calls[0]!;
    expect(payload).toEqual({
      condominiumId: CONDOMINIO_ID,
      data: {
        codigo: "C-303",
        tower_id: "torre-1",
        property_type_id: "tipo-1",
        property_status_id: "estado-1",
        piso: null,
        area_m2: null,
      },
    });

    // Al tener éxito, el Sheet se cierra.
    await waitFor(() => {
      expect(
        screen.queryByRole("heading", { name: "Nueva unidad" }),
      ).not.toBeInTheDocument();
    });
  });

  // CA7 ────────────────────────────────────────────────────────────────────
  it("muestra el toast de código duplicado en un error 422 y mantiene el Sheet abierto", async () => {
    mockCreateMutate.mockImplementation(() => {
      // Simula el onError real de useCreatePropertyMutation (PROPERTY_CODE_DUPLICATE).
      toast.error("Ya existe una unidad con ese código en este condominio.");
    });
    const user = userEvent.setup();
    renderUnidadesTab();

    await user.click(screen.getByRole("button", { name: /nueva unidad/i }));
    await user.type(screen.getByPlaceholderText('Ej: "A-101"'), "A-101");
    const sheetSelects = screen.getAllByRole("combobox").slice(-3);
    await user.selectOptions(sheetSelects[1]!, "tipo-1");
    await user.selectOptions(sheetSelects[2]!, "estado-1");

    await user.click(screen.getByRole("button", { name: /guardar/i }));

    await waitFor(() => {
      expect(mockCreateMutate).toHaveBeenCalledTimes(1);
    });
    expect(toast.error).toHaveBeenCalledWith(
      "Ya existe una unidad con ese código en este condominio.",
    );
    // El Sheet permanece abierto porque no se invocó onSuccess.
    expect(
      screen.getByRole("heading", { name: "Nueva unidad" }),
    ).toBeInTheDocument();
  });

  // CA8 ────────────────────────────────────────────────────────────────────
  it("muestra el toast de torre no perteneciente al condominio en un error 422", async () => {
    mockCreateMutate.mockImplementation(() => {
      toast.error("La torre seleccionada no pertenece a este condominio.");
    });
    const user = userEvent.setup();
    renderUnidadesTab();

    await user.click(screen.getByRole("button", { name: /nueva unidad/i }));
    await user.type(screen.getByPlaceholderText('Ej: "A-101"'), "C-303");
    const sheetSelects = screen.getAllByRole("combobox").slice(-3);
    await user.selectOptions(sheetSelects[0]!, "torre-1");
    await user.selectOptions(sheetSelects[1]!, "tipo-1");
    await user.selectOptions(sheetSelects[2]!, "estado-1");

    await user.click(screen.getByRole("button", { name: /guardar/i }));

    await waitFor(() => {
      expect(mockCreateMutate).toHaveBeenCalledTimes(1);
    });
    expect(toast.error).toHaveBeenCalledWith(
      "La torre seleccionada no pertenece a este condominio.",
    );
    expect(
      screen.getByRole("heading", { name: "Nueva unidad" }),
    ).toBeInTheDocument();
  });

  // CA9 ────────────────────────────────────────────────────────────────────
  it("editar: precarga los datos de la unidad y no expone condominium_id como campo editable", async () => {
    const user = userEvent.setup();
    renderUnidadesTab();

    const editButtons = screen.getAllByTitle("Editar unidad");
    await user.click(editButtons[0]!);

    expect(screen.getByText("Editar unidad")).toBeInTheDocument();
    expect(screen.getByPlaceholderText('Ej: "A-101"')).toHaveValue("A-101");

    const sheetSelects = screen.getAllByRole("combobox").slice(-3);
    expect(sheetSelects[0]).toHaveValue("torre-1"); // tower_id precargado
    expect(sheetSelects[1]).toHaveValue("tipo-1"); // property_type_id precargado
    expect(sheetSelects[2]).toHaveValue("estado-1"); // property_status_id precargado

    // No existe ningún campo para condominium_id en el formulario.
    expect(screen.queryByDisplayValue(CONDOMINIO_ID)).not.toBeInTheDocument();
  });

  // CA10 ───────────────────────────────────────────────────────────────────
  it("elimina una unidad tras confirmar en el diálogo", async () => {
    mockDeleteMutate.mockImplementation((_payload, options) => {
      options?.onSettled?.();
    });
    const user = userEvent.setup();
    renderUnidadesTab();

    const deleteButtons = screen.getAllByTitle("Eliminar unidad");
    await user.click(deleteButtons[0]!);

    expect(screen.getByText("Eliminar unidad")).toBeInTheDocument();

    const dialog = screen.getByRole("dialog");
    await user.click(within(dialog).getByRole("button", { name: /^eliminar$/i }));

    await waitFor(() => {
      expect(mockDeleteMutate).toHaveBeenCalledWith(
        { id: "unit-1", condominiumId: CONDOMINIO_ID },
        expect.anything(),
      );
    });
    await waitFor(() => {
      expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
    });
  });

  // CA11 ───────────────────────────────────────────────────────────────────
  it("bloquea la eliminación por ocupantes (409) y muestra el toast correspondiente", async () => {
    mockDeleteMutate.mockImplementation((_payload, options) => {
      toast.error("No se puede eliminar: la unidad tiene ocupantes activos.");
      options?.onSettled?.();
    });
    const user = userEvent.setup();
    renderUnidadesTab();

    const deleteButtons = screen.getAllByTitle("Eliminar unidad");
    await user.click(deleteButtons[0]!);
    const dialog = screen.getByRole("dialog");
    await user.click(within(dialog).getByRole("button", { name: /^eliminar$/i }));

    await waitFor(() => {
      expect(toast.error).toHaveBeenCalledWith(
        "No se puede eliminar: la unidad tiene ocupantes activos.",
      );
    });
  });

  // CA12 ───────────────────────────────────────────────────────────────────
  it("acción en lote: cambia el estado de las unidades seleccionadas", async () => {
    mockBatchStatusMutate.mockImplementation((_payload, options) => {
      options?.onSettled?.();
    });
    const user = userEvent.setup();
    renderUnidadesTab();

    const checkboxes = screen.getAllByRole("checkbox");
    // checkboxes[0] = "Seleccionar todas"; las filas empiezan en 1.
    await user.click(checkboxes[1]!);
    await user.click(checkboxes[2]!);

    expect(screen.getByText("2 seleccionadas")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: /cambiar estado/i }));
    const dialog = screen.getByRole("dialog");
    const statusSelect = within(dialog).getByRole("combobox");
    await user.selectOptions(statusSelect, "estado-2");
    await user.click(within(dialog).getByRole("button", { name: /aplicar/i }));

    await waitFor(() => {
      expect(mockBatchStatusMutate).toHaveBeenCalledTimes(1);
    });
    const [payload] = mockBatchStatusMutate.mock.calls[0]!;
    expect(payload).toEqual({
      condominiumId: CONDOMINIO_ID,
      items: [
        { id: "unit-1", codigo: "A-101" },
        { id: "unit-2", codigo: "B-202" },
      ],
      statusId: "estado-2",
    });

    // Tras la operación se limpia la selección.
    await waitFor(() => {
      expect(screen.queryByText("2 seleccionadas")).not.toBeInTheDocument();
    });
  });

  // CA13 ───────────────────────────────────────────────────────────────────
  it("acción en lote: eliminar seleccionadas con resultados mixtos no bloquea los éxitos", async () => {
    mockBatchDeleteMutate.mockImplementation((payload, options) => {
      const results = payload.items.map(
        (item: { id: string; codigo: string }, index: number) =>
          index === 0
            ? { propertyId: item.id, codigo: item.codigo, success: true }
            : {
                propertyId: item.id,
                codigo: item.codigo,
                success: false,
                error: "La unidad tiene ocupantes activos.",
              },
      );
      // Replica el comportamiento real: éxito parcial + advertencia + error individual.
      toast.success("1 eliminada.");
      toast.warning("1 unidad no pudo eliminarse.");
      toast.error(`"${results[1].codigo}": ${results[1].error}`);
      options?.onSettled?.();
    });
    const user = userEvent.setup();
    renderUnidadesTab();

    const checkboxes = screen.getAllByRole("checkbox");
    await user.click(checkboxes[1]!);
    await user.click(checkboxes[2]!);

    await user.click(screen.getByRole("button", { name: /eliminar seleccionadas/i }));
    const dialog = screen.getByRole("dialog");
    await user.click(within(dialog).getByRole("button", { name: /^eliminar$/i }));

    await waitFor(() => {
      expect(mockBatchDeleteMutate).toHaveBeenCalledTimes(1);
    });
    const [payload] = mockBatchDeleteMutate.mock.calls[0]!;
    expect(payload).toEqual({
      condominiumId: CONDOMINIO_ID,
      items: [
        { id: "unit-1", codigo: "A-101" },
        { id: "unit-2", codigo: "B-202" },
      ],
    });

    // El fallo de una unidad no bloquea el reporte de éxito de la otra.
    expect(toast.success).toHaveBeenCalledWith("1 eliminada.");
    expect(toast.warning).toHaveBeenCalledWith("1 unidad no pudo eliminarse.");
    expect(toast.error).toHaveBeenCalledWith(
      '"B-202": La unidad tiene ocupantes activos.',
    );

    await waitFor(() => {
      expect(screen.queryByText("2 seleccionadas")).not.toBeInTheDocument();
    });
  });
});
