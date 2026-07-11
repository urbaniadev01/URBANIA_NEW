import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { Button } from "@/components/ui/button";
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
    <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-primary">
      {/* Fondo — skyline de marca a opacidad plena, capas fijas de paralax */}
      <div
        className="absolute inset-0 bg-cover bg-center"
        style={{ backgroundImage: "url('/background.png')" }}
      />

      {/* Rectángulo central — 60% del viewport, logo + formulario */}
      <div className="relative grid h-[60vh] w-[70vw] min-h-[520px] min-w-[720px] grid-cols-2 overflow-hidden rounded-2xl border border-surface-glass-border/20 bg-surface-glass/20 shadow-2xl backdrop-blur-[4px]">
        {/* Logo */}
        <div className="flex items-center justify-center border-r border-surface-glass-border/10 p-6">
          <img
            src="/logo.png"
            alt="Urbania"
            className="max-h-full max-w-full scale-125 object-contain"
          />
        </div>

        {/* Formulario */}
        <div className="flex flex-col justify-center gap-6 px-10 py-8 text-surface-glass-foreground">
          <div className="space-y-1">
            <h1 className="font-display text-2xl font-bold tracking-tight [text-shadow:-4px_3px_2px_rgb(0_0_0_/_0.3)]">
              Iniciar sesión
            </h1>
            <p className="text-sm text-surface-glass-foreground/70">
              Ingresa tus credenciales para acceder al panel
            </p>
          </div>

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
                        className="border-input-accent-border/40 bg-input-accent-bg/20 text-foreground placeholder:text-foreground/50"
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
                        className="border-input-accent-border/40 bg-input-accent-bg/20 text-foreground placeholder:text-foreground/50"
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <Button
                type="submit"
                className="w-full bg-brand-cta text-brand-cta-foreground transition-all duration-150 hover:bg-brand-cta-hover hover:shadow-md hover:shadow-brand-cta/40 active:scale-95 active:bg-brand-cta-active"
                disabled={isSubmitting}
              >
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
        </div>
      </div>
    </div>
  );
}
