import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
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
import { useLoginMutation } from "@/features/auth/api/login";
import type { LoginRequestDto } from "@/features/auth/types/auth.types";
import { Loader2 } from "lucide-react";

/**
 * Esquema Zod para validación del formulario de login.
 * email: string requerido, formato email válido.
 * password: string requerido, mínimo 1 carácter.
 */
const loginSchema = z.object({
  email: z
    .string()
    .min(1, "El email es obligatorio.")
    .email("Ingresa un email válido."),
  password: z.string().min(1, "La contraseña es obligatoria."),
});

type LoginFormValues = z.infer<typeof loginSchema>;

/**
 * Pantalla de login — ruta /login.
 * Formulario email + password → POST /auth/login (LOCK-AUTH-02).
 *
 * Estados:
 * - Carga: botón deshabilitado + spinner
 * - Error: toast según código de error de la API
 * - Éxito: redirige a /dashboard, token en Zustand (memoria)
 */
export function LoginPage(): React.ReactNode {
  const form = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: {
      email: "",
      password: "",
    },
  });

  const loginMutation = useLoginMutation();

  function onSubmit(values: LoginFormValues): void {
    const dto: LoginRequestDto = {
      email: values.email,
      password: values.password,
    };
    loginMutation.mutate(dto);
  }

  const isSubmitting = loginMutation.isPending;

  return (
    <div className="flex min-h-screen items-center justify-center bg-muted/50 px-4">
      <Card className="w-full max-w-md">
        <CardHeader className="space-y-1">
          <CardTitle className="text-2xl font-bold text-center">
            Urbania
          </CardTitle>
          <CardDescription className="text-center">
            Ingresa tus credenciales para acceder al panel
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Form {...form}>
            <form
              onSubmit={form.handleSubmit(onSubmit)}
              className="space-y-4"
              noValidate
            >
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

              {/* Password */}
              <FormField
                control={form.control}
                name="password"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Contraseña</FormLabel>
                    <FormControl>
                      <Input
                        type="password"
                        placeholder="••••••••"
                        autoComplete="current-password"
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
                    Iniciando sesión...
                  </>
                ) : (
                  "Iniciar sesión"
                )}
              </Button>
            </form>
          </Form>
        </CardContent>
      </Card>
    </div>
  );
}
