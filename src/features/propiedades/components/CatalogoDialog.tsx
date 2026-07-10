import type { ReactNode } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
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
import { catalogoFormSchema, type CatalogoFormValues, type CatalogoItem } from "../types";

interface CatalogoDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  item: CatalogoItem | null;
  isSubmitting: boolean;
  onSubmit: (values: CatalogoFormValues) => void;
  entityName: string;
}

/**
 * Diálogo compartido para crear/editar catálogos (tipos y estados de propiedad).
 * Modo crear (item === null): formulario vacío, título "Nuevo <entity>".
 * Modo editar (item !== null): datos precargados, título "Editar <entity>".
 */
export function CatalogoDialog({
  open,
  onOpenChange,
  item,
  isSubmitting,
  onSubmit,
  entityName,
}: CatalogoDialogProps): ReactNode {
  const isEditing = item !== null;
  const title = isEditing ? `Editar ${entityName}` : `Nuevo ${entityName}`;
  const description = isEditing
    ? `Modifica los datos de "${item.nombre}".`
    : `Crea un nuevo ${entityName.toLowerCase()} para tu organización.`;

  const form = useForm<CatalogoFormValues>({
    resolver: zodResolver(catalogoFormSchema),
    defaultValues: {
      nombre: item?.nombre ?? "",
      descripcion: item?.descripcion ?? "",
    },
  });

  // Resetear valores cuando cambia el item (modo crear/editar)
  // Usamos key en el Dialog para forzar remount, así evitamos este efecto.
  // Alternativa: manejar con useEffect

  function handleSubmit(values: CatalogoFormValues): void {
    onSubmit(values);
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[480px]">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>{description}</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form
            onSubmit={form.handleSubmit(handleSubmit)}
            className="space-y-4"
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
                      placeholder={`Ej: ${isEditing ? item.nombre : "Oficina"}`}
                      disabled={isSubmitting}
                      autoFocus
                      {...field}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            {/* Descripción */}
            <FormField
              control={form.control}
              name="descripcion"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Descripción (opcional)</FormLabel>
                  <FormControl>
                    <Input
                      placeholder="Descripción breve"
                      disabled={isSubmitting}
                      {...field}
                      value={field.value ?? ""}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <DialogFooter>
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
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
