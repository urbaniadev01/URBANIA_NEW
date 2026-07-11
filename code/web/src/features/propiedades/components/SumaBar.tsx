import { useMemo } from "react";
import { CheckCircle2, AlertTriangle } from "lucide-react";

interface SumaBarProps {
  /** Suma actual de los coeficientes de copropiedad */
  sum: number;
  /** Si está cargando los datos */
  isLoading?: boolean;
}

/**
 * Barra de suma en tiempo real que muestra el total de coeficientes
 * de copropiedad con indicador visual:
 * - Verde (100%): suma = 1.0
 * - Ámbar (≠ 100%): suma ≠ 1.0
 *
 * La barra se actualiza con useMemo (derived state) en cada cambio.
 */
export function SumaBar({ sum, isLoading = false }: SumaBarProps) {
  const pct = useMemo(() => sum * 100, [sum]);
  const isExact = useMemo(() => Math.abs(sum - 1.0) < 0.0001, [sum]);

  return (
    <div
      className={`flex items-center gap-3 rounded-lg border px-4 py-3 text-sm ${
        isLoading
          ? "border-muted bg-muted/20"
          : isExact
            ? "border-success/30 bg-success/10 text-success"
            : "border-warning/30 bg-warning/10 text-warning"
      }`}
    >
      {isLoading ? (
        <span className="h-4 w-4 animate-pulse rounded-full bg-muted-foreground/30" />
      ) : isExact ? (
        <CheckCircle2 className="h-4 w-4 shrink-0 text-success" />
      ) : (
        <AlertTriangle className="h-4 w-4 shrink-0 text-warning" />
      )}

      <span className="flex-1 font-medium">
        {isLoading
          ? "Calculando suma…"
          : isExact
            ? `Suma: ${pct.toFixed(1)}% ✓`
            : `Suma actual: ${pct.toFixed(1)}% — se requiere 100%`}
      </span>

      {!isLoading ? (
        <span className="text-xs opacity-70">
          {isExact ? "Balanceado" : `${(100 - pct).toFixed(1)}% faltante`}
        </span>
      ) : null}
    </div>
  );
}
