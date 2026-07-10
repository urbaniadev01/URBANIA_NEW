import { type ReactNode, useMemo } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { Button } from "@/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Loader2 } from "lucide-react";
import {
  unidadFormSchema,
  type UnidadFormValues,
  type PropertyDetail,
  type CatalogoItem,
  type TorreItem,
} from "../types";

interface UnidadSheetProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** null = crear, PropertyDetail = editar */
  item: PropertyDetail | null;
  towers: TorreItem[];
  tipos: CatalogoItem[];
  estados: CatalogoItem[];
  isSubmitting: boolean;
  onSubmit: (values: UnidadFormValues) => void;
}

/**
 * Sheet compartido para crear/editar unidades.
 * Modo crear (item === null): formulario vacío.
 * Modo editar (item !== null): datos precargados.
 */
export function UnidadSheet({
  open,
  onOpenChange,
  item,
  towers,
  tipos,
  estados,
  isSubmitting,
  onSubmit,
}: UnidadSheetProps): ReactNode {
  const isEditing = item !== null;
  const title = isEditing ? "Editar unidad" : "Nueva unidad";
  const description = isEditing
    ? `Modifica los datos de la unidad "${item.codigo}".`
    : "Crea una nueva unidad en este condominio.";

  const towerOptions = useMemo(
    () => [
      { value: "", label: "Sin torre" },
      ...towers.map((t) => ({ value: t.id, label: t.nombre })),
    ],
    [towers],
  );

  const tipoOptions = useMemo(
    () => tipos.map((t) => ({ value: t.id, label: t.nombre })),
    [tipos],
  );

  const estadoOptions = useMemo(
    () => estados.map((e) => ({ value: e.id, label: e.nombre })),
    [estados],
  );

  const form = useForm<UnidadFormValues>({
    resolver: zodResolver(unidadFormSchema),
    defaultValues: {
      codigo: item?.codigo ?? "",
      tower_id: item?.tower_id ?? "",
      property_type_id: item?.property_type_id ?? "",
      property_status_id: item?.property_status_id ?? "",
      piso: item?.piso ?? null,
      area_m2: item?.area_m2 ?? null,
    },
  });

  function handleSubmit(values: UnidadFormValues): void {
    onSubmit(values);
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="sm:max-w-md">
        <SheetHeader>
          <SheetTitle>{title}</SheetTitle>
          <SheetDescription>{description}</SheetDescription>
        </SheetHeader>
        <Form {...form}>
          <form
            onSubmit={form.handleSubmit(handleSubmit)}
            className="mt-6 space-y-4"
            noValidate
          >
            {/* Código */}
            <FormField
              control={form.control}
              name="codigo"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Código</FormLabel>
                  <FormControl>
                    <Input
                      placeholder='Ej: "A-101"'
                      disabled={isSubmitting}
                      autoFocus
                      {...field}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            {/* Torre */}
            <FormField
              control={form.control}
              name="tower_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Torre</FormLabel>
                  <FormControl>
                    <Select
                      options={towerOptions}
                      placeholder="Sin torre"
                      disabled={isSubmitting}
                      {...field}
                      value={field.value ?? ""}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            {/* Tipo */}
            <FormField
              control={form.control}
              name="property_type_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Tipo de propiedad</FormLabel>
                  <FormControl>
                    <Select
                      options={tipoOptions}
                      placeholder="Seleccionar tipo..."
                      disabled={isSubmitting}
                      {...field}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            {/* Estado */}
            <FormField
              control={form.control}
              name="property_status_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Estado</FormLabel>
                  <FormControl>
                    <Select
                      options={estadoOptions}
                      placeholder="Seleccionar estado..."
                      disabled={isSubmitting}
                      {...field}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            {/* Piso */}
            <FormField
              control={form.control}
              name="piso"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Piso (opcional)</FormLabel>
                  <FormControl>
                    <Input
                      type="number"
                      placeholder="Ej: 1"
                      disabled={isSubmitting}
                      {...field}
                      value={field.value ?? ""}
                      onChange={(e) => {
                        const val = e.target.value;
                        field.onChange(val === "" ? null : Number(val));
                      }}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            {/* Área */}
            <FormField
              control={form.control}
              name="area_m2"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Área (m²) (opcional)</FormLabel>
                  <FormControl>
                    <Input
                      type="number"
                      step="0.01"
                      placeholder="Ej: 75.5"
                      disabled={isSubmitting}
                      {...field}
                      value={field.value ?? ""}
                      onChange={(e) => {
                        const val = e.target.value;
                        field.onChange(val === "" ? null : Number(val));
                      }}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <SheetFooter className="pt-4">
              <Button
                type="button"
                variant="outline"
                onClick={() => onOpenChange(false)}
                disabled={isSubmitting}
              >
                Cancelar
              </Button>
              <Button type="submit" disabled={isSubmitting}>
                {isSubmitting ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Guardando...
                  </>
                ) : (
                  "Guardar"
                )}
              </Button>
            </SheetFooter>
          </form>
        </Form>
      </SheetContent>
    </Sheet>
  );
}
