// Bootstrap — side-effect modules del dashboard (debe ir antes que cualquier feature)
import "@/app/bootstrap";

import { type ReactNode, lazy, Suspense } from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Routes, Route } from "react-router-dom";
import { Toaster } from "@/components/ui/sonner";
import { ThemeProvider } from "@/components/theme-provider";
import { ErrorBoundary } from "@/components/error-boundary";
import { NotFoundPage } from "@/components/not-found-page";
import { DashboardPage } from "@/app/DashboardPage";
import { RequireAuth } from "@/app/RequireAuth";
import { AppLayout } from "@/app/AppLayout";
import { RouteSuspenseFallback } from "@/components/route-fallback";

const LoginPageLazy = lazy(() =>
  import("@/features/auth/pages/LoginPage").then((m) => ({
    default: m.LoginPage,
  })),
);

const RegisterPageLazy = lazy(() =>
  import("@/features/auth/pages/RegisterPage").then((m) => ({
    default: m.RegisterPage,
  })),
);

const MfaVerifyPageLazy = lazy(() =>
  import("@/features/auth/pages/MfaVerifyPage").then((m) => ({
    default: m.MfaVerifyPage,
  })),
);

const MfaEnrollPageLazy = lazy(() =>
  import("@/features/auth/pages/MfaEnrollPage").then((m) => ({
    default: m.MfaEnrollPage,
  })),
);

const ForgotPasswordPageLazy = lazy(() =>
  import("@/features/auth/pages/ForgotPasswordPage").then((m) => ({
    default: m.ForgotPasswordPage,
  })),
);

const ResetPasswordPageLazy = lazy(() =>
  import("@/features/auth/pages/ResetPasswordPage").then((m) => ({
    default: m.ResetPasswordPage,
  })),
);

const TiposPropiedadPageLazy = lazy(() =>
  import("@/features/propiedades/pages/TiposPropiedadPage").then((m) => ({
    default: m.TiposPropiedadPage,
  })),
);

const EstadosPropiedadPageLazy = lazy(() =>
  import("@/features/propiedades/pages/EstadosPropiedadPage").then((m) => ({
    default: m.EstadosPropiedadPage,
  })),
);

const CondominiosListPageLazy = lazy(() =>
  import("@/features/propiedades/pages/CondominiosListPage").then((m) => ({
    default: m.CondominiosListPage,
  })),
);

const DetalleCondominioPageLazy = lazy(() =>
  import("@/features/propiedades/pages/DetalleCondominioPage").then((m) => ({
    default: m.DetalleCondominioPage,
  })),
);

const TiposOcupantePageLazy = lazy(() =>
  import("@/features/directorio/pages/TiposOcupantePage").then((m) => ({
    default: m.TiposOcupantePage,
  })),
);

const ContactosPageLazy = lazy(() =>
  import("@/features/directorio/pages/ContactosPage").then((m) => ({
    default: m.ContactosPage,
  })),
);

const MiPerfilPageLazy = lazy(() =>
  import("@/features/directorio/pages/MiPerfilPage").then((m) => ({
    default: m.MiPerfilPage,
  })),
);

const DevIndicatorLazy = import.meta.env.DEV
  ? lazy(() =>
      import("@/components/DevIndicator").then((m) => ({
        default: m.DevIndicator,
      })),
    )
  : null;

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000, // 5 min — conservador para datos de panel
      retry: 1,
    },
    mutations: {
      retry: 0, // Mutaciones no reintentan — especialmente login (evita loop 429)
    },
  },
});

function DevIndicatorWrapper(): ReactNode {
  if (!DevIndicatorLazy) return null;
  return (
    <Suspense fallback={null}>
      <DevIndicatorLazy />
    </Suspense>
  );
}

