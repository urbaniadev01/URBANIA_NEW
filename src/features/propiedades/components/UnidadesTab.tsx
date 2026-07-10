import {
  type ReactNode,
  useState,
  useCallback,
  useMemo,
  useEffect,
  useRef,
} from "react";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Checkbox } from "@/components/ui/checkbox";
import { Select } from "@/components/ui/select";
import { Label } from "@/components/ui/label";
import {
  Loader2,
  Plus,
  Pencil,
  Trash2,
  Home,
  AlertTriangle,
} from "lucide-react";
import { FiltrosUnidades } from "./FiltrosUnidades";
import { UnidadSheet } from "./UnidadSheet";
import {
  usePropertiesInfiniteQuery,
  useCreatePropertyMutation,
  useUpdatePropertyMutation,
  useDeletePropertyMutation,
  useBatchUpdateStatusMutation,
  useBatchDeleteMutation,
  flattenProperties,
} from "../api/properties";
import {
  useTorresQuery,
} from "../api/towers";
import {
  usePropertyTypesQuery,
} from "../api/property-types";
import {
  usePropertyStatusesQuery,
} from "../api/property-statuses";
import type {
  PropertyListItem,
  PropertyFilters,
  UnidadFormValues,
} from "../types";

interface UnidadesTabProps {
  condominioId: string;
}

/**
 * Tab "Unidades" del DetalleCondominio.
 * Incluye tabla paginada, filtros combinables, acciones individuales y en lote,
 * y Sheet de crear/editar. Consume LOCK-PROPIEDADES-03, LOCK-PROPIEDADES-01, LOCK-PROPIEDADES-02.
 */
