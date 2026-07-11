import { useState, useCallback, useMemo } from "react";
import { useParams, useNavigate, useSearchParams, Link } from "react-router-dom";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Loader2,
  Plus,
  Pencil,
  Trash2,
  Building2,
  ChevronRight,
  Home,
  AlertTriangle,
} from "lucide-react";
import { EmptyState } from "@/components/empty-state";
import { LoadingState } from "@/components/loading-state";
import { PAGE_CONTAINER } from "@/lib/layout";
import { CondominioSheet } from "../components/CondominioSheet";
import { TorreSheet } from "../components/TorreSheet";
import { UnidadesTab } from "../components/UnidadesTab";
import { CoeficientesTab } from "../components/CoeficientesTab";
import {
  useCondominioQuery,
  useUpdateCondominioMutation,
  useDeleteCondominioMutation,
} from "../api/condominiums";
import {
  useCreateTorreMutation,
  useUpdateTorreMutation,
  useDeleteTorreMutation,
} from "../api/towers";
import type {
  CondominioFormValues,
  TorreFormValues,
  TorreItem,
} from "../types";
import type { ApiError } from "@/types/api-error";

/**
 * Página de detalle de condominio — ruta /condominios/{id}.
 * Layout con breadcrumb y dos tabs: Torres y Configuración.
 * Consume LOCK-PROPIEDADES-02.
 */