export function App(): ReactNode {
  return (
    <ThemeProvider defaultTheme="system" storageKey="urbania-ui-theme">
      <ErrorBoundary>
        <QueryClientProvider client={queryClient}>
          <BrowserRouter>
            <Routes>
              {/* Public routes — no authentication required */}
              <Route
                path="/login"
                element={
                  <Suspense fallback={<RouteSuspenseFallback />}>
                    <LoginPageLazy />
                  </Suspense>
                }
              />
              <Route
                path="/register/:token?"
                element={
                  <Suspense fallback={<RouteSuspenseFallback />}>
                    <RegisterPageLazy />
                  </Suspense>
                }
              />
              <Route
                path="/mfa/verify"
                element={
                  <Suspense fallback={<RouteSuspenseFallback />}>
                    <MfaVerifyPageLazy />
                  </Suspense>
                }
              />
              <Route
                path="/forgot-password"
                element={
                  <Suspense fallback={<RouteSuspenseFallback />}>
                    <ForgotPasswordPageLazy />
                  </Suspense>
                }
              />
              <Route
                path="/reset-password"
                element={
                  <Suspense fallback={<RouteSuspenseFallback />}>
                    <ResetPasswordPageLazy />
                  </Suspense>
                }
              />

              {/* MFA enroll — requiere auth pero es conceptualmente parte del flujo
                  de auth (mismo shell tipo Card que login/register), no lleva
                  DashboardShell. */}
              <Route element={<RequireAuth />}>
                <Route
                  path="/mfa/enroll"
                  element={
                    <Suspense fallback={<RouteSuspenseFallback />}>
                      <MfaEnrollPageLazy />
                    </Suspense>
                  }
                />
              </Route>

              {/* Private routes — RequireAuth wrapper checks for access token,
                  AppLayout instancia DashboardShell una única vez para todas
                  las rutas anidadas (antes cada página se envolvía sola, o no
                  se envolvía en absoluto — ver plan de rediseño, Fase 1). */}
              <Route element={<RequireAuth />}>
                <Route element={<AppLayout />}>
                  <Route index element={<DashboardPage />} />
                  <Route path="/dashboard" element={<DashboardPage />} />
                  <Route
                    path="/catalogos/tipos-propiedad"
                    element={
                      <Suspense fallback={<RouteSuspenseFallback />}>
                        <TiposPropiedadPageLazy />
                      </Suspense>
                    }
                  />
                  <Route
                    path="/catalogos/estados-propiedad"
                    element={
                      <Suspense fallback={<RouteSuspenseFallback />}>
                        <EstadosPropiedadPageLazy />
                      </Suspense>
                    }
                  />
                  <Route
                    path="/condominios"
                    element={
                      <Suspense fallback={<RouteSuspenseFallback />}>
                        <CondominiosListPageLazy />
                      </Suspense>
                    }
                  />
                  <Route
                    path="/condominios/:id"
                    element={
                      <Suspense fallback={<RouteSuspenseFallback />}>
                        <DetalleCondominioPageLazy />
                      </Suspense>
                    }
                  />
                  <Route
                    path="/catalogos/tipos-ocupante"
                    element={
                      <Suspense fallback={<RouteSuspenseFallback />}>
                        <TiposOcupantePageLazy />
                      </Suspense>
                    }
                  />
                  <Route
                    path="/directorio/contactos"
                    element={
                      <Suspense fallback={<RouteSuspenseFallback />}>
                        <ContactosPageLazy />
                      </Suspense>
                    }
                  />
                  <Route
                    path="/perfil"
                    element={
                      <Suspense fallback={<RouteSuspenseFallback />}>
                        <MiPerfilPageLazy />
                      </Suspense>
                    }
                  />
                </Route>
              </Route>

              {/* Catch-all — cualquier ruta no reconocida, pública o privada. */}
              <Route path="*" element={<NotFoundPage />} />
            </Routes>
            <DevIndicatorWrapper />
          </BrowserRouter>
          <Toaster richColors closeButton />
        </QueryClientProvider>
      </ErrorBoundary>
    </ThemeProvider>
  );
}