export function UnidadesTab({ condominioId }: UnidadesTabProps): ReactNode {
  // ── Filters ──────────────────────────────────────────────────────────
  const [filters, setFilters] = useState<PropertyFilters>({});
  // Debounce search: input changes immediately, query uses debounced value
  const [searchInput, setSearchInput] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const handleSearchChange = useCallback((value: string) => {
    setSearchInput(value);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      setDebouncedSearch(value);
    }, 300);
  }, []);

  // Combined filters for the query — non-search fields immediate, search debounced
  const queryFilters = useMemo<PropertyFilters>(
    () => ({
      ...filters,
      search: debouncedSearch || undefined,
    }),
    [filters, debouncedSearch],
  );

  useEffect(() => {
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, []);

  // ── Queries ─────────────────────────────────────────────────────────
  const {
    data: pagesData,
    isLoading,
    isError,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = usePropertiesInfiniteQuery(condominioId, queryFilters);

  const { data: towers = [] } = useTorresQuery(condominioId);
  const { data: tipos = [] } = usePropertyTypesQuery();
  const { data: estados = [] } = usePropertyStatusesQuery();

  const unidades = useMemo(() => flattenProperties(pagesData?.pages), [pagesData]);

  // ── Mutations ────────────────────────────────────────────────────────
  const createMutation = useCreatePropertyMutation();
  const updateMutation = useUpdatePropertyMutation();
  const deleteMutation = useDeletePropertyMutation();
  const batchStatusMutation = useBatchUpdateStatusMutation();
  const batchDeleteMutation = useBatchDeleteMutation();

  // ── Sheet state ──────────────────────────────────────────────────────
  const [sheetOpen, setSheetOpen] = useState(false);
  const [sheetVersion, setSheetVersion] = useState(0);
  const [editingUnit, setEditingUnit] = useState<PropertyListItem | null>(null);
  // Editing data (with area_m2) — cuando es edición, necesitamos los datos completos
  const [editingUnitDetail, setEditingUnitDetail] = useState<{
    codigo: string;
    tower_id: string | null;
    property_type_id: string;
    property_status_id: string;
    piso: number | null;
    area_m2: number | null;
  } | null>(null);

  // ── Batch selection ──────────────────────────────────────────────────
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());

  const toggleSelect = useCallback((id: string) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  }, []);

  const toggleSelectAll = useCallback(() => {
    setSelectedIds((prev) => {
      if (prev.size === unidades.length && unidades.length > 0) {
        return new Set();
      }
      return new Set(unidades.map((u) => u.id));
    });
  }, [unidades]);

  const clearSelection = useCallback(() => setSelectedIds(new Set()), []);

  // ── Batch dialogs ────────────────────────────────────────────────────
  const [batchStatusOpen, setBatchStatusOpen] = useState(false);
  const [batchStatusId, setBatchStatusId] = useState("");
  const [batchDeleteOpen, setBatchDeleteOpen] = useState(false);

  // ── Delete dialog ────────────────────────────────────────────────────
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deletingUnit, setDeletingUnit] = useState<PropertyListItem | null>(
    null,
  );

  // ── Lookup maps ──────────────────────────────────────────────────────
  const towerMap = useMemo(() => {
    const map = new Map<string, string>();
    for (const t of towers) map.set(t.id, t.nombre);
    return map;
  }, [towers]);

  const tipoMap = useMemo(() => {
    const map = new Map<string, string>();
    for (const t of tipos) map.set(t.id, t.nombre);
    return map;
  }, [tipos]);

  const estadoMap = useMemo(() => {
    const map = new Map<string, string>();
    for (const e of estados) map.set(e.id, e.nombre);
    return map;
  }, [estados]);

  // ── Handlers ─────────────────────────────────────────────────────────
  const handleCreate = useCallback(() => {
    setEditingUnit(null);
    setEditingUnitDetail(null);
    setSheetVersion((v) => v + 1);
    setSheetOpen(true);
  }, []);

  const handleEdit = useCallback(
    (unit: PropertyListItem) => {
      // Para editar necesitamos los datos del listado (sin area_m2).
      // El formulario precarga lo que tenemos; area_m2 queda vacío a menos
      // que el usuario haya hecho un GET detail antes, pero eso es para
      // un bloque futuro. Mientras tanto, usamos null para area_m2.
      setEditingUnit(unit);
      setEditingUnitDetail({
        codigo: unit.codigo,
        tower_id: unit.tower_id,
        property_type_id: unit.property_type_id,
        property_status_id: unit.property_status_id,
        piso: unit.piso,
        area_m2: null, // Listado no expone area_m2 (R-10)
      });
      setSheetVersion((v) => v + 1);
      setSheetOpen(true);
    },
    [],
  );

  const handleSheetSubmit = useCallback(
    (values: UnidadFormValues) => {
      if (editingUnit) {
        // Editar
        updateMutation.mutate(
          {
            id: editingUnit.id,
            condominiumId: condominioId,
            data: {
              codigo: values.codigo,
              tower_id: values.tower_id || null,
              property_type_id: values.property_type_id,
              property_status_id: values.property_status_id,
              piso: values.piso ?? null,
              area_m2: values.area_m2 ?? null,
            },
          },
          {
            onSuccess: () => {
              setSheetOpen(false);
              setEditingUnit(null);
              setEditingUnitDetail(null);
            },
          },
        );
      } else {
        // Crear
        createMutation.mutate(
          {
            condominiumId: condominioId,
            data: {
              codigo: values.codigo,
              tower_id: values.tower_id || null,
              property_type_id: values.property_type_id,
              property_status_id: values.property_status_id,
              piso: values.piso ?? null,
              area_m2: values.area_m2 ?? null,
            },
          },
          {
            onSuccess: () => {
              setSheetOpen(false);
            },
          },
        );
      }
    },
    [editingUnit, condominioId, createMutation, updateMutation],
  );

  const handleDeleteClick = useCallback((unit: PropertyListItem) => {
    setDeletingUnit(unit);
    setDeleteOpen(true);
  }, []);

  const handleDeleteConfirm = useCallback(() => {
    if (!deletingUnit) return;
    deleteMutation.mutate(
      { id: deletingUnit.id, condominiumId: condominioId },
      {
        onSettled: () => {
          setDeleteOpen(false);
          setDeletingUnit(null);
        },
      },
    );
  }, [deletingUnit, condominioId, deleteMutation]);

  // Batch: cambiar estado
  const handleBatchStatus = useCallback(() => {
    if (!batchStatusId) return;
    const items = unidades
      .filter((u) => selectedIds.has(u.id))
      .map((u) => ({ id: u.id, codigo: u.codigo }));
    batchStatusMutation.mutate(
      { condominiumId: condominioId, items, statusId: batchStatusId },
      {
        onSettled: () => {
          setBatchStatusOpen(false);
          setBatchStatusId("");
          clearSelection();
        },
      },
    );
  }, [
    batchStatusId,
    unidades,
    selectedIds,
    condominioId,
    batchStatusMutation,
    clearSelection,
  ]);

  // Batch: eliminar
  const handleBatchDelete = useCallback(() => {
    const items = unidades
      .filter((u) => selectedIds.has(u.id))
      .map((u) => ({ id: u.id, codigo: u.codigo }));
    batchDeleteMutation.mutate(
      { condominiumId: condominioId, items },
      {
        onSettled: () => {
          setBatchDeleteOpen(false);
          clearSelection();
        },
      },
    );
  }, [unidades, selectedIds, condominioId, batchDeleteMutation, clearSelection]);

  const statusOptions = useMemo(
    () => estados.map((e) => ({ value: e.id, label: e.nombre })),
    [estados],
  );

  const isAllSelected =
    unidades.length > 0 && selectedIds.size === unidades.length;
  const selectedCount = selectedIds.size;
  const sheetIsSubmitting = createMutation.isPending || updateMutation.isPending;
  const selectedItems = useMemo(
    () => unidades.filter((u) => selectedIds.has(u.id)),
    [unidades, selectedIds],
  );

  // ── Render ───────────────────────────────────────────────────────────
  return (
    <div className="space-y-4 pt-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <p className="text-sm text-muted-foreground">
          {isLoading
            ? "Cargando unidades..."
            : unidades.length === 0
              ? "No hay unidades registradas."
              : `${unidades.length} ${unidades.length === 1 ? "unidad" : "unidades"}${hasNextPage ? "+" : ""}`}
        </p>
        <Button size="sm" onClick={handleCreate}>
          <Plus className="mr-1.5 h-4 w-4" />
          Nueva unidad
        </Button>
      </div>

      {/* Filters */}
      <FiltrosUnidades
        filters={filters}
        searchInput={searchInput}
        onFilterChange={setFilters}
        onSearchChange={handleSearchChange}
        towers={towers}
        tipos={tipos}
        estados={estados}
      />

      {/* Batch actions */}
      {selectedCount > 0 ? (
        <div className="flex items-center gap-2 rounded-md border bg-muted/30 px-3 py-2">
          <span className="text-sm text-muted-foreground">
            {selectedCount} {selectedCount === 1 ? "seleccionada" : "seleccionadas"}
          </span>
          <Button
            variant="outline"
            size="sm"
            onClick={() => setBatchStatusOpen(true)}
          >
            Cambiar estado
          </Button>
          <Button
            variant="outline"
            size="sm"
            className="text-destructive hover:text-destructive"
            onClick={() => setBatchDeleteOpen(true)}
          >
            <Trash2 className="mr-1 h-3.5 w-3.5" />
            Eliminar seleccionadas
          </Button>
        </div>
      ) : null}

      {/* Content */}
      {isLoading ? (
        <div className="flex items-center justify-center py-16">
          <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
        </div>
      ) : isError ? (
        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed py-16 text-center">
          <AlertTriangle className="mb-4 h-10 w-10 text-destructive/60" />
          <p className="text-sm text-muted-foreground">
            Error al cargar las unidades.
          </p>
        </div>
      ) : unidades.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed py-16 text-center">
          <Home className="mb-3 h-8 w-8 text-muted-foreground/50" />
          <p className="text-sm text-muted-foreground">
            No hay unidades registradas en este condominio.
          </p>
          <Button variant="outline" className="mt-4" onClick={handleCreate}>
            <Plus className="mr-2 h-4 w-4" />
            Crear primera unidad
          </Button>
        </div>
      ) : (
        <>
          <div className="rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-[40px]">
                    <Checkbox
                      checked={isAllSelected}
                      onChange={toggleSelectAll}
                      aria-label="Seleccionar todas"
                    />
                  </TableHead>
                  <TableHead>Código</TableHead>
                  <TableHead>Torre</TableHead>
                  <TableHead>Tipo</TableHead>
                  <TableHead>Estado</TableHead>
                  <TableHead className="w-[100px]">Piso</TableHead>
                  <TableHead className="w-[100px] text-right">Acciones</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {unidades.map((unit) => (
                  <TableRow key={unit.id}>
                    <TableCell>
                      <Checkbox
                        checked={selectedIds.has(unit.id)}
                        onChange={() => toggleSelect(unit.id)}
                        aria-label={`Seleccionar ${unit.codigo}`}
                      />
                    </TableCell>
                    <TableCell className="font-medium">
                      {unit.codigo}
                    </TableCell>
                    <TableCell className="text-muted-foreground">
                      {unit.tower_id
                        ? (towerMap.get(unit.tower_id) ?? "—")
                        : "—"}
                    </TableCell>
                    <TableCell className="text-muted-foreground">
                      {tipoMap.get(unit.property_type_id) ?? "—"}
                    </TableCell>
                    <TableCell className="text-muted-foreground">
                      {estadoMap.get(unit.property_status_id) ?? "—"}
                    </TableCell>
                    <TableCell className="text-muted-foreground">
                      {unit.piso ?? "—"}
                    </TableCell>
                    <TableCell className="text-right">
                      <div className="flex items-center justify-end gap-1">
                        <Button
                          variant="ghost"
                          size="icon"
                          onClick={() => handleEdit(unit)}
                          title="Editar unidad"
                        >
                          <Pencil className="h-4 w-4" />
                          <span className="sr-only">Editar</span>
                        </Button>
                        <Button
                          variant="ghost"
                          size="icon"
                          onClick={() => handleDeleteClick(unit)}
                          title="Eliminar unidad"
                          className="text-destructive hover:text-destructive"
                        >
                          <Trash2 className="h-4 w-4" />
                          <span className="sr-only">Eliminar</span>
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>

          {/* Load more / pagination */}
          {hasNextPage ? (
            <div className="flex justify-center pt-2">
              <Button
                variant="ghost"
                size="sm"
                onClick={() => fetchNextPage()}
                disabled={isFetchingNextPage}
              >
                {isFetchingNextPage ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Cargando...
                  </>
                ) : (
                  "Cargar más"
                )}
              </Button>
            </div>
          ) : null}
        </>
      )}

      {/* ── Unidad Sheet ────────────────────────────────────────────── */}
      <UnidadSheet
        key={`${editingUnit?.id ?? "new"}-${sheetVersion}`}
        open={sheetOpen}
        onOpenChange={setSheetOpen}
        item={
          editingUnitDetail
            ? {
                id: editingUnit?.id ?? "",
                condominium_id: condominioId,
                tower_id: editingUnitDetail.tower_id,
                property_type_id: editingUnitDetail.property_type_id,
                property_status_id: editingUnitDetail.property_status_id,
                codigo: editingUnitDetail.codigo,
                piso: editingUnitDetail.piso,
                area_m2: editingUnitDetail.area_m2,
                created_by: null,
                updated_by: null,
                created_at: "",
                updated_at: "",
              }
            : null
        }
        towers={towers}
        tipos={tipos}
        estados={estados}
        isSubmitting={sheetIsSubmitting}
        onSubmit={handleSheetSubmit}
      />

      {/* ── Delete dialog ───────────────────────────────────────────── */}
      <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
        <DialogContent className="sm:max-w-[440px]">
          <DialogHeader>
            <div className="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-destructive/10">
              <AlertTriangle className="h-6 w-6 text-destructive" />
            </div>
            <DialogTitle className="text-center">Eliminar unidad</DialogTitle>
            <DialogDescription className="text-center">
              ¿Estás seguro de eliminar{" "}
              <span className="font-semibold text-foreground">
                &quot;{deletingUnit?.codigo}&quot;
              </span>
              ? Esta acción no se puede deshacer.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setDeleteOpen(false)}
              disabled={deleteMutation.isPending}
            >
              Cancelar
            </Button>
            <Button
              variant="destructive"
              onClick={handleDeleteConfirm}
              disabled={deleteMutation.isPending}
            >
              {deleteMutation.isPending ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Eliminando...
                </>
              ) : (
                "Eliminar"
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* ── Batch status dialog ─────────────────────────────────────── */}
      <Dialog open={batchStatusOpen} onOpenChange={setBatchStatusOpen}>
        <DialogContent className="sm:max-w-[440px]">
          <DialogHeader>
            <DialogTitle>Cambiar estado</DialogTitle>
            <DialogDescription>
              Selecciona el nuevo estado para{" "}
              <span className="font-semibold">{selectedCount}</span>{" "}
              {selectedCount === 1 ? "unidad" : "unidades"}.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label htmlFor="batch-status">Nuevo estado</Label>
              <Select
                id="batch-status"
                options={statusOptions}
                placeholder="Seleccionar estado..."
                value={batchStatusId}
                onChange={(e) => setBatchStatusId(e.target.value)}
              />
            </div>
            {/* Preview */}
            {selectedItems.length > 0 ? (
              <div className="text-sm text-muted-foreground">
                <p className="font-medium text-foreground">Unidades:</p>
                <ul className="mt-1 list-inside list-disc">
                  {selectedItems.slice(0, 5).map((u) => (
                    <li key={u.id}>{u.codigo}</li>
                  ))}
                  {selectedItems.length > 5 ? (
                    <li>...y {selectedItems.length - 5} más</li>
                  ) : null}
                </ul>
              </div>
            ) : null}
          </div>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setBatchStatusOpen(false)}
              disabled={batchStatusMutation.isPending}
            >
              Cancelar
            </Button>
            <Button
              onClick={handleBatchStatus}
              disabled={!batchStatusId || batchStatusMutation.isPending}
            >
              {batchStatusMutation.isPending ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Aplicando...
                </>
              ) : (
                "Aplicar"
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* ── Batch delete dialog ─────────────────────────────────────── */}
      <Dialog open={batchDeleteOpen} onOpenChange={setBatchDeleteOpen}>
        <DialogContent className="sm:max-w-[440px]">
          <DialogHeader>
            <div className="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-destructive/10">
              <AlertTriangle className="h-6 w-6 text-destructive" />
            </div>
            <DialogTitle className="text-center">
              Eliminar unidades seleccionadas
            </DialogTitle>
            <DialogDescription className="text-center">
              ¿Estás seguro de eliminar{" "}
              <span className="font-semibold">{selectedCount}</span>{" "}
              {selectedCount === 1 ? "unidad" : "unidades"}? Las que tengan
              ocupantes activos no se eliminarán.
            </DialogDescription>
          </DialogHeader>
          {selectedItems.length > 0 ? (
            <div className="text-sm text-muted-foreground">
              <ul className="list-inside list-disc">
                {selectedItems.slice(0, 5).map((u) => (
                  <li key={u.id}>{u.codigo}</li>
                ))}
                {selectedItems.length > 5 ? (
                  <li>...y {selectedItems.length - 5} más</li>
                ) : null}
              </ul>
            </div>
          ) : null}
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setBatchDeleteOpen(false)}
              disabled={batchDeleteMutation.isPending}
            >
              Cancelar
            </Button>
            <Button
              variant="destructive"
              onClick={handleBatchDelete}
              disabled={batchDeleteMutation.isPending}
            >
              {batchDeleteMutation.isPending ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Eliminando...
                </>
              ) : (
                "Eliminar"
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
