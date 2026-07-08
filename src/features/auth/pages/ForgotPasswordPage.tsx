import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { Link } from "react-router-dom";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Input } from "@/components/ui/input";
import { useForgotPasswordMutation } from "@/features/auth/api/forgot-password";
import type { ForgotPasswordRequest } from "@/features/auth/types/auth.types";
import { Loader2 } from "lucide-react";

/**
 * Esquema Zod para validación del formulario de recuperación de contraseña.
 * email: string requerido, formato email válido.
 */
const forgotPasswordSchema = z.object({
  email: z
    .string()
    .min(1, "El email es obligatorio.")
    .email("Ingresa un email válido."),
});

type ForgotPasswordFormValues = z.infer<typeof forgotPasswordSchema>;

/**
 * Pantalla de recuperación de contraseña — ruta /forgot-password.
 * Formulario de un solo campo (email) → POST /auth/forgot-password (LOCK-AUTH-09).
 *
 * Estados:
 * - Inicial: formulario email + enlace "Volver a inicio de sesión"
 * - Carga: botón deshabilitado + spinner
 * - Éxito (200): formulario reemplazado por mensaje genérico + enlace a /login
 * - Error 429: toast "Demasiadas solicitudes..."
 *
 * La API no distingue entre email existente/no existente — la UI replica ese
 * comportamiento con un único mensaje para todos los casos.
 */
export function ForgotPasswordPage(): React.ReactNode {
  const [sent, setSent] = useState(false);

  const form = useForm<ForgotPasswordFormValues>({
    resolver: zodResolver(forgotPasswordSchema),
    defaultValues: {
      email: "",
    },
  });

  const forgotPasswordMutation = useForgotPasswordMutation();

  function onSubmit(values: ForgotPasswordFormValues): void {
    const dto: ForgotPasswordRequest = {
      email: values.email,
    };
    forgotPasswordMutation.mutate(dto, {
      onSuccess: () => {
        setSent(true);
      },
    });
  }

  const isSubmitting = forgotPasswordMutation.isPending;

  // Estado éxito: formulario reemplazado por mensaje genérico
  if (sent) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-muted/50 px-4">
        <Card className="w-full max-w-md">
          <CardHeader className="space-y-1">
            <CardTitle className="text-2xl font-bold text-center">
              Urbania
            </CardTitle>
            <CardDescription className="text-center">
              Revisa tu correo electrónico
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <p className="text-sm text-muted-foreground text-center">
              Si el email está registrado, recibirás un enlace de recuperación.
              Revisa tu bandeja de entrada y spam.
            </p>
            <div className="text-center">
              <Link
                to="/login"
                className="text-sm text-primary underline underline-offset-4 hover:text-primary/80"
              >
                Volver a inicio de sesión
              </Link>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  // Estado inicial / carga
  return (
    <div className="flex min-h-screen items-center justify-center bg-muted/50 px-4">
      <Card className="w-full max-w-md">
        <CardHeader className="space-y-1">
          <CardTitle className="text-2xl font-bold text-center">
            Urbania
          </CardTitle>
          <CardDescription className="text-center">
            Ingresa tu email para recuperar tu contraseña
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Form {...form}>
            <form
              onSubmit={form.handleSubmit(onSubmit)}
              className="space-y-4"
              noValidate
            >
              <FormField
                control={form.control}
                name="email"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Email</FormLabel>
                    <FormControl>
                      <Input
                        type="email"
                        placeholder="tu@email.com"
                        autoComplete="email"
                        disabled={isSubmitting}
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <Button type="submit" className="w-full" disabled={isSubmitting}>
                {isSubmitting ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Enviando...
                  </>
                ) : (
                  "Enviar enlace de recuperación"
                )}
              </Button>
            </form>
          </Form>

          <div className="mt-4 text-center">
            <Link
              to="/login"
              className="text-sm text-primary underline underline-offset-4 hover:text-primary/80"
            >
              Volver a inicio de sesión
            </Link>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
