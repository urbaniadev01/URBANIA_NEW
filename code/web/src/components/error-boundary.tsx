import { Component, type ErrorInfo, type ReactNode } from "react";
import { Button } from "@/components/ui/button";

interface ErrorBoundaryProps {
  children: ReactNode;
}

interface ErrorBoundaryState {
  hasError: boolean;
}

/** Error boundary genérico — mínimo para MVP, ver web/WEB_VISUAL_STANDARDS.md §6. */
export class ErrorBoundary extends Component<
  ErrorBoundaryProps,
  ErrorBoundaryState
> {
  state: ErrorBoundaryState = { hasError: false };

  static getDerivedStateFromError(): ErrorBoundaryState {
    return { hasError: true };
  }

  componentDidCatch(error: Error, info: ErrorInfo): void {
    console.error("[ErrorBoundary]", error, info.componentStack);
  }

  render(): ReactNode {
    if (!this.state.hasError) {
      return this.props.children;
    }

    return (
      <div className="flex min-h-screen flex-col items-center justify-center gap-4 bg-background px-4 text-center">
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">
          Ocurrió un error inesperado
        </h1>
        <p className="max-w-sm text-sm text-muted-foreground">
          Intentá recargar la página. Si el problema persiste, contactá al
          administrador.
        </p>
        <Button onClick={() => window.location.assign("/")}>
          Volver al inicio
        </Button>
      </div>
    );
  }
}
