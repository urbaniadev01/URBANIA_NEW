import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen, within, act, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter } from "react-router-dom";
import { ContactosPage } from "@/features/directorio/pages/ContactosPage";
import type { ContactItem } from "@/features/directorio/types";
import type { ApiError } from "@/types/api-error";

const mockCreateMutate = vi.fn();
const mockUpdateMutate = vi.fn();
const mockDeleteMutate = vi.fn();
const mockUseContactsQuery = vi.fn();

let queryData: ContactItem[] = [];
let queryIsLoading = false;
let createIsPending = false;
let updateIsPending = false;
let deleteIsPending = false;

vi.mock("@/features/directorio/api/contacts", () => ({
  useContactsQuery: (search: string) => {
    mockUseContactsQuery(search);
    return {
      data: { data: queryData, meta: { next_cursor: null } },
      isLoading: queryIsLoading,
    };
  },
  useContactPropertiesQuery: () => ({
    data: { data: [] },
    isLoading: false,
  }),
  useCreateContactMutation: () => ({
    mutate: mockCreateMutate,
    isPending: createIsPending,
  }),
  useUpdateContactMutation: () => ({
    mutate: mockUpdateMutate,
    isPending: updateIsPending,
  }),
  useDeleteContactMutation: () => ({
    mutate: mockDeleteMutate,
    isPending: deleteIsPending,
  }),
}));

const NO_ACCOUNT_ITEM: ContactItem = {
  id: "11111111-1111-1111-1111-111111111111",
  organization_id: "org-1",
  user_id: null,
  nombre: "Ana Gomez",
  email: "ana@urbania.test",
  telefono: "3001234567",
  created_by: "user-1",
  updated_by: null,
  created_at: "2026-01-01T00:00:00Z",
  updated_at: "2026-01-01T00:00:00Z",
};

const WITH_ACCOUNT_ITEM: ContactItem = {
  id: "22222222-2222-2222-2222-222222222222",
  organization_id: "org-1",
  user_id: "user-2",
  nombre: "Carlos Ruiz",
  email: "carlos@urbania.test",
  telefono: null,
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
        <ContactosPage />
      </BrowserRouter>
    </QueryClientProvider>,
  );
}