export function DetalleCondominioPage(): React.ReactNode {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();

  // ── Tab routing ────────────────────────────────────────────────────
  const activeTab = useMemo(
    () => searchParams.get("tab") || "torres",
    [searchParams],
  );

  const handleTabChange = useCallback(
    (value: string) => {
      setSearchParams(value === "torres" ? {} : { tab: value }, { replace: true });
    },
    [setSearchParams],
  );

  // ── Queries ─────────────────────────────────────────────────────────

  const { data, isLoading, isError } = useCondominioQuery(id);
  const condominio = data?.condominium;
  const towers = condominio?.towers ?? [];

  // ── Condominio mutations ────────────────────────────────────────────

  const updateCondominio = useUpdateCondominioMutation();
  const deleteCondominio = useDeleteCondominioMutation();

  // ── Torre mutations ─────────────────────────────────────────────────

  const createTorre = useCreateTorreMutation();
  const updateTorre = useUpdateTorreMutation();
  const deleteTorre = useDeleteTorreMutation();

  // ── Sheet state ─────────────────────────────────────────────────────

  const [condominioSheetOpen, setCondominioSheetOpen] = useState(false);
  const [condominioSheetVersion, setCondominioSheetVersion] = useState(0);
  const [torreSheetOpen, setTorreSheetOpen] = useState(false);
  const [torreSheetVersion, setTorreSheetVersion] = useState(0);
  const [editingTorre, setEditingTorre] = useState<TorreItem | null>(null);

  // ── Delete dialogs ──────────────────────────────────────────────────

  const [deleteCondominioOpen, setDeleteCondominioOpen] = useState(false);
  const [deleteTorreOpen, setDeleteTorreOpen] = useState(false);
  const [deletingTorre, setDeletingTorre] = useState<TorreItem | null>(null);
  const [deleteCondominioError, setDeleteCondominioError] = useState<
    string | null
  >(null);

  // ── Handlers: condominio ────────────────────────────────────────────

  const handleCondominioSubmit = useCallback(
    (values: CondominioFormValues) => {
      if (!id) return;
      updateCondominio.mutate(
        {
          id,
          data: {
            nombre: values.nombre || undefined,
            direccion: values.direccion || undefined,
            nit: values.nit || undefined,
          },
        },
        {
          onSuccess: () => setCondominioSheetOpen(false),
        },
      );
    },
    [id, updateCondominio],
  );

  const handleDeleteCondominioConfirm = useCallback(() => {
    if (!id) return;
    deleteCondominio.mutate(id, {
      onSuccess: () => {
        setDeleteCondominioOpen(false);
        navigate("/condominios");
      },
      onError: (error: ApiError) => {
        if (
          error.code === "CONDOMINIUM_HAS_TOWERS" ||
          error.code === "CONDOMINIUM_HAS_PROPERTIES"
        ) {
          setDeleteCondominioError(
            error.message ||
              "No se puede eliminar: el condominio tiene torres o propiedades activas.",
          );
        } else {
          setDeleteCondominioOpen(false);
        }
      },
    });
  }, [id, deleteCondominio, navigate]);

  // ── Handlers: torres ────────────────────────────────────────────────

  const handleCreateTorre = useCallback(() => {
    setEditingTorre(null);
    setTorreSheetVersion((v) => v + 1);
    setTorreSheetOpen(true);
  }, []);

  const handleEditTorre = useCallback((torre: TorreItem) => {
    setEditingTorre(torre);
    setTorreSheetVersion((v) => v + 1);
    setTorreSheetOpen(true);
  }, []);

  const handleTorreSubmit = useCallback(
    (values: TorreFormValues) => {
      if (!id) return;
      if (editingTorre) {
        updateTorre.mutate(
          {
            id: editingTorre.id,
            condominiumId: id,
            data: { nombre: values.nombre },
          },
          {
            onSuccess: () => setTorreSheetOpen(false),
          },
        );
      } else {
        createTorre.mutate(
          {
            condominiumId: id,
            data: { nombre: values.nombre },
          },
          {
            onSuccess: () => setTorreSheetOpen(false),
          },
        );
      }
    },
    [id, editingTorre, createTorre, updateTorre],
  );

  const handleDeleteTorreClick = useCallback((torre: TorreItem) => {
    setDeletingTorre(torre);
    setDeleteTorreOpen(true);
  }, []);

  const handleDeleteTorreConfirm = useCallback(() => {
    if (!deletingTorre || !id) return;
    deleteTorre.mutate(
      { id: deletingTorre.id, condominiumId: id },
      {
        onSuccess: () => {
          setDeleteTorreOpen(false);
          setDeletingTorre(null);
        },
        onError: () => {
          // Toast ya lo muestra el hook — pero no cerramos el diálogo en 409
          // para que el usuario vea el toast. Cerramos igual.
          setDeleteTorreOpen(false);
          setDeletingTorre(null);
        },
      },
    );
  }, [deletingTorre, id, deleteTorre]);

  // ── Loading / error states ──────────────────────────────────────────

  if (isLoading) {
    return (
      <div className={PAGE_CONTAINER}>
        <LoadingState />
      </div>
    );
  }

  if (isError || !condominio) {
    return (
      <div className={PAGE_CONTAINER}>
        <EmptyState
          icon={AlertTriangle}
          message="El condominio no existe o no tienes acceso."
          action={
            <Button variant="outline" onClick={() => navigate("/condominios")}>
              Volver a la lista
            </Button>
          }
        />
      </div>
    );
  }

  const hasChildren = towers.length > 0;
  const isDeletingCondominio = deleteCondominio.isPending;
  const torreSheetIsSubmitting =
    createTorre.isPending || updateTorre.isPending;

  // ── Render ──────────────────────────────────────────────────────────

  return (
    <div className={PAGE_CONTAINER}>
      {/* Breadcrumb */}
      <nav className="mb-6 flex items-center gap-2 text-sm text-muted-foreground">
        <Link
          to="/condominios"
          className="flex items-center gap-1 hover:text-foreground"
        >
          <Home className="h-3.5 w-3.5" />
          Condominios
        </Link>
        <ChevronRight className="h-3.5 w-3.5" />
        <span className="font-medium text-foreground">
          {condominio.nombre}
        </span>
      </nav>

      {/* Header */}
      <div className="mb-6">
        <h1 className="text-2xl font-bold tracking-tight">
          {condominio.nombre}
        </h1>
        {condominio.direccion ? (
          <p className="mt-1 text-sm text-muted-foreground">
            {condominio.direccion}
          </p>
        ) : null}
        {condominio.nit ? (
          <p className="text-sm text-muted-foreground">
            NIT: {condominio.nit}
          </p>
        ) : null}
      </div>

      {/* Tabs */}
      <Tabs value={activeTab} onValueChange={handleTabChange} className="w-full">
        <TabsList>
          <TabsTrigger value="torres">
            Torres
            {towers.length > 0 ? (
              <span className="ml-1.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-muted-foreground/15 px-1 text-xs">
                {towers.length}
              </span>
            ) : null}
          </TabsTrigger>
          <TabsTrigger value="unidades">
            Unidades
          </TabsTrigger>
          <TabsTrigger value="coeficientes">
            Coeficientes
          </TabsTrigger>
          <TabsTrigger value="configuracion">Configuración</TabsTrigger>
        </TabsList>

        {/* ── Tab: Torres ──────────────────────────────────────────── */}
        <TabsContent value="torres" className="space-y-4 pt-4">
          <div className="flex items-center justify-between">
            <p className="text-sm text-muted-foreground">
              {towers.length === 0
                ? "No hay torres registradas."
                : `${towers.length} ${towers.length === 1 ? "torre" : "torres"} registradas`}
            </p>
            <Button size="sm" onClick={handleCreateTorre}>
              <Plus className="mr-1.5 h-4 w-4" />
              Nueva torre
            </Button>
          </div>

          {towers.length === 0 ? (
            <div className="flex flex-col items-center justify-center rounded-lg border border-dashed py-12 text-center">
              <Building2 className="mb-3 h-8 w-8 text-muted-foreground/50" />
              <p className="text-sm text-muted-foreground">
                Agrega torres para organizar las unidades del condominio.
              </p>
            </div>
          ) : (
            <div className="space-y-2">
              {towers.map((torre) => (
                <div
                  key={torre.id}
                  className="flex items-center justify-between rounded-lg border p-4"
                >
                  <div>
                    <p className="font-medium">{torre.nombre}</p>
                    <p className="text-xs text-muted-foreground">
                      ID: {torre.id}
                    </p>
                  </div>
                  <div className="flex items-center gap-1">
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => handleEditTorre(torre)}
                      title="Editar torre"
                    >
                      <Pencil className="h-4 w-4" />
                      <span className="sr-only">Editar</span>
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => handleDeleteTorreClick(torre)}
                      title="Eliminar torre"
                      className="text-destructive hover:text-destructive"
                    >
                      <Trash2 className="h-4 w-4" />
                      <span className="sr-only">Eliminar</span>
                    </Button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </TabsContent>

        {/* ── Tab: Unidades ────────────────────────────────────────── */}
        <TabsContent value="unidades" className="pt-4">
          {id ? <UnidadesTab condominioId={id} /> : null}
        </TabsContent>

        {/* ── Tab: Coeficientes ─────────────────────────────────────── */}
        <TabsContent value="coeficientes" className="pt-4">
          {id ? <CoeficientesTab condominioId={id} /> : null}
        </TabsContent>

        {/* ── Tab: Configuración ────────────────────────────────────── */}
        <TabsContent value="configuracion" className="space-y-6 pt-4">
          {/* Edit form */}
          <div className="rounded-lg border p-6">
            <h3 className="mb-4 text-lg font-semibold">
              Datos del condominio
            </h3>
            <div className="space-y-4">
              <div>
                <Label htmlFor="edit-nombre">Nombre</Label>
                <Input
                  id="edit-nombre"
                  value={condominio.nombre}
                  readOnly
                  className="mt-1.5 bg-muted/50"
                />
              </div>
              <div>
                <Label htmlFor="edit-direccion">Dirección</Label>
                <Input
                  id="edit-direccion"
                  value={condominio.direccion ?? "—"}
                  readOnly
                  className="mt-1.5 bg-muted/50"
                />
              </div>
              <div>
                <Label htmlFor="edit-nit">NIT</Label>
                <Input
                  id="edit-nit"
                  value={condominio.nit ?? "—"}
                  readOnly
                  className="mt-1.5 bg-muted/50"
                />
              </div>
              <Button
                variant="outline"
                onClick={() => {
                  setCondominioSheetVersion((v) => v + 1);
                  setCondominioSheetOpen(true);
                }}
              >
                <Pencil className="mr-2 h-4 w-4" />
                Editar condominio
              </Button>
            </div>
          </div>

          {/* Danger zone */}
          <div className="rounded-lg border border-destructive/30 p-6">
            <h3 className="mb-2 text-lg font-semibold text-destructive">
              Zona de peligro
            </h3>
            <p className="mb-4 text-sm text-muted-foreground">
              Eliminar este condominio es irreversible. Solo es posible si no
              tiene torres ni propiedades asociadas.
            </p>
            <Button
              variant="destructive"
              onClick={() => {
                setDeleteCondominioError(null);
                setDeleteCondominioOpen(true);
              }}
              disabled={hasChildren}
              title={
                hasChildren
                  ? "Elimina las torres y propiedades primero"
                  : undefined
              }
            >
              <Trash2 className="mr-2 h-4 w-4" />
              Eliminar condominio
            </Button>
            {hasChildren ? (
              <p className="mt-2 text-xs text-muted-foreground">
                Debes eliminar todas las torres antes de poder eliminar el
                condominio.
              </p>
            ) : null}
          </div>
        </TabsContent>
      </Tabs>

      {/* ── Sheets ─────────────────────────────────────────────────── */}

      <CondominioSheet
        key={`${condominio.id}-${condominioSheetVersion}`}
        open={condominioSheetOpen}
        onOpenChange={setCondominioSheetOpen}
        item={{
          id: condominio.id,
          organization_id: condominio.organization_id,
          nombre: condominio.nombre,
          direccion: condominio.direccion,
          nit: condominio.nit,
          created_by: condominio.created_by,
          updated_by: condominio.updated_by,
          created_at: condominio.created_at,
          updated_at: condominio.updated_at,
        }}
        isSubmitting={updateCondominio.isPending}
        onSubmit={handleCondominioSubmit}
      />

      <TorreSheet
        key={`${editingTorre?.id ?? "new-torre"}-${torreSheetVersion}`}
        open={torreSheetOpen}
        onOpenChange={setTorreSheetOpen}
        item={editingTorre}
        condominioNombre={condominio.nombre}
        isSubmitting={torreSheetIsSubmitting}
        onSubmit={handleTorreSubmit}
      />

      {/* ── Delete dialogs ──────────────────────────────────────────── */}

      {/* Delete torre */}
      <Dialog open={deleteTorreOpen} onOpenChange={setDeleteTorreOpen}>
        <DialogContent className="sm:max-w-[440px]">
          <DialogHeader>
            <div className="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-destructive/10">
              <AlertTriangle className="h-6 w-6 text-destructive" />
            </div>
            <DialogTitle className="text-center">Eliminar torre</DialogTitle>
            <DialogDescription className="text-center">
              ¿Estás seguro de eliminar{" "}
              <span className="font-semibold text-foreground">
                &quot;{deletingTorre?.nombre}&quot;
              </span>
              ? Esta acción no se puede deshacer.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setDeleteTorreOpen(false)}
              disabled={deleteTorre.isPending}
            >
              Cancelar
            </Button>
            <Button
              variant="destructive"
              onClick={handleDeleteTorreConfirm}
              disabled={deleteTorre.isPending}
            >
              {deleteTorre.isPending ? (
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

      {/* Delete condominio */}
      <Dialog
        open={deleteCondominioOpen}
        onOpenChange={setDeleteCondominioOpen}
      >
        <DialogContent className="sm:max-w-[440px]">
          <DialogHeader>
            <div className="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-destructive/10">
              <AlertTriangle className="h-6 w-6 text-destructive" />
            </div>
            <DialogTitle className="text-center">
              Eliminar condominio
            </DialogTitle>
            <DialogDescription className="text-center">
              ¿Estás seguro de eliminar{" "}
              <span className="font-semibold text-foreground">
                &quot;{condominio.nombre}&quot;
              </span>
              ? Esta acción no se puede deshacer.
            </DialogDescription>
          </DialogHeader>

          {deleteCondominioError ? (
            <div className="rounded-md border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
              {deleteCondominioError}
            </div>
          ) : null}

          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setDeleteCondominioOpen(false)}
              disabled={isDeletingCondominio}
            >
              Cancelar
            </Button>
            <Button
              variant="destructive"
              onClick={handleDeleteCondominioConfirm}
              disabled={isDeletingCondominio}
            >
              {isDeletingCondominio ? (
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
