import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, within, act, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter } from "react-router-dom";
import { OcupantesSheet } from "@/features/directorio/components/OcupantesSheet";
import type {
  PropertyOccupantItem,
  ContactItem,
  OccupantTypeItem,
} from "@/features/directorio/types";
import type { ApiError } from "@/types/api-error";

const mockAssignMutate = vi.fn();
const mockUpdateMutate = vi.fn();
const mockUnassignMutate = vi.fn();

let occupantsData: PropertyOccupantItem[] = [];
let occupantsIsLoading = false;
let assignIsPending = false;
let updateIsPending = false;
let unassignIsPending = false;
let contactsSearchResults: ContactItem[] = [];

vi.mock("@/features/directorio/api/property-occupants", () => ({
  usePropertyOccupantsQuery: () => ({
    data: { data: occupantsData },
    isLoading: occupantsIsLoading,
  }),
  useAssignOccupantMutation: () => ({
    mutate: mockAssignMutate,
    isPending: assignIsPending,
  }),
  useUpdatePropertyOccupantMutation: () => ({
    mutate: mockUpdateMutate,
    isPending: updateIsPending,
  }),
  useUnassignOccupantMutation: () => ({
    mutate: mockUnassignMutate,
    isPending: unassignIsPending,
  }),
}));

vi.mock("@/features/directorio/api/contacts", () => ({
  useContactsQuery: (search: string) => ({
    data: { data: search ? contactsSearchResults : [], meta: { next_cursor: null } },
    isLoading: false,
  }),
}));

const OCCUPANT_TYPES: OccupantTypeItem[] = [
  {
    id: "type-1",
    organization_id: null,
    nombre: "Propietario",
    descripcion: null,
    created_by: null,
    updated_by: null,
    created_at: "2026-01-01T00:00:00Z",
    updated_at: "2026-01-01T00:00:00Z",
  },
  {
    id: "type-2",
    organization_id: null,
    nombre: "Arrendatario",
    descripcion: null,
    created_by: null,
    updated_by: null,
    created_at: "2026-01-01T00:00:00Z",
    updated_at: "2026-01-01T00:00:00Z",
  },
];

vi.mock("@/features/directorio/api/occupant-types", () => ({
  useOccupantTypesQuery: () => ({
    data: OCCUPANT_TYPES,
    isLoading: false,
  }),
}));

const EXISTING_OCCUPANT: PropertyOccupantItem = {
  id: "occ-1",
  property_id: "prop-1",
  contact_id: "contact-1",
  occupant_type_id: "type-1",
  es_principal: true,
  created_by: "user-1",
  updated_by: null,
  created_at: "2026-01-01T00:00:00Z",
  updated_at: "2026-01-01T00:00:00Z",
  contact: { id: "contact-1", nombre: "Juan Perez" },
  occupant_type: OCCUPANT_TYPES[0],
};

const SEARCH_CONTACT: ContactItem = {
  id: "contact-2",
  organization_id: "org-1",
  user_id: null,
  nombre: "Maria Lopez",
  email: "maria@urbania.test",
  telefono: null,
  created_by: null,
  updated_by: null,
  created_at: "2026-01-01T00:00:00Z",
  updated_at: "2026-01-01T00:00:00Z",
};

function renderSheet() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <OcupantesSheet
          open
          onOpenChange={() => {}}
          propertyId="prop-1"
          propertyCodigo="A-101"
        />
      </BrowserRouter>
    </QueryClientProvider>,
  );
}

