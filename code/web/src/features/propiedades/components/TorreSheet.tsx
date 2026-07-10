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
  torreFormSchema,
  type TorreFormValues,
  type TorreItem,
} from "../types";

interface TorreSheetProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  item: TorreItem | null;
  condominioNombre: string;
  isSubmitting: boolean;
  onSubmit: (values: TorreFormValues) => void;
}

/**
 * Sheet compartido para crear/editar torres.
 * Modo crear (item === null): formulario vacío.
 * Modo editar (item !== null): datos precargados.
 */
export function TorreSheet({
  open,
  onOpenChange,
  item,
  condominioNombre,
  isSubmitting,
  onSubmit,
}: TorreSheetProps): ReactNode {
  const isEditing = item !== null;
  const title = isEditing ? "Editar torre" : "Nueva torre";
  const description = isEditing
    ? `Modifica el nombre de la torre "${item.nombre}".`
    : `Crea una nueva torre en "${condominioNombre}".`;

  const form = useForm<TorreFormValues>({
    resolver: zodResolver(torreFormSchema),
    defaultValues: {
      nombre: item?.nombre ?? "",
    },
  });

  function handleSubmit(values: TorreFormValues): void {
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
            <FormField
              control={form.control}
              name="nombre"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Nombre</FormLabel>
                  <FormControl>
                    <Input
                      placeholder='Ej: "Torre A"'
                      disabled={isSubmitting}
                      autoFocus
                      {...field}
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
