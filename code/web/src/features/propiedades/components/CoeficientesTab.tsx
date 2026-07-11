import {
  type ReactNode,
  useState,
  useCallback,
  useMemo,
  useEffect,
} from "react";
import { Button } from "@/components/ui/button";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Select } from "@/components/ui/select";
import { Loader2, AlertTriangle, History } from "lucide-react";
import { toast } from "sonner";
import { SumaBar } from "./SumaBar";
import { usePropertiesInfiniteQuery, flattenProperties } from "../api/properties";
import {
  useBatchPropertyCoefficientsQueries,
  useUpdateCoefficientsMutation,
} from "../api/coefficients";
import type {
  CoefficientItem,
  CoefficientType,
  CoefficientRow,
} from "../types";
import {
  COEFFICIENT_TYPES,
  COEFFICIENT_TYPE_LABELS,
  isVigente,
} from "../types";

interface CoeficientesTabProps {
  condominioId: string;
}

/**
 * Tab "Coeficientes" del DetalleCondominio.
 * Tabla editable en lote donde el admin asigna coeficientes a cada unidad,
 * con barra de suma en tiempo real, validación visual, toggle de historial,
 * y selector de tipo de coeficiente.
 *
 * Consume LOCK-PROPIEDADES-04 y LOCK-PROPIEDADES-03.
 */
