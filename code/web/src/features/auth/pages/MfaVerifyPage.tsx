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
import { AuthLayout } from "@/components/auth-layout";
import { useMfaVerifyMutation } from "@/features/auth/api/mfa-verify";
import type { MfaVerifyRequest } from "@/features/auth/types/auth.types";
import { MFA_VERIFY_ERROR_CODES } from "@/features/auth/types/auth.types";
import { Loader2 } from "lucide-react";

const mfaVerifySchema = z.object({
  code: z
    .string()
    .min(1, "El código es obligatorio.")
    .refine(
      (val) =>
        /^\d{6}$/.test(val) ||
        /^[A-Za-z0-9]{5}-[A-Za-z0-9]{5}$/.test(val),
      "Ingresa un código TOTP de 6 dígitos o un código de respaldo (formato XXXXX-XXXXX).",
    ),
});

type MfaVerifyFormValues = z.infer<typeof mfaVerifySchema>;

export function MfaVerifyPage(): React.ReactNode {
  const [tokenExpired, setTokenExpired] = useState(false);
  const [rateLimitedUntil, setRateLimitedUntil] = useState<number | null>(null);

  const form = useForm<MfaVerifyFormValues>({
    resolver: zodResolver(mfaVerifySchema),
    defaultValues: { code: "" },
  });

  const verifyMutation = useMfaVerifyMutation();

  function onSubmit(values: MfaVerifyFormValues): void {
    const dto: MfaVerifyRequest = { code: values.code.toUpperCase() };
    verifyMutation.mutate(dto, {
      onError: (error) => {
        if (error.code === MFA_VERIFY_ERROR_CODES.MFA_TOKEN_INVALID) {
          setTokenExpired(true);
        }

        if (
          error.code === MFA_VERIFY_ERROR_CODES.TOO_MANY_REQUESTS ||
          error.code.startsWith("HTTP_429")
        ) {
          setRateLimitedUntil(Date.now() + 60_000);
          setTimeout(() => setRateLimitedUntil(null), 60_000);
        }

        form.setValue("code", "");
      },
    });
  }

  const isRateLimited =
    rateLimitedUntil !== null && Date.now() < rateLimitedUntil;
  const isSubmitting = verifyMutation.isPending || isRateLimited;

  if (tokenExpired) {
    return (
      <AuthLayout>
        <Card className="w-full">
          <CardHeader className="space-y-1">
            <CardTitle className="text-2xl font-bold text-center">
              Sesión expirada
            </CardTitle>
            <CardDescription className="text-center">
              Tu sesión de verificación expiró. Vuelve a iniciar sesión.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Button asChild className="w-full">
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
            Verificación en dos pasos
          </CardTitle>
          <CardDescription className="text-center">
            Ingresa el código de tu aplicación autenticadora o un código de
            respaldo
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
                name="code"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Código de verificación</FormLabel>
                    <FormControl>
                      <Input
                        type="text"
                        inputMode="numeric"
                        placeholder="000000 o XXXXX-XXXXX"
                        autoComplete="one-time-code"
                        maxLength={11}
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
                    Verificando...
                  </>
                ) : (
                  "Verificar"
                )}
              </Button>
            </form>
          </Form>
        </CardContent>
      </Card>
    </AuthLayout>
  );
}