describe("ContactosPage", () => {
  beforeEach(() => {
    mockCreateMutate.mockClear();
    mockUpdateMutate.mockClear();
    mockDeleteMutate.mockClear();
    mockUseContactsQuery.mockClear();
    queryData = [NO_ACCOUNT_ITEM, WITH_ACCOUNT_ITEM];
    queryIsLoading = false;
    createIsPending = false;
    updateIsPending = false;
    deleteIsPending = false;
  });

  it("renderiza la tabla con badges 'Sin cuenta' y 'Con cuenta' segun user_id", () => {
    renderPage();

    expect(screen.getByText("Contactos")).toBeInTheDocument();

    const noAccountRow = screen.getByText("Ana Gomez").closest("tr") as HTMLElement;
    expect(within(noAccountRow).getByText("Sin cuenta")).toBeInTheDocument();

    const withAccountRow = screen.getByText("Carlos Ruiz").closest("tr") as HTMLElement;
    expect(within(withAccountRow).getByText("Con cuenta")).toBeInTheDocument();
  });

  it("dispara la búsqueda server-side con debounce al escribir", async () => {
    const user = userEvent.setup();
    renderPage();

    mockUseContactsQuery.mockClear();
    await user.type(screen.getByPlaceholderText("Buscar por nombre..."), "Perez");

    await waitFor(
      () => {
        expect(mockUseContactsQuery).toHaveBeenCalledWith("Perez");
      },
      { timeout: 2000 },
    );
  });

  it("abre el diálogo 'Nuevo contacto' al hacer click en Nuevo", async () => {
    const user = userEvent.setup();
    renderPage();

    await user.click(screen.getByRole("button", { name: /nuevo contacto/i }));

    expect(
      screen.getByRole("heading", { name: "Nuevo contacto" }),
    ).toBeInTheDocument();
    expect(screen.getByLabelText("Nombre")).toHaveValue("");
  });

  it("bloquea el submit y muestra error de validación cuando el email es inválido", async () => {
    const user = userEvent.setup();
    renderPage();

    await user.click(screen.getByRole("button", { name: /nuevo contacto/i }));
    await user.type(screen.getByLabelText("Nombre"), "Test");
    await user.type(screen.getByLabelText("Email"), "no-es-un-email");
    await user.click(screen.getByRole("button", { name: /guardar/i }));

    expect(
      screen.getByText("El email no tiene un formato válido."),
    ).toBeInTheDocument();
    expect(mockCreateMutate).not.toHaveBeenCalled();
  });

  it("crea exitosamente llamando a la mutación con el payload correcto (sin user_id)", async () => {
    const user = userEvent.setup();
    renderPage();

    await user.click(screen.getByRole("button", { name: /nuevo contacto/i }));
    await user.type(screen.getByLabelText("Nombre"), "Nuevo Contacto");
    await user.type(screen.getByLabelText("Email"), "nuevo@urbania.test");
    await user.click(screen.getByRole("button", { name: /guardar/i }));

    expect(mockCreateMutate).toHaveBeenCalledTimes(1);
    expect(mockCreateMutate).toHaveBeenCalledWith(
      {
        nombre: "Nuevo Contacto",
        email: "nuevo@urbania.test",
        telefono: undefined,
      },
      expect.objectContaining({ onSuccess: expect.any(Function) }),
    );
  });

  it("abre el drawer de detalle al hacer click en una fila", async () => {
    const user = userEvent.setup();
    renderPage();

    await user.click(screen.getByText("Ana Gomez"));

    expect(
      await screen.findByText("Detalle del contacto"),
    ).toBeInTheDocument();
    expect(screen.getByText("Sin unidades asociadas.")).toBeInTheDocument();
  });

  it("actualiza llamando a la mutación con id y payload correctos", async () => {
    const user = userEvent.setup();
    renderPage();

    const row = screen.getByText("Ana Gomez").closest("tr") as HTMLElement;
    await user.click(within(row).getByTitle("Editar"));

    const nombreInput = screen.getByLabelText("Nombre");
    await user.clear(nombreInput);
    await user.type(nombreInput, "Ana Gomez Actualizada");
    await user.click(screen.getByRole("button", { name: /guardar/i }));

    expect(mockUpdateMutate).toHaveBeenCalledTimes(1);
    expect(mockUpdateMutate).toHaveBeenCalledWith(
      {
        id: NO_ACCOUNT_ITEM.id,
        data: {
          nombre: "Ana Gomez Actualizada",
          email: "ana@urbania.test",
          telefono: "3001234567",
        },
      },
      expect.objectContaining({ onSuccess: expect.any(Function) }),
    );
  });

  it("abre confirmación de eliminar y llama a la mutación de delete al confirmar", async () => {
    const user = userEvent.setup();
    renderPage();

    const row = screen.getByText("Ana Gomez").closest("tr") as HTMLElement;
    await user.click(within(row).getByTitle("Eliminar"));

    expect(screen.getByText(/¿Estás seguro de eliminar/i)).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: /^eliminar$/i }));

    expect(mockDeleteMutate).toHaveBeenCalledTimes(1);
    expect(mockDeleteMutate).toHaveBeenCalledWith(
      NO_ACCOUNT_ITEM.id,
      expect.objectContaining({
        onSuccess: expect.any(Function),
        onError: expect.any(Function),
      }),
    );
  });

  it("muestra el warning contextual cuando el delete falla con 409 CONTACT_HAS_OCCUPATIONS", async () => {
    const user = userEvent.setup();
    renderPage();

    const row = screen.getByText("Ana Gomez").closest("tr") as HTMLElement;
    await user.click(within(row).getByTitle("Eliminar"));
    await user.click(screen.getByRole("button", { name: /^eliminar$/i }));

    const call = mockDeleteMutate.mock.calls[0] as [
      string,
      { onSuccess: () => void; onError: (error: ApiError) => void },
    ];
    const { onError } = call[1];

    const conflictError: ApiError = {
      code: "CONTACT_HAS_OCCUPATIONS",
      message: "Este contacto tiene unidades asignadas, quítalas primero.",
      trace_id: "trace-409",
    };

    act(() => {
      onError(conflictError);
    });

    await waitFor(() => {
      expect(
        screen.getByText("Este contacto tiene unidades asignadas, quítalas primero."),
      ).toBeInTheDocument();
    });
    expect(screen.getByText(/¿Estás seguro de eliminar/i)).toBeInTheDocument();
  });

  it("muestra estado de carga mientras isLoading es true", () => {
    queryIsLoading = true;
    renderPage();

    expect(screen.queryByText("Ana Gomez")).not.toBeInTheDocument();
  });

  it("muestra el estado vacío cuando no hay contactos", () => {
    queryData = [];
    renderPage();

    expect(screen.getByText("No hay contactos registrados.")).toBeInTheDocument();
  });
});