export function CoeficientesTab({ condominioId }: CoeficientesTabProps): ReactNode {
  // ── Tipo seleccionado (filtrar tabla) ─────────────────────────────────
  const [tipoFiltro, setTipoFiltro] = useState<CoefficientType>("copropiedad");

  // ── Toggle historial ──────────────────────────────────────────────────
  const [verHistorial, setVerHistorial] = useState(false);

  // ── Estado de edición local (valores en inputs) ───────────────────────
  // key: `${property_id}::${tipo}` → string value
  const [editedValues, setEditedValues] = useState<Record<string, string>>({});

  // ── Obtener todas las propiedades del condominio ──────────────────────
  const {
    data: pagesData,
    isLoading: propsLoading,
    isError: propsError,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = usePropertiesInfiniteQuery(condominioId, {});

  const allProperties = useMemo(
    () => flattenProperties(pagesData?.pages),
    [pagesData],
  );

  // Auto-fetch all pages
  useEffect(() => {
    if (hasNextPage && !isFetchingNextPage) {
      void fetchNextPage();
    }
  }, [hasNextPage, isFetchingNextPage, fetchNextPage]);

  // ── Obtener coeficientes de todas las propiedades ─────────────────────
  const propertyIds = useMemo(
    () => allProperties.map((p) => p.id),
    [allProperties],
  );

  const {
    coefficientsMap,
    isLoading: coeffsLoading,
    isError: coeffsError,
  } = useBatchPropertyCoefficientsQueries(
    // Solo consultar cuando ya tenemos todas las propiedades (no hay más páginas)
    !hasNextPage ? propertyIds : [],
  );

  // ── Construir las filas de la tabla ───────────────────────────────────
  const allRows = useMemo<CoefficientRow[]>(() => {
    const rows: CoefficientRow[] = [];

    for (const property of allProperties) {
      const coeffs = coefficientsMap.get(property.id) ?? [];

      // Agrupar coeficientes por tipo
      const byType = new Map<CoefficientType, CoefficientItem[]>();
      for (const c of coeffs) {
        const list = byType.get(c.tipo) ?? [];
        list.push(c);
        byType.set(c.tipo, list);
      }

      // Para CADA tipo de coeficiente, generar una fila
      for (const tipo of COEFFICIENT_TYPES) {
        const historicos = byType.get(tipo) ?? [];
        // El vigente es el que tiene vigente_hasta === null
        const vigente = historicos.find((c) => isVigente(c)) ?? null;

        const key = `${property.id}::${tipo}`;
        const editedVal = editedValues[key];
        const originalVal = vigente?.valor ?? null;
        const hasEdit = editedVal !== undefined;
        const currentVal = hasEdit ? editedVal : (originalVal !== null ? String(originalVal) : "");

        rows.push({
          property_id: property.id,
          codigo: property.codigo,
          tower_id: property.tower_id,
          tower_nombre: null, // se llena después si tenemos tree data
          tipo,
          valor: currentVal,
          originalValor: originalVal,
          vigente,
          historicos,
          modified: hasEdit && (originalVal === null || String(originalVal) !== editedVal),
        });
      }
    }

    return rows;
  }, [allProperties, coefficientsMap, editedValues]);

  // ── Filtrar por tipo seleccionado ─────────────────────────────────────
  const filteredRows = useMemo(
    () => allRows.filter((r) => r.tipo === tipoFiltro),
    [allRows, tipoFiltro],
  );

  // ── Calcular suma de copropiedad en tiempo real ───────────────────────
  const sumaCopropiedad = useMemo(() => {
    const copRows = allRows.filter((r) => r.tipo === "copropiedad");
    if (copRows.length === 0) return 0;
    const sum = copRows.reduce((acc, r) => {
      const v = parseFloat(r.valor);
      return acc + (Number.isFinite(v) ? v : 0);
    }, 0);
    return sum;
  }, [allRows]);

  // ── Determinar filas modificadas (TODAS, no solo las filtradas) ─────
  const modifiedRows = useMemo(
    () => allRows.filter((r) => r.modified),
    [allRows],
  );

  const hasModifications = modifiedRows.length > 0;

  // ── Mutation para guardar ─────────────────────────────────────────────
  const updateMutation = useUpdateCoefficientsMutation(condominioId);

  const handleSave = useCallback(() => {
    const validItems: Array<{ property_id: string; tipo: CoefficientType; valor: number }> = [];
    const skippedItems: string[] = [];

    for (const r of modifiedRows) {
      const v = parseFloat(r.valor);
      if (Number.isFinite(v) && v >= 0 && v <= 1) {
        validItems.push({
          property_id: r.property_id,
          tipo: r.tipo,
          valor: v,
        });
      } else {
        skippedItems.push(`${r.codigo} (${COEFFICIENT_TYPE_LABELS[r.tipo]})`);
      }
    }

    if (validItems.length === 0) {
      toast.error("No hay cambios válidos para guardar. Verifica los valores ingresados.");
      return;
    }

    if (skippedItems.length > 0) {
      toast.warning(
        `${skippedItems.length} ${skippedItems.length === 1 ? "valor inválido fue omitido" : "valores inválidos fueron omitidos"}.`,
      );
    }

    updateMutation.mutate(
      { items: validItems },
      {
        onSuccess: () => {
          // Limpiar el estado editado para que refleje los nuevos valores del servidor
          setEditedValues({});
        },
      },
    );
  }, [modifiedRows, updateMutation]);

  // ── Handler de input inline ───────────────────────────────────────────
  const handleValueChange = useCallback(
    (propertyId: string, tipo: CoefficientType, rawValue: string) => {
      // Permitir solo dígitos, punto decimal, y vacío
      if (rawValue !== "" && !/^\d*\.?\d*$/.test(rawValue)) return;

      const key = `${propertyId}::${tipo}`;
      setEditedValues((prev) => ({
        ...prev,
        [key]: rawValue,
      }));
    },
    [],
  );

  // ── Loading / Error ───────────────────────────────────────────────────
  const isLoading = propsLoading || (hasNextPage && isFetchingNextPage) || (coeffsLoading && propertyIds.length > 0);
  const isError = propsError || coeffsError;

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-16">
        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
      </div>
    );
  }

  if (isError) {
    return (
      <div className="flex flex-col items-center justify-center rounded-lg border border-dashed py-16 text-center">
        <AlertTriangle className="mb-4 h-10 w-10 text-destructive/60" />
        <p className="text-sm text-muted-foreground">
          Error al cargar los coeficientes. Verifica tu conexión.
        </p>
      </div>
    );
  }

  if (allProperties.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center rounded-lg border border-dashed py-12 text-center">
        <p className="text-sm text-muted-foreground">
          No hay unidades registradas en este condominio.
        </p>
        <p className="mt-1 text-xs text-muted-foreground">
          Agrega unidades en el tab &quot;Unidades&quot; para asignar coeficientes.
        </p>
      </div>
    );
  }

  const isSaving = updateMutation.isPending;

  return (
    <div className="space-y-4 pt-4">
      {/* ── Barra de suma ──────────────────────────────────────────────── */}
      <SumaBar sum={sumaCopropiedad} isLoading={isLoading} />

      {/* ── Controles ──────────────────────────────────────────────────── */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          {/* Selector de tipo */}
          <label htmlFor="tipo-coeficiente" className="text-sm font-medium">
            Tipo:
          </label>
          <Select
            id="tipo-coeficiente"
            value={tipoFiltro}
            onChange={(e) => setTipoFiltro(e.target.value as CoefficientType)}
            className="w-[180px]"
            options={COEFFICIENT_TYPES.map((t) => ({
              value: t,
              label: COEFFICIENT_TYPE_LABELS[t],
            }))}
          />

          {/* Toggle historial */}
          <Button
            variant={verHistorial ? "secondary" : "outline"}
            size="sm"
            onClick={() => setVerHistorial((v) => !v)}
          >
            <History className="mr-1.5 h-4 w-4" />
            {verHistorial ? "Ocultar historial" : "Ver historial"}
          </Button>
        </div>

        {/* Botón guardar */}
        <div className="flex items-center gap-2">
          {hasModifications ? (
            <span className="text-xs text-muted-foreground">
              {modifiedRows.length}{" "}
              {modifiedRows.length === 1 ? "cambio pendiente" : "cambios pendientes"}
            </span>
          ) : null}
          <Button
            size="sm"
            onClick={handleSave}
            disabled={!hasModifications || isSaving}
          >
            {isSaving ? (
              <>
                <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                Guardando...
              </>
            ) : (
              "Guardar cambios"
            )}
          </Button>
        </div>
      </div>

      {/* ── Tabla ──────────────────────────────────────────────────────── */}
      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-[120px]">Unidad</TableHead>
              {verHistorial ? (
                <TableHead className="w-[100px]">Vig. desde</TableHead>
              ) : null}
              {verHistorial ? (
                <TableHead className="w-[100px]">Vig. hasta</TableHead>
              ) : null}
              <TableHead>Tipo</TableHead>
              <TableHead className="w-[200px]">Valor</TableHead>
              {verHistorial ? (
                <TableHead className="w-[60px] text-center">Vigente</TableHead>
              ) : null}
            </TableRow>
          </TableHeader>
          <TableBody>
            {filteredRows.length === 0 ? (
              <TableRow>
                <TableCell
                  colSpan={verHistorial ? 6 : 3}
                  className="py-8 text-center text-muted-foreground"
                >
                  No hay unidades con coeficientes de tipo &quot;
                  {COEFFICIENT_TYPE_LABELS[tipoFiltro]}&quot;.
                </TableCell>
              </TableRow>
            ) : (
              filteredRows.map((row) => {
                const key = `${row.property_id}::${row.tipo}`;
                const isCurrentVigente = row.vigente !== null && row.vigente !== undefined && isVigente(row.vigente);

                return (
                  <TableRow
                    key={key}
                    className={
                      row.modified
                        ? "bg-warning/10"
                        : isCurrentVigente && verHistorial
                          ? "bg-success/10"
                          : undefined
                    }
                  >
                    {/* Código unidad */}
                    <TableCell className="font-medium">
                      {row.codigo}
                    </TableCell>

                    {/* Vigente desde (historial) */}
                    {verHistorial ? (
                      <TableCell className="text-xs text-muted-foreground">
                        {row.vigente?.vigente_desde ?? "—"}
                      </TableCell>
                    ) : null}

                    {/* Vigente hasta (historial) */}
                    {verHistorial ? (
                      <TableCell className="text-xs text-muted-foreground">
                        {row.vigente?.vigente_hasta ?? (
                          isCurrentVigente ? (
                            <span className="font-medium text-success">
                              Vigente
                            </span>
                          ) : "—"
                        )}
                      </TableCell>
                    ) : null}

                    {/* Tipo */}
                    <TableCell className="text-sm text-muted-foreground">
                      {COEFFICIENT_TYPE_LABELS[row.tipo]}
                    </TableCell>

                    {/* Valor editable */}
                    <TableCell>
                      <input
                        type="text"
                        inputMode="decimal"
                        value={row.valor}
                        onChange={(e) =>
                          handleValueChange(row.property_id, row.tipo, e.target.value)
                        }
                        placeholder="0.0000"
                        className={`w-full rounded-md border px-3 py-1.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-ring ${
                          row.modified
                            ? "border-warning bg-warning/10"
                            : "border-input bg-background"
                        }`}
                        aria-label={`Coeficiente ${row.codigo} - ${COEFFICIENT_TYPE_LABELS[row.tipo]}`}
                      />
                    </TableCell>

                    {/* Indicador vigente (historial) */}
                    {verHistorial ? (
                      <TableCell className="text-center">
                        {isCurrentVigente ? (
                          <span
                            className="inline-flex h-5 w-5 items-center justify-center rounded-full bg-success/15 text-success"
                            title="Coeficiente vigente"
                          >
                            ✓
                          </span>
                        ) : (
                          <span className="text-muted-foreground">—</span>
                        )}
                      </TableCell>
                    ) : null}
                  </TableRow>
                );
              })
            )}
          </TableBody>
        </Table>
      </div>

      {/* ── Footer info ────────────────────────────────────────────────── */}
      <p className="text-xs text-muted-foreground">
        {filteredRows.length} {filteredRows.length === 1 ? "unidad" : "unidades"} ·{" "}
        Tipo: {COEFFICIENT_TYPE_LABELS[tipoFiltro]} ·{" "}
        El coeficiente vigente se cierra automáticamente al guardar uno nuevo (R-05).
      </p>
    </div>
  );
}
