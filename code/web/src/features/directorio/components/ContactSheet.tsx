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
import { contactFormSchema, type ContactFormValues, type ContactItem } from "../types";

interface ContactSheetProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  item: ContactItem | null;
  isSubmitting: boolean;
  onSubmit: (values: ContactFormValues) => void;
}

/**
 * Sheet para crear/editar contactos. Nunca incluye un campo user_id — un
 * contacto con cuenta de usuario solo se crea vía el flujo de invitación de
 * AUTH (ver DIRECTORIO-B03).
 * Modo crear (item === null): formulario vacío, título "Nuevo contacto".
 * Modo editar (item !== null): datos precargados, título "Editar contacto".
 */
export function ContactSheet({
  open,
  onOpenChange,
  item,
  isSubmitting,
  onSubmit,
}: ContactSheetProps): ReactNode {
  const isEditing = item !== null;
  const title = isEditing ? "Editar contacto" : "Nuevo contacto";
  const description = isEditing
    ? `Modifica los datos de "${item.nombre}".`
    : "Crea un contacto sin cuenta de usuario para tu organización.";

  const form = useForm<ContactFormValues>({
    resolver: zodResolver(contactFormSchema),
    defaultValues: {
      nombre: item?.nombre ?? "",
      email: item?.email ?? "",
      telefono: item?.telefono ?? "",
    },
  });

  function handleSubmit(values: ContactFormValues): void {
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
                      placeholder="Ej: Juan Pérez"
                      disabled={isSubmitting}
                      autoFocus
                      {...field}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            {/* Email */}
            <FormField
              control={form.control}
              name="email"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Email</FormLabel>
                  <FormControl>
                    <Input
                      type="email"
                      placeholder="juan@correo.com"
                      disabled={isSubmitting}
                      {...field}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            {/* Teléfono */}
            <FormField
              control={form.control}
              name="telefono"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Teléfono (opcional)</FormLabel>
                  <FormControl>
                    <Input
                      placeholder="3001234567"
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
