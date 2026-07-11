import { type ReactNode, useState, useCallback, useRef, useEffect } from "react";
import { Link } from "react-router-dom";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Loader2, Search, X } from "lucide-react";
import { useContactsQuery } from "../api/contacts";
import { useOccupantTypesQuery } from "../api/occupant-types";
import type { ContactItem } from "../types";

interface AssignOccupantDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  isSubmitting: boolean;
  onSubmit: (values: {
    contact_id: string;
    occupant_type_id: string;
    es_principal: boolean;
  }) => void;
}

/**
 * Diálogo "Asignar ocupante": busca un contacto existente (GET /contacts?search=,
 * LOCK-DIRECTORIO-02 read-only), selecciona un tipo de ocupante (GET /occupant-types,
 * LOCK-DIRECTORIO-01 read-only) y opcionalmente lo marca como principal.
 * Si la búsqueda no encuentra resultados, ofrece un enlace a crear el contacto
 * en /directorio/contactos (CA 8) — sin salir del flujo actual no es viable sin
 * un combobox más complejo; se documenta la decisión en la ficha de pantalla.
 */
export function AssignOccupantDialog({
  open,
  onOpenChange,
  isSubmitting,
  onSubmit,
}: AssignOccupantDialogProps): ReactNode {
  const [searchInput, setSearchInput] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const [selectedContact, setSelectedContact] = useState<ContactItem | null>(null);
  const [occupantTypeId, setOccupantTypeId] = useState("");
  const [esPrincipal, setEsPrincipal] = useState(false);

  const handleSearchChange = useCallback((value: string) => {
    setSearchInput(value);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      setDebouncedSearch(value);
    }, 300);
  }, []);

  useEffect(() => {
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, []);

  const { data: contactsData, isLoading: contactsLoading } = useContactsQuery(
    selectedContact ? "" : debouncedSearch,
  );
  const contacts = selectedContact ? [] : (contactsData?.data ?? []);

  const { data: occupantTypes = [] } = useOccupantTypesQuery();
  const occupantTypeOptions = occupantTypes.map((t) => ({
    value: t.id,
    label: t.nombre,
  }));

  const resetForm = useCallback(() => {
    setSearchInput("");
    setDebouncedSearch("");
    setSelectedContact(null);
    setOccupantTypeId("");
    setEsPrincipal(false);
  }, []);

  const handleOpenChange = useCallback(
    (nextOpen: boolean) => {
      if (!nextOpen) resetForm();
      onOpenChange(nextOpen);
    },
    [onOpenChange, resetForm],
  );

  const handleSubmit = useCallback(() => {
    if (!selectedContact || !occupantTypeId) return;
    onSubmit({
      contact_id: selectedContact.id,
      occupant_type_id: occupantTypeId,
      es_principal: esPrincipal,
    });
  }, [selectedContact, occupantTypeId, esPrincipal, onSubmit]);

  const canSubmit = selectedContact !== null && occupantTypeId !== "";
  const showNoResults =
    !selectedContact && debouncedSearch !== "" && !contactsLoading && contacts.length === 0;

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="sm:max-w-[480px]">
        <DialogHeader>
          <DialogTitle>Asignar ocupante</DialogTitle>
          <DialogDescription>
            Busca un contacto existente y elige su tipo de ocupante en esta unidad.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {/* Búsqueda / selección de contacto */}
          <div className="space-y-2">
            <Label htmlFor="assign-contact-search">Contacto</Label>
            {selectedContact ? (
              <div className="flex items-center justify-between rounded-md border px-3 py-2">
                <span className="text-sm font-medium">{selectedContact.nombre}</span>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  onClick={() => setSelectedContact(null)}
                  disabled={isSubmitting}
                  title="Cambiar contacto"
                >
                  <X className="h-4 w-4" />
                  <span className="sr-only">Cambiar contacto</span>
                </Button>
              </div>
            ) : (
              <>
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                  <Input
                    id="assign-contact-search"
                    placeholder="Buscar contacto por nombre..."
                    className="pl-9"
                    value={searchInput}
                    onChange={(e) => handleSearchChange(e.target.value)}
                    disabled={isSubmitting}
                  />
                </div>
                {debouncedSearch !== "" ? (
                  showNoResults ? (
                    <div className="rounded-md border border-dashed px-3 py-3 text-sm text-muted-foreground">
                      Sin resultados.{" "}
                      <Link
                        to="/directorio/contactos"
                        className="font-medium text-primary underline-offset-4 hover:underline"
                      >
                        Crear contacto nuevo
                      </Link>
                    </div>
                  ) : (
                    <ul className="max-h-[180px] overflow-y-auto rounded-md border">
                      {contacts.map((contact) => (
                        <li key={contact.id}>
                          <button
                            type="button"
                            className="w-full px-3 py-2 text-left text-sm hover:bg-accent"
                            onClick={() => setSelectedContact(contact)}
                          >
                            {contact.nombre}
                          </button>
                        </li>
                      ))}
                    </ul>
                  )
                ) : null}
              </>
            )}
          </div>

          {/* Tipo de ocupante */}
          <div className="space-y-2">
            <Label htmlFor="assign-occupant-type">Tipo de ocupante</Label>
            <Select
              id="assign-occupant-type"
              options={occupantTypeOptions}
              placeholder="Seleccionar tipo..."
              value={occupantTypeId}
              onChange={(e) => setOccupantTypeId(e.target.value)}
              disabled={isSubmitting}
            />
          </div>

          {/* Principal */}
          <div className="flex items-center gap-2">
            <Checkbox
              id="assign-es-principal"
              checked={esPrincipal}
              onChange={() => setEsPrincipal((v) => !v)}
              disabled={isSubmitting}
            />
            <Label htmlFor="assign-es-principal" className="font-normal">
              Marcar como ocupante principal
            </Label>
          </div>
        </div>

        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            onClick={() => handleOpenChange(false)}
            disabled={isSubmitting}
          >
            Cancelar
          </Button>
          <Button type="button" onClick={handleSubmit} disabled={!canSubmit || isSubmitting}>
            {isSubmitting ? (
              <>
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                Asignando...
              </>
            ) : (
              "Asignar"
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
