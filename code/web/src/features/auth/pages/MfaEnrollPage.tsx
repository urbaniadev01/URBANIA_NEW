import { useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { toast } from "sonner";
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
import {
  useMfaEnrollMutation,
  useMfaConfirmMutation,
  useMfaDisableMutation,
  useMfaRecoveryMutation,
} from "@/features/auth/api/mfa-enroll";
import { RecoveryCodes } from "@/features/auth/components/RecoveryCodes";
import type {
  MfaConfirmRequest,
  MfaDisableRequest,
  MfaRecoveryRequest,
} from "@/features/auth/types/auth.types";
import { MFA_ENROLL_ERROR_CODES } from "@/features/auth/types/auth.types";
import { Loader2, ShieldCheck } from "lucide-react";

const totpSchema = z.object({
  code: z
    .string()
    .min(1, "El código es obligatorio.")
    .length(6, "Ingresa un código de 6 dígitos.")
    .regex(/^\d{6}$/, "Ingresa un código de 6 dígitos."),
});

type TotpFormValues = z.infer<typeof totpSchema>;

type Step = "idle" | "enrolling" | "active";
type ActiveSection = "panel" | "recovery-view";

export function MfaEnrollPage(): React.ReactNode {
  return <MfaEnrollContent />;
}

function MfaEnrollContent(): React.ReactNode {
  const [step, setStep] = useState<Step>("idle");
  const [qrCode, setQrCode] = useState<string | null>(null);
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
  const [regeneratedCodes, setRegeneratedCodes] = useState<string[]>([]);
  const [activeSection, setActiveSection] = useState<ActiveSection>("panel");
  const [attemptsRemaining, setAttemptsRemaining] = useState(5);
  const [confirmMessage, setConfirmMessage] = useState<string | null>(null);
  const [confirmError, setConfirmError] = useState<string | null>(null);
  const [disableMessage, setDisableMessage] = useState<string | null>(null);
  const [recoveryMessage, setRecoveryMessage] = useState<string | null>(null);

  const enrollMutation = useMfaEnrollMutation();
  const confirmMutation = useMfaConfirmMutation();
  const disableMutation = useMfaDisableMutation();
  const recoveryMutation = useMfaRecoveryMutation();

  const confirmForm = useForm<TotpFormValues>({
    resolver: zodResolver(totpSchema),
    defaultValues: { code: "" },
  });

  const disableForm = useForm<TotpFormValues>({
    resolver: zodResolver(totpSchema),
    defaultValues: { code: "" },
  });

  const recoveryForm = useForm<TotpFormValues>({
    resolver: zodResolver(totpSchema),
    defaultValues: { code: "" },
  });

  function handleEnroll(): void {
    setConfirmMessage(null);
    setConfirmError(null);

    enrollMutation.mutate(undefined, {
      onSuccess: (response) => {
        setQrCode(response.qr_code);
        setRecoveryCodes(response.recovery_codes);
        setAttemptsRemaining(5);
        confirmForm.reset();
        setStep("enrolling");
      },
      onError: (error) => {
        if (error.code === MFA_ENROLL_ERROR_CODES.MFA_ALREADY_ENABLED) {
          setStep("active");
          setRecoveryCodes([]);
        } else if (
          error.code === MFA_ENROLL_ERROR_CODES.UNAUTHENTICATED ||
          error.code === MFA_ENROLL_ERROR_CODES.REFRESH_FAILED
        ) {
          return;
        }
        if (error.code === MFA_ENROLL_ERROR_CODES.TOO_MANY_REQUESTS) {
          toast.error(
            "Demasiados intentos. Espera una hora e inténtalo de nuevo.",
          );
        }
      },
    });
  }

  function handleConfirm(values: TotpFormValues): void {
    setConfirmMessage(null);
    setConfirmError(null);

    const dto: MfaConfirmRequest = { code: values.code };
    confirmMutation.mutate(dto, {
      onSuccess: () => {
        setRecoveryCodes([]);
        setQrCode(null);
        toast.success("MFA activado exitosamente.");
        setStep("active");
      },
      onError: (error) => {
        if (error.code === MFA_ENROLL_ERROR_CODES.MFA_CODE_INVALID) {
          const next = attemptsRemaining - 1;
          setAttemptsRemaining(next);
          if (next <= 0) {
            setConfirmMessage(
              "Demasiados intentos fallidos. La activación fue cancelada. Inicia de nuevo.",
            );
            setStep("idle");
            setRecoveryCodes([]);
            setQrCode(null);
          } else {
            setConfirmError("Código inválido. Intenta de nuevo.");
          }
          confirmForm.setValue("code", "");
        } else if (
          error.code === MFA_ENROLL_ERROR_CODES.MFA_ENROLLMENT_EXPIRED
        ) {
          setConfirmMessage(
            "Demasiados intentos fallidos. La activación fue cancelada. Inicia de nuevo.",
          );
          setStep("idle");
          setRecoveryCodes([]);
          setQrCode(null);
        } else if (
          error.code === MFA_ENROLL_ERROR_CODES.MFA_ENROLLMENT_NOT_FOUND
        ) {
          setConfirmMessage(
            "No hay una activación en curso. Inicia de nuevo.",
          );
          setStep("idle");
          setRecoveryCodes([]);
          setQrCode(null);
        } else if (
          error.code === MFA_ENROLL_ERROR_CODES.TOO_MANY_REQUESTS
        ) {
          toast.error(
            "Demasiados intentos. Espera un minuto e inténtalo de nuevo.",
          );
          confirmForm.setValue("code", "");
        } else {
          confirmForm.setValue("code", "");
        }
      },
    });
  }

  function handleDisable(values: TotpFormValues): void {
    setDisableMessage(null);

    const dto: MfaDisableRequest = { code: values.code };
    disableMutation.mutate(dto, {
      onSuccess: () => {
        toast.success("MFA desactivado exitosamente.");
        setStep("idle");
        disableForm.reset();
      },
      onError: (error) => {
        if (error.code === MFA_ENROLL_ERROR_CODES.MFA_CODE_INVALID) {
          setDisableMessage("Código inválido. Intenta de nuevo.");
          disableForm.setValue("code", "");
        } else if (error.code === MFA_ENROLL_ERROR_CODES.MFA_NOT_ENABLED) {
          toast.error("No tienes MFA activo.");
          setStep("idle");
          disableForm.reset();
        } else if (error.code === MFA_ENROLL_ERROR_CODES.TOO_MANY_REQUESTS) {
          toast.error(
            "Demasiados intentos. Espera un minuto e inténtalo de nuevo.",
          );
          disableForm.setValue("code", "");
        } else {
          disableForm.setValue("code", "");
        }
      },
    });
  }

  function handleRecovery(values: TotpFormValues): void {
    setRecoveryMessage(null);

    const dto: MfaRecoveryRequest = { code: values.code };
    recoveryMutation.mutate(dto, {
      onSuccess: (response) => {
        setRegeneratedCodes(response.recovery_codes);
        setActiveSection("recovery-view");
        recoveryForm.reset();
      },
      onError: (error) => {
        if (error.code === MFA_ENROLL_ERROR_CODES.MFA_CODE_INVALID) {
          setRecoveryMessage("Código inválido. Intenta de nuevo.");
          recoveryForm.setValue("code", "");
        } else if (error.code === MFA_ENROLL_ERROR_CODES.MFA_NOT_ENABLED) {
          toast.error("No tienes MFA activo.");
          setStep("idle");
          recoveryForm.reset();
        } else if (error.code === MFA_ENROLL_ERROR_CODES.TOO_MANY_REQUESTS) {
          toast.error(
            "Demasiados intentos. Espera un minuto e inténtalo de nuevo.",
          );
          recoveryForm.setValue("code", "");
        } else {
          recoveryForm.setValue("code", "");
        }
      },
    });
  }

  function handleBackToPanel(): void {
    setRegeneratedCodes([]);
    setActiveSection("panel");
  }

  return (
    <AuthLayout contentClassName="w-full max-w-lg">
      <Card className="w-full">
        <CardHeader className="space-y-1">
          <CardTitle className="text-2xl font-bold text-center">
            Autenticación en dos pasos
          </CardTitle>
          <CardDescription className="text-center">
            Protege tu cuenta agregando un segundo factor de autenticación
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          {/* Step: IDLE — activate button */}
          {step === "idle" && (
            <div className="space-y-4">
              {confirmMessage && (
                <p className="text-center text-sm text-destructive">
                  {confirmMessage}
                </p>
              )}
              <Button
                className="w-full"
                onClick={handleEnroll}
                disabled={enrollMutation.isPending}
              >
                {enrollMutation.isPending ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Activando...
                  </>
                ) : (
                  "Activar autenticación en dos pasos"
                )}
              </Button>
            </div>
          )}

          {/* Step: ENROLLING — QR + codes + confirm */}
          {step === "enrolling" && (
            <div className="space-y-6">
              <div>
                <h3 className="mb-2 font-semibold">Paso 1: Escanea el código QR</h3>
                <p className="mb-4 text-sm text-muted-foreground">
                  Escanea este código con tu aplicación autenticadora (Google
                  Authenticator, Authy, etc.).
                </p>
                {qrCode && (
                  <div className="flex justify-center rounded-lg border bg-white p-4">
                    <img
                      src={qrCode}
                      alt="QR code para autenticador"
                      className="h-48 w-48"
                    />
                  </div>
                )}
              </div>

              {recoveryCodes.length > 0 && (
                <div>
                  <h3 className="mb-1 font-semibold">Códigos de respaldo</h3>
                  <p className="mb-3 text-sm text-muted-foreground">
                    Guarda estos códigos en un lugar seguro. Si pierdes acceso a
                    tu aplicación autenticadora, úsalos para iniciar sesión.
                  </p>
                  <RecoveryCodes codes={recoveryCodes} />
                </div>
              )}

              <div>
                <h3 className="mb-1 font-semibold">Paso 2: Verifica el código</h3>
                <p className="mb-3 text-sm text-muted-foreground">
                  Ingresa el código de 6 dígitos generado por tu aplicación
                  autenticadora.
                </p>

                <Form {...confirmForm}>
                  <form
                    onSubmit={confirmForm.handleSubmit(handleConfirm)}
                    className="space-y-4"
                    noValidate
                  >
                    <FormField
                      control={confirmForm.control}
                      name="code"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>Código de verificación</FormLabel>
                          <FormControl>
                            <Input
                              type="text"
                              inputMode="numeric"
                              placeholder="000000"
                              autoComplete="one-time-code"
                              maxLength={6}
                              disabled={confirmMutation.isPending}
                              {...field}
                            />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />

                    {confirmError && (
                      <div className="space-y-1">
                        <p className="text-sm text-destructive">
                          {confirmError}
                        </p>
                        {attemptsRemaining > 0 && (
                          <p className="text-sm text-muted-foreground">
                            {attemptsRemaining} de 5 intentos restantes
                          </p>
                        )}
                      </div>
                    )}

                    <Button
                      type="submit"
                      className="w-full"
                      disabled={confirmMutation.isPending}
                    >
                      {confirmMutation.isPending ? (
                        <>
                          <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                          Verificando...
                        </>
                      ) : (
                        "Verificar y activar"
                      )}
                    </Button>
                  </form>
                </Form>
              </div>
            </div>
          )}

          {/* Step: ACTIVE — management panel */}
          {step === "active" && (
            <div className="space-y-6">
              <div className="flex items-center gap-2">
                <ShieldCheck className="h-5 w-5 text-success" />
                <span className="rounded-full bg-success/15 px-3 py-0.5 text-sm font-medium text-success">
                  MFA activo
                </span>
              </div>

              {activeSection === "panel" && (
                <>
                  {/* Disable section */}
                  <div className="space-y-3 rounded-lg border p-4">
                    <h3 className="font-semibold">
                      Desactivar autenticación en dos pasos
                    </h3>
                    <p className="text-sm text-muted-foreground">
                      Ingresa un código de tu aplicación autenticadora para
                      desactivar MFA.
                    </p>

                    <Form {...disableForm}>
                      <form
                        onSubmit={disableForm.handleSubmit(handleDisable)}
                        className="space-y-3"
                        noValidate
                      >
                        <FormField
                          control={disableForm.control}
                          name="code"
                          render={({ field }) => (
                            <FormItem>
                              <FormLabel>Código de verificación</FormLabel>
                              <FormControl>
                                <Input
                                  type="text"
                                  inputMode="numeric"
                                  placeholder="000000"
                                  autoComplete="one-time-code"
                                  maxLength={6}
                                  disabled={disableMutation.isPending}
                                  {...field}
                                />
                              </FormControl>
                              <FormMessage />
                            </FormItem>
                          )}
                        />

                        {disableMessage && (
                          <p className="text-sm text-destructive">
                            {disableMessage}
                          </p>
                        )}

                        <Button
                          type="submit"
                          variant="destructive"
                          disabled={disableMutation.isPending}
                        >
                          {disableMutation.isPending ? (
                            <>
                              <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                              Desactivando...
                            </>
                          ) : (
                            "Desactivar"
                          )}
                        </Button>
                      </form>
                    </Form>
                  </div>

                  {/* Recovery section */}
                  <div className="space-y-3 rounded-lg border p-4">
                    <h3 className="font-semibold">
                      Regenerar códigos de respaldo
                    </h3>
                    <p className="text-sm text-muted-foreground">
                      Ingresa un código de tu aplicación autenticadora para
                      generar nuevos códigos. Los códigos anteriores quedarán
                      invalidados.
                    </p>

                    <Form {...recoveryForm}>
                      <form
                        onSubmit={recoveryForm.handleSubmit(handleRecovery)}
                        className="space-y-3"
                        noValidate
                      >
                        <FormField
                          control={recoveryForm.control}
                          name="code"
                          render={({ field }) => (
                            <FormItem>
                              <FormLabel>Código de verificación</FormLabel>
                              <FormControl>
                                <Input
                                  type="text"
                                  inputMode="numeric"
                                  placeholder="000000"
                                  autoComplete="one-time-code"
                                  maxLength={6}
                                  disabled={recoveryMutation.isPending}
                                  {...field}
                                />
                              </FormControl>
                              <FormMessage />
                            </FormItem>
                          )}
                        />

                        {recoveryMessage && (
                          <p className="text-sm text-destructive">
                            {recoveryMessage}
                          </p>
                        )}

                        <Button
                          type="submit"
                          variant="outline"
                          disabled={recoveryMutation.isPending}
                        >
                          {recoveryMutation.isPending ? (
                            <>
                              <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                              Regenerando...
                            </>
                          ) : (
                            "Regenerar"
                          )}
                        </Button>
                      </form>
                    </Form>
                  </div>
                </>
              )}

              {activeSection === "recovery-view" &&
                regeneratedCodes.length > 0 && (
                  <div className="space-y-4">
                    <div>
                      <h3 className="mb-1 font-semibold">
                        Nuevos códigos de respaldo
                      </h3>
                      <p className="mb-3 text-sm text-muted-foreground">
                        Estos códigos se muestran una sola vez. Guárdalos en un
                        lugar seguro. Los códigos anteriores quedaron
                        invalidados.
                      </p>
                      <RecoveryCodes codes={regeneratedCodes} />
                    </div>

                    <Button
                      type="button"
                      variant="secondary"
                      className="w-full"
                      onClick={handleBackToPanel}
                    >
                      Volver al panel de gestión
                    </Button>
                  </div>
                )}
            </div>
          )}
        </CardContent>
      </Card>
    </AuthLayout>
  );
}