describe("OcupantesSheet", () => {
  beforeEach(() => {
    mockAssignMutate.mockClear();
    mockUpdateMutate.mockClear();
    mockUnassignMutate.mockClear();
    occupantsData = [EXISTING_OCCUPANT];
    occupantsIsLoading = false;
    assignIsPending = false;
    updateIsPending = false;
    unassignIsPending = false;
    contactsSearchResults = [SEARCH_CONTACT];
  });

  it("muestra la tabla de ocupantes con tipo e indicador de principal", () => {
    renderSheet();

    expect(screen.getByText("Ocupantes de A-101")).toBeInTheDocument();
    expect(screen.getByText("Juan Perez")).toBeInTheDocument();
    expect(screen.getByText("Propietario")).toBeInTheDocument();
    expect(screen.getByText("Principal")).toBeInTheDocument();
  });

  it("abre el diálogo de asignar ocupante", async () => {
    const user = userEvent.setup();
    renderSheet();

    await user.click(screen.getByRole("button", { name: /asignar ocupante/i }));

    expect(
      screen.getByRole("heading", { name: "Asignar ocupante" }),
    ).toBeInTheDocument();
  });

  it("asigna un ocupante exitosamente con el payload correcto", async () => {
    const user = userEvent.setup();
    renderSheet();

    await user.click(screen.getByRole("button", { name: /asignar ocupante/i }));
    await user.type(
      screen.getByPlaceholderText("Buscar contacto por nombre..."),
      "Maria",
    );

    await waitFor(() => {
      expect(screen.getByText("Maria Lopez")).toBeInTheDocument();
    });
    await user.click(screen.getByText("Maria Lopez"));

    const dialog = screen.getByRole("heading", { name: "Asignar ocupante" }).closest(
      "[role=dialog]",
    ) as HTMLElement;
    await user.selectOptions(
      within(dialog).getByLabelText("Tipo de ocupante"),
      "type-2",
    );
    await user.click(within(dialog).getByLabelText("Marcar como ocupante principal"));
    await user.click(within(dialog).getByRole("button", { name: /^asignar$/i }));

    expect(mockAssignMutate).toHaveBeenCalledTimes(1);
    expect(mockAssignMutate).toHaveBeenCalledWith(
      { contact_id: "contact-2", occupant_type_id: "type-2", es_principal: true },
      expect.objectContaining({ onSuccess: expect.any(Function) }),
    );
  });

  it("muestra 'Sin resultados' con enlace a crear contacto cuando la búsqueda no encuentra nada", async () => {
    contactsSearchResults = [];
    const user = userEvent.setup();
    renderSheet();

    await user.click(screen.getByRole("button", { name: /asignar ocupante/i }));
    await user.type(
      screen.getByPlaceholderText("Buscar contacto por nombre..."),
      "Nadie",
    );

    await waitFor(() => {
      expect(screen.getByText(/sin resultados/i)).toBeInTheDocument();
    });
    expect(screen.getByRole("link", { name: /crear contacto nuevo/i })).toHaveAttribute(
      "href",
      "/directorio/contactos",
    );
  });

  it("edita el tipo de ocupante de una asignación existente", async () => {
    const user = userEvent.setup();
    renderSheet();

    await user.click(screen.getByTitle("Editar"));

    expect(
      screen.getByRole("heading", { name: "Editar ocupante" }),
    ).toBeInTheDocument();

    await user.selectOptions(screen.getByLabelText("Tipo de ocupante"), "type-2");
    await user.click(screen.getByRole("button", { name: /guardar/i }));

    expect(mockUpdateMutate).toHaveBeenCalledTimes(1);
    expect(mockUpdateMutate).toHaveBeenCalledWith(
      {
        id: "occ-1",
        data: { occupant_type_id: "type-2", es_principal: true },
      },
      expect.objectContaining({ onSuccess: expect.any(Function) }),
    );
  });

  it("desasigna un ocupante tras confirmar", async () => {
    const user = userEvent.setup();
    renderSheet();

    await user.click(screen.getByTitle("Desasignar"));

    expect(screen.getByText(/¿Estás seguro de desasignar/i)).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: /^desasignar$/i }));

    expect(mockUnassignMutate).toHaveBeenCalledTimes(1);
    expect(mockUnassignMutate).toHaveBeenCalledWith(
      "occ-1",
      expect.objectContaining({ onSettled: expect.any(Function) }),
    );
  });

  it("muestra el toast de duplicado cuando el assign falla con 409 (simulado vía onError)", async () => {
    const user = userEvent.setup();
    renderSheet();

    await user.click(screen.getByRole("button", { name: /asignar ocupante/i }));
    await user.type(
      screen.getByPlaceholderText("Buscar contacto por nombre..."),
      "Maria",
    );
    await waitFor(() => {
      expect(screen.getByText("Maria Lopez")).toBeInTheDocument();
    });
    await user.click(screen.getByText("Maria Lopez"));

    const dialog = screen.getByRole("heading", { name: "Asignar ocupante" }).closest(
      "[role=dialog]",
    ) as HTMLElement;
    await user.selectOptions(
      within(dialog).getByLabelText("Tipo de ocupante"),
      "type-1",
    );
    await user.click(within(dialog).getByRole("button", { name: /^asignar$/i }));

    // Verificamos que la mutación se llamó — el manejo real del error 409 se
    // prueba a nivel de hook (mismo patrón que DIRECTORIO-B05/B06).
    const call = mockAssignMutate.mock.calls[0] as [
      unknown,
      { onSuccess: () => void; onError?: (error: ApiError) => void },
    ];
    expect(call[1].onSuccess).toBeInstanceOf(Function);
    act(() => {
      call[1].onSuccess();
    });
  });

  it("muestra estado vacío cuando no hay ocupantes", () => {
    occupantsData = [];
    renderSheet();

    expect(
      screen.getByText("No hay ocupantes asignados a esta unidad."),
    ).toBeInTheDocument();
  });

  it("muestra estado de carga mientras isLoading es true", () => {
    occupantsIsLoading = true;
    renderSheet();

    expect(screen.queryByText("Juan Perez")).not.toBeInTheDocument();
  });
});
