import type { ReactNode } from "react";
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
import { Loader2 } from "lucide-react";
import {
  condominioFormSchema,
  type CondominioFormValues,
  type CondominioItem,
} from "../types";

interface CondominioSheetProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  item: CondominioItem | null;
  isSubmitting: boolean;
  onSubmit: (values: CondominioFormValues) => void;
}

/**
 * Sheet compartido para crear/editar condominios.
 * Modo crear (item === null): formulario vacío, título "Nuevo condominio".
 * Modo editar (item !== null): datos precargados, título "Editar condominio".
 */
export function CondominioSheet({
  open,
  onOpenChange,
  item,
  isSubmitting,
  onSubmit,
}: CondominioSheetProps): ReactNode {
  const isEditing = item !== null;
  const title = isEditing ? "Editar condominio" : "Nuevo condominio";
  const description = isEditing
    ? `Modifica los datos de "${item.nombre}".`
    : "Crea un nuevo condominio para tu organización.";

  const form = useForm<CondominioFormValues>({
    resolver: zodResolver(condominioFormSchema),
    defaultValues: {
      nombre: item?.nombre ?? "",
      direccion: item?.direccion ?? "",
      nit: item?.nit ?? "",
    },
  });

  function handleSubmit(values: CondominioFormValues): void {
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
            {/* Nombre */}
            <FormField
              control={form.control}
              name="nombre"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Nombre</FormLabel>
                  <FormControl>
                    <Input
                      placeholder='Ej: "Conjunto El Paraíso"'
                      disabled={isSubmitting}
                      autoFocus
                      {...field}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            {/* Dirección */}
            <FormField
              control={form.control}
              name="direccion"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Dirección (opcional)</FormLabel>
                  <FormControl>
                    <Input
                      placeholder="Ej: Calle 123 #45-67"
                      disabled={isSubmitting}
                      {...field}
                      value={field.value ?? ""}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            {/* NIT */}
            <FormField
              control={form.control}
              name="nit"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>NIT (opcional)</FormLabel>
                  <FormControl>
                    <Input
                      placeholder="Ej: 900123456-7"
                      disabled={isSubmitting}
                      {...field}
                      value={field.value ?? ""}
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
