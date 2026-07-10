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
            ? "border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200"
            : "border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
      }`}
    >
      {isLoading ? (
        <span className="h-4 w-4 animate-pulse rounded-full bg-muted-foreground/30" />
      ) : isExact ? (
        <CheckCircle2 className="h-4 w-4 shrink-0 text-green-600 dark:text-green-400" />
      ) : (
        <AlertTriangle className="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
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
