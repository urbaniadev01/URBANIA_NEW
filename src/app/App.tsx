// Bootstrap — side-effect modules del dashboard (debe ir antes que cualquier feature)
import "@/app/bootstrap";

import { type ReactNode, lazy, Suspense } from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Routes, Route } from "react-router-dom";
import { Toaster } from "@/components/ui/sonner";
import { DashboardPage } from "@/app/DashboardPage";
import { RequireAuth } from "@/app/RequireAuth";

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
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <Routes>
          {/* Public routes — no authentication required */}
          <Route
            path="/login"
            element={
              <Suspense
                fallback={
                  <div className="flex min-h-screen items-center justify-center">
                    <p className="text-muted-foreground">Cargando...</p>
                  </div>
                }
              >
                <LoginPageLazy />
              </Suspense>
            }
          />
          <Route
            path="/register/:token?"
            element={
              <Suspense
                fallback={
                  <div className="flex min-h-screen items-center justify-center">
                    <p className="text-muted-foreground">Cargando...</p>
                  </div>
                }
              >
                <RegisterPageLazy />
              </Suspense>
            }
          />
          <Route
            path="/mfa/verify"
            element={
              <Suspense
                fallback={
                  <div className="flex min-h-screen items-center justify-center">
                    <p className="text-muted-foreground">Cargando...</p>
                  </div>
                }
              >
                <MfaVerifyPageLazy />
              </Suspense>
            }
          />
          <Route
            path="/forgot-password"
            element={
              <Suspense
                fallback={
                  <div className="flex min-h-screen items-center justify-center">
                    <p className="text-muted-foreground">Cargando...</p>
                  </div>
                }
              >
                <ForgotPasswordPageLazy />
              </Suspense>
            }
          />
          <Route
            path="/reset-password"
            element={
              <Suspense
                fallback={
                  <div className="flex min-h-screen items-center justify-center">
                    <p className="text-muted-foreground">Cargando...</p>
                  </div>
                }
              >
                <ResetPasswordPageLazy />
              </Suspense>
            }
          />

          {/* Private routes — RequireAuth wrapper checks for access token */}
          <Route element={<RequireAuth />}>
            <Route index element={<DashboardPage />} />
            <Route path="/dashboard" element={<DashboardPage />} />
            <Route
              path="/mfa/enroll"
              element={
                <Suspense
                  fallback={
                    <div className="flex min-h-screen items-center justify-center">
                      <p className="text-muted-foreground">Cargando...</p>
                    </div>
                  }
                >
                  <MfaEnrollPageLazy />
                </Suspense>
              }
            />
            <Route
              path="/catalogos/tipos-propiedad"
              element={
                <Suspense
                  fallback={
                    <div className="flex min-h-screen items-center justify-center">
                      <p className="text-muted-foreground">Cargando...</p>
                    </div>
                  }
                >
                  <TiposPropiedadPageLazy />
                </Suspense>
              }
            />
            <Route
              path="/catalogos/estados-propiedad"
              element={
                <Suspense
                  fallback={
                    <div className="flex min-h-screen items-center justify-center">
                      <p className="text-muted-foreground">Cargando...</p>
                    </div>
                  }
                >
                  <EstadosPropiedadPageLazy />
                </Suspense>
              }
            />
            <Route
              path="/condominios"
              element={
                <Suspense
                  fallback={
                    <div className="flex min-h-screen items-center justify-center">
                      <p className="text-muted-foreground">Cargando...</p>
                    </div>
                  }
                >
                  <CondominiosListPageLazy />
                </Suspense>
              }
            />
            <Route
              path="/condominios/:id"
              element={
                <Suspense
                  fallback={
                    <div className="flex min-h-screen items-center justify-center">
                      <p className="text-muted-foreground">Cargando...</p>
                    </div>
                  }
                >
                  <DetalleCondominioPageLazy />
                </Suspense>
              }
            />
          </Route>
        </Routes>
        <DevIndicatorWrapper />
      </BrowserRouter>
      <Toaster richColors closeButton />
    </QueryClientProvider>
  );
}
