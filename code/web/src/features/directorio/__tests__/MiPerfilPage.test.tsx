import { describe, it, expect, vi, beforeEach } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter } from "react-router-dom";
import { MiPerfilPage } from "@/features/directorio/pages/MiPerfilPage";
import type { ContactItem } from "@/features/directorio/types";

const mockUpdateMutate = vi.fn();

let queryData: ContactItem | undefined;
let queryIsLoading = false;
let updateIsPending = false;

vi.mock("@/features/directorio/api/me-contact", () => ({
  useMeContactQuery: () => ({
    data: queryData ? { data: queryData } : undefined,
    isLoading: queryIsLoading,
  }),
  useUpdateMeContactMutation: () => ({
    mutate: mockUpdateMutate,
    isPending: updateIsPending,
  }),
}));

const CONTACT: ContactItem = {
  id: "11111111-1111-1111-1111-111111111111",
  organization_id: "org-1",
  user_id: "user-1",
  nombre: "Mi Nombre",
  email: "mi-email@urbania.test",
  telefono: "3009998877",
  created_by: null,
  updated_by: null,
  created_at: "2026-01-01T00:00:00Z",
  updated_at: "2026-01-01T00:00:00Z",
};

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <MiPerfilPage />
      </BrowserRouter>
    </QueryClientProvider>,
  );
}

describe("MiPerfilPage", () => {
  beforeEach(() => {
    mockUpdateMutate.mockClear();
    queryData = CONTACT;
    queryIsLoading = false;
    updateIsPending = false;
  });

  it("precarga el formulario con el propio contacto", async () => {
    renderPage();

    expect(await screen.findByDisplayValue("Mi Nombre")).toBeInTheDocument();
    expect(screen.getByDisplayValue("mi-email@urbania.test")).toBeInTheDocument();
    expect(screen.getByDisplayValue("3009998877")).toBeInTheDocument();
  });

  it("llama a la mutación con el payload actualizado al guardar", async () => {
    const user = userEvent.setup();
    renderPage();

    await screen.findByDisplayValue("Mi Nombre");

    const telefonoInput = screen.getByLabelText("Teléfono (opcional)");
    await user.clear(telefonoInput);
    await user.type(telefonoInput, "3005556677");
    await user.click(screen.getByRole("button", { name: /guardar cambios/i }));

    expect(mockUpdateMutate).toHaveBeenCalledTimes(1);
    expect(mockUpdateMutate).toHaveBeenCalledWith({
      nombre: "Mi Nombre",
      email: "mi-email@urbania.test",
      telefono: "3005556677",
    });
  });

  it("muestra estado de carga mientras isLoading es true", () => {
    queryIsLoading = true;
    renderPage();

    expect(screen.queryByLabelText("Nombre")).not.toBeInTheDocument();
  });
});
