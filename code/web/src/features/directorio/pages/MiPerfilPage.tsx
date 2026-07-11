import { useEffect } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { PageHeader } from "@/components/page-header";
import { LoadingState } from "@/components/loading-state";
import { PAGE_CONTAINER } from "@/lib/layout";
import { Loader2 } from "lucide-react";
import { contactFormSchema, type ContactFormValues } from "../types";
import { useMeContactQuery, useUpdateMeContactMutation } from "../api/me-contact";

/**
 * Página de autoservicio "Mi perfil" — ruta /perfil.
 * Cualquier usuario autenticado accede a su propio contacto sin permisos
 * especiales (R-DIR-04). Consume LOCK-DIRECTORIO-02: GET/PATCH /me/contact.
 */
export function MiPerfilPage(): React.ReactNode {
  const { data, isLoading } = useMeContactQuery();
  const updateMutation = useUpdateMeContactMutation();
  const contact = data?.data;

  const form = useForm<ContactFormValues>({
    resolver: zodResolver(contactFormSchema),
    defaultValues: { nombre: "", email: "", telefono: "" },
  });

  // Precargar el formulario cuando llega el contacto (la query resuelve async)
  useEffect(() => {
    if (contact) {
      form.reset({
        nombre: contact.nombre,
        email: contact.email,
        telefono: contact.telefono ?? "",
      });
    }
  }, [contact, form]);

  function handleSubmit(values: ContactFormValues): void {
    updateMutation.mutate({
      nombre: values.nombre,
      email: values.email,
      telefono: values.telefono || undefined,
    });
  }

  if (isLoading) {
    return (
      <div className={PAGE_CONTAINER}>
        <PageHeader title="Mi perfil" description="Tus datos de contacto." />
        <LoadingState />
      </div>
    );
  }

  return (
    <div className={PAGE_CONTAINER}>
      <PageHeader title="Mi perfil" description="Tus datos de contacto." />

      <div className="max-w-md">
        <Form {...form}>
          <form
            onSubmit={form.handleSubmit(handleSubmit)}
            className="space-y-4"
            noValidate
          >
            <FormField
              control={form.control}
              name="nombre"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Nombre</FormLabel>
                  <FormControl>
                    <Input disabled={updateMutation.isPending} {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="email"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Email</FormLabel>
                  <FormControl>
                    <Input
                      type="email"
                      disabled={updateMutation.isPending}
                      {...field}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="telefono"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Teléfono (opcional)</FormLabel>
                  <FormControl>
                    <Input
                      disabled={updateMutation.isPending}
                      {...field}
                      value={field.value ?? ""}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <Button type="submit" disabled={updateMutation.isPending}>
              {updateMutation.isPending ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Guardando...
                </>
              ) : (
                "Guardar cambios"
              )}
            </Button>
          </form>
        </Form>
      </div>
    </div>
  );
}
