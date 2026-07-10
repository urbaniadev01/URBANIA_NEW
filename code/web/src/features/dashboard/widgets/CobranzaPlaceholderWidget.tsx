import type { ReactNode } from "react";
import { CreditCard } from "lucide-react";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { WidgetSkeleton } from "@/features/dashboard/components/WidgetSkeleton";
import type { WidgetProps } from "@/features/dashboard/types";

/**
 * CobranzaPlaceholderWidget — placeholder "Cuotas Pendientes".
 *
 * Reglas (R-DASH-03):
 * - Solo visible para roles staff (admin).
 * - featureStatus: 'draft' → el registry filtra para no-staff.
 * - NO dispara llamadas API.
 * - Badge "En desarrollo".
 * - Cuando COBRANZA esté SHIPPED, su widget real reemplaza este placeholder.
 *
 * priority: 90.
 */
export default function CobranzaPlaceholderWidget(
  _props: WidgetProps,
): ReactNode {
  return (
    <Card className="flex h-full flex-col">
      <CardHeader className="pb-3">
        <div className="flex items-center justify-between">
          <CardTitle className="flex items-center gap-2 text-base">
            <CreditCard
              className="h-4 w-4 text-muted-foreground"
              aria-hidden="true"
            />
            Cuotas Pendientes
          </CardTitle>
          <Badge variant="secondary">En desarrollo</Badge>
        </div>
      </CardHeader>
      <CardContent className="flex-1">
        <div aria-hidden="true">
          <WidgetSkeleton />
        </div>
      </CardContent>
    </Card>
  );
}
