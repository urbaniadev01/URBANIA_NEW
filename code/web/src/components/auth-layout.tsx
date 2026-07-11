import type { ReactNode } from "react";
import { cn } from "@/lib/utils";

interface AuthLayoutProps {
  children: ReactNode;
  /** Clases del contenedor del formulario — default: "w-full max-w-md". */
  contentClassName?: string;
}

/**
 * Layout compartido de las 6 pantallas de auth (login, registro,
 * forgot/reset password, MFA). Split-screen: panel de marca a la
 * izquierda (oculto en mobile), formulario a la derecha — reemplaza el
 * shell "Card centrada sobre fondo" que cada página duplicaba inline.
 */
export function AuthLayout({
  children,
  contentClassName,
}: AuthLayoutProps): ReactNode {
  return (
    <div className="grid min-h-screen lg:grid-cols-2">
      {/* Panel de marca — solo desktop */}
      <div className="hidden flex-col justify-between bg-accent-brand p-10 text-accent-brand-foreground lg:flex">
        <div className="flex items-center gap-2 text-lg font-semibold">
          <img
            src="/logo.jpg"
            alt="Urbania"
            className="h-8 w-8 rounded-sm object-cover"
          />
          Urbania
        </div>
        <p className="max-w-sm text-2xl font-semibold leading-snug">
          Administra condominios, propiedades y cobranza desde un solo panel.
        </p>
        <p className="text-sm text-accent-brand-foreground/70">
          © {new Date().getFullYear()} Urbania
        </p>
      </div>

      {/* Formulario */}
      <div className="flex items-center justify-center bg-muted/50 px-4 py-12 lg:bg-background">
        <div className={cn("w-full max-w-md", contentClassName)}>
          {children}
        </div>
      </div>
    </div>
  );
}
