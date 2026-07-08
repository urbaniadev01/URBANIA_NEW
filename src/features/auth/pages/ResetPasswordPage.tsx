import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useSearchParams, Link } from "react-router-dom";
import { z } from "zod";
import { Check, X, Loader2 } from "lucide-react";
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
import { useResetPasswordMutation } from "@/features/auth/api/reset-password";
import {
  passwordSchema,
  type ResetPasswordRequest,
} from "@/features/auth/types/auth.types";

/**
 * Esquema Zod para formulario de reset de contraseña.
 * Reutiliza passwordSchema compartido + .refine() para confirmación.
 */
const resetPasswordSchema = z
  .object({
    password: passwordSchema,
    password_confirmation: z
      .string()
      .min(1, "La confirmación es obligatoria."),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: "Las contraseñas no coinciden.",
    path: ["password_confirmation"],
  });

type ResetPasswordFormValues = z.infer<typeof resetPasswordSchema>;

/**
 * Pantalla de nueva contraseña — ruta /reset-password?token=...&email=...
 *
 * Lee token (64 chars hex) y email de query params. Si faltan → error card.
 * Formulario: nueva contraseña + confirmación con checklist en tiempo real.
 * Consume POST /auth/reset-password (LOCK-AUTH-09).
 *
 * Estados:
 * - Params ausentes: "Enlace inválido o incompleto" + enlace /forgot-password
 * - Formulario activo: checklist reactiva mientras se escribe
 * - Carga: botón deshabilitado + spinner
 * - Éxito (200): redirige a /login con toast
 * - Error RESET_TOKEN_INVALID / RESET_TOKEN_EXPIRED: reemplaza formulario
 *   por mensaje + enlace /forgot-password
 * - Error TOO_MANY_REQUESTS (429): toast
 * - Error VALIDATION_ERROR (422): toast con mensaje del servidor
 */
export function ResetPasswordPage(): React.ReactNode {
  const [searchParams] = useSearchParams();
  const token = searchParams.get("token");
  const email = searchParams.get("email");

  const form = useForm<ResetPasswordFormValues>({
    resolver: zodResolver(resetPasswordSchema),
    defaultValues: {
      password: "",
      password_confirmation: "",
    },
  });

  const { mutate, isPending, fatalError } = useResetPasswordMutation();

  // watch para checklist reactiva
  const password = form.watch("password");

  const checks = {
    minLength: password.length >= 8,
    hasUpper: /[A-Z]/.test(password),
    hasLower: /[a-z]/.test(password),
    hasNumber: /[0-9]/.test(password),
  };

  function onSubmit(values: ResetPasswordFormValues): void {
    if (!token) return;
    const dto: ResetPasswordRequest = {
      token,
      password: values.password,
      password_confirmation: values.password_confirmation,
    };
    mutate(dto);
  }

  // ── Error fatal de la API: token inválido o expirado ──
  if (fatalError) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-muted/50 px-4">
        <Card className="w-full max-w-md">
          <CardHeader className="space-y-1">
            <CardTitle className="text-2xl font-bold text-center">
              Enlace inválido
            </CardTitle>
            <CardDescription className="text-center">
              {fatalError}
            </CardDescription>
          </CardHeader>
          <CardContent className="text-center">
            <Button asChild variant="outline">
              <Link to="/forgot-password">
                Solicitar un nuevo enlace
              </Link>
            </Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  // ── Faltan params en la URL ──
  if (!token || !email) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-muted/50 px-4">
        <Card className="w-full max-w-md">
          <CardHeader className="space-y-1">
            <CardTitle className="text-2xl font-bold text-center">
              Enlace inválido
            </CardTitle>
            <CardDescription className="text-center">
              Enlace inválido o incompleto. Solicita un nuevo enlace de
              recuperación.
            </CardDescription>
          </CardHeader>
          <CardContent className="text-center">
            <Button asChild variant="outline">
              <Link to="/forgot-password">
                Ir a recuperación de contraseña
              </Link>
            </Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  // ── Formulario normal ──
  return (
    <div className="flex min-h-screen items-center justify-center bg-muted/50 px-4">
      <Card className="w-full max-w-md">
        <CardHeader className="space-y-1">
          <CardTitle className="text-2xl font-bold text-center">
            Nueva contraseña
          </CardTitle>
          <CardDescription className="text-center">
            Restableciendo contraseña para {email}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Form {...form}>
            <form
              onSubmit={form.handleSubmit(onSubmit)}
              className="space-y-4"
              noValidate
            >
              {/* Nueva contraseña */}
              <FormField
                control={form.control}
                name="password"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Nueva contraseña</FormLabel>
                    <FormControl>
                      <Input
                        type="password"
                        placeholder="••••••••"
                        autoComplete="new-password"
                        disabled={isPending}
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                    {/* Checklist en tiempo real — 4 requisitos */}
                    <div className="mt-2 space-y-1 text-sm">
                      <CheckItem
                        met={checks.minLength}
                        label="Al menos 8 caracteres"
                      />
                      <CheckItem
                        met={checks.hasUpper}
                        label="Al menos una mayúscula"
                      />
                      <CheckItem
                        met={checks.hasLower}
                        label="Al menos una minúscula"
                      />
                      <CheckItem
                        met={checks.hasNumber}
                        label="Al menos un número"
                      />
                    </div>
                  </FormItem>
                )}
              />

              {/* Confirmar contraseña */}
              <FormField
                control={form.control}
                name="password_confirmation"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Confirmar contraseña</FormLabel>
                    <FormControl>
                      <Input
                        type="password"
                        placeholder="••••••••"
                        autoComplete="new-password"
                        disabled={isPending}
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <Button type="submit" className="w-full" disabled={isPending}>
                {isPending ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Actualizando contraseña...
                  </>
                ) : (
                  "Actualizar contraseña"
                )}
              </Button>
            </form>
          </Form>

          <p className="mt-4 text-center text-sm text-muted-foreground">
            <Link
              to="/login"
              className="font-medium text-primary underline underline-offset-4"
            >
              Volver a inicio de sesión
            </Link>
          </p>
        </CardContent>
      </Card>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════════════
// Componente auxiliar
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Ítem individual de la checklist de requisitos de contraseña.
 * Muestra check verde si se cumple, X roja si no.
 */
function CheckItem({
  met,
  label,
}: {
  met: boolean;
  label: string;
}): React.ReactNode {
  return (
    <div className="flex items-center gap-2">
      {met ? (
        <Check className="h-4 w-4 text-green-500" aria-label="Cumple" />
      ) : (
        <X className="h-4 w-4 text-red-500" aria-label="No cumple" />
      )}
      <span className={met ? "text-green-600" : "text-muted-foreground"}>
        {label}
      </span>
    </div>
  );
}
