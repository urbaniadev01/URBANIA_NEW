import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useParams, Link } from "react-router-dom";
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
import { AuthLayout } from "@/components/auth-layout";
import { useRegisterMutation } from "@/features/auth/api/register";
import type { RegisterRequestDto } from "@/features/auth/types/auth.types";
import { Loader2 } from "lucide-react";

/**
 * Esquema Zod para validación del formulario de registro.
 * - name: mínimo 2 caracteres
 * - password: mínimo 8 caracteres, 1 mayúscula, 1 minúscula, 1 número
 * - confirm_password: debe coincidir con password
 * - phone: opcional
 */
const registerSchema = z
  .object({
    name: z
      .string()
      .min(2, "El nombre debe tener al menos 2 caracteres."),
    password: z
      .string()
      .min(8, "La contraseña debe tener al menos 8 caracteres.")
      .regex(/[A-Z]/, "La contraseña debe contener al menos una mayúscula.")
      .regex(/[a-z]/, "La contraseña debe contener al menos una minúscula.")
      .regex(/[0-9]/, "La contraseña debe contener al menos un número."),
    confirm_password: z
      .string()
      .min(1, "Confirma tu contraseña."),
    phone: z.string().optional(),
  })
  .refine((data) => data.password === data.confirm_password, {
    message: "Las contraseñas no coinciden.",
    path: ["confirm_password"],
  });

type RegisterFormValues = z.infer<typeof registerSchema>;

/**
 * Pantalla de registro por invitación — ruta /register/:token.
 *
 * Lee el token de invitación de la URL, formulario (name, password,
 * confirmación, teléfono opcional), consume POST /auth/register (LOCK-AUTH-01).
 *
 * Estados:
 * - Carga: botón deshabilitado + spinner
 * - Error: toast según código de error de la API
 * - Éxito: redirige a /login con mensaje "Cuenta creada, inicia sesión"
 */
export function RegisterPage(): React.ReactNode {
  const { token } = useParams<{ token: string }>();

  const form = useForm<RegisterFormValues>({
    resolver: zodResolver(registerSchema),
    defaultValues: {
      name: "",
      password: "",
      confirm_password: "",
      phone: "",
    },
  });

  const registerMutation = useRegisterMutation();

  function onSubmit(values: RegisterFormValues): void {
    if (!token) {
      return;
    }
    const dto: RegisterRequestDto = {
      invitation_token: token,
      name: values.name,
      password: values.password,
      phone: values.phone || undefined,
    };
    registerMutation.mutate(dto);
  }

  const isSubmitting = registerMutation.isPending;

  if (!token) {
    return (
      <AuthLayout>
        <Card className="w-full">
          <CardHeader className="space-y-1">
            <CardTitle className="text-2xl font-bold text-center">
              Enlace inválido
            </CardTitle>
            <CardDescription className="text-center">
              No se encontró un token de invitación en el enlace.
            </CardDescription>
          </CardHeader>
          <CardContent className="text-center">
            <Button asChild variant="outline">
              <Link to="/login">Ir al inicio de sesión</Link>
            </Button>
          </CardContent>
        </Card>
      </AuthLayout>
    );
  }

  return (
    <AuthLayout>
      <Card className="w-full">
        <CardHeader className="space-y-1">
          <CardTitle className="text-2xl font-bold text-center">
            Crear cuenta
          </CardTitle>
          <CardDescription className="text-center">
            Completa tus datos para finalizar el registro
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Form {...form}>
            <form
              onSubmit={form.handleSubmit(onSubmit)}
              className="space-y-4"
              noValidate
            >
              {/* Nombre */}
              <FormField
                control={form.control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Nombre completo</FormLabel>
                    <FormControl>
                      <Input
                        type="text"
                        placeholder="Tu nombre"
                        autoComplete="name"
                        disabled={isSubmitting}
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              {/* Contraseña */}
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
                        autoComplete="new-password"
                        disabled={isSubmitting}
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              {/* Confirmar contraseña */}
              <FormField
                control={form.control}
                name="confirm_password"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Confirmar contraseña</FormLabel>
                    <FormControl>
                      <Input
                        type="password"
                        placeholder="••••••••"
                        autoComplete="new-password"
                        disabled={isSubmitting}
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              {/* Teléfono (opcional) */}
              <FormField
                control={form.control}
                name="phone"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Teléfono (opcional)</FormLabel>
                    <FormControl>
                      <Input
                        type="tel"
                        placeholder="+51 999 888 777"
                        autoComplete="tel"
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
                    Creando cuenta...
                  </>
                ) : (
                  "Crear cuenta"
                )}
              </Button>
            </form>
          </Form>

          <p className="mt-4 text-center text-sm text-muted-foreground">
            ¿Ya tienes cuenta?{" "}
            <Link
              to="/login"
              className="font-medium text-primary underline underline-offset-4"
            >
              Inicia sesión
            </Link>
          </p>
        </CardContent>
      </Card>
    </AuthLayout>
  );
}
