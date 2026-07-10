import { type ReactNode, Suspense } from "react";
import { AlertCircle, PackageOpen } from "lucide-react";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { WidgetSkeleton } from "@/features/dashboard/components/WidgetSkeleton";

/**
 * Estados visuales de un widget.
 *
 * Cada widget maneja sus estados de forma independiente — si un endpoint falla,
 * solo ese widget muestra error; los demás siguen funcionando.
 */
export type WidgetState =
  | { status: "loading" }
  | { status: "empty"; message: string; cta: string; onCta: () => void }
  | { status: "error"; message: string; onRetry: () => void }
  | { status: "normal" };

interface WidgetCardProps {
  title: string;
  description?: string;
  state: WidgetState;
  children: ReactNode;
}

/**
 * WidgetCard — envoltorio de tarjeta para widgets del dashboard.
 *
 * Reglas de oro (PANORAMA §8.2):
 * - 4 estados independientes: loading, empty, error, normal
 * - NUNCA ocultar un widget que falló → mostrar Alert destructive + Reintentar
 * - Cada estado tiene sus atributos ARIA correctos
 */
export function WidgetCard({
  title,
  description,
  state,
  children,
}: WidgetCardProps): ReactNode {
  return (
    <Card className="flex h-full flex-col">
      <CardHeader className="pb-3">
        <CardTitle className="text-base">{title}</CardTitle>
        {description && <CardDescription>{description}</CardDescription>}
      </CardHeader>
      <CardContent className="flex-1">
        {renderContent(state, children)}
      </CardContent>
    </Card>
  );
}

function renderContent(state: WidgetState, children: ReactNode): ReactNode {
  switch (state.status) {
    case "loading":
      return (
        <div aria-busy="true">
          <WidgetSkeleton />
        </div>
      );

    case "empty":
      return (
        <div
          role="status"
          className="flex flex-col items-center justify-center gap-4 py-8 text-center"
        >
          <PackageOpen
            className="h-12 w-12 text-muted-foreground"
            style={{ opacity: 0.3 }}
            aria-hidden="true"
          />
          <p className="text-sm text-muted-foreground">{state.message}</p>
          <Button variant="outline" size="sm" onClick={state.onCta}>
            {state.cta}
          </Button>
        </div>
      );

    case "error":
      return (
        <div role="alert">
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>{state.message}</AlertDescription>
          </Alert>
          <div className="mt-3 flex justify-center">
            <Button variant="outline" size="sm" onClick={state.onRetry}>
              Reintentar
            </Button>
          </div>
        </div>
      );

    case "normal":
      return <Suspense fallback={<WidgetSkeleton />}>{children}</Suspense>;

    default:
      return null;
  }
}
