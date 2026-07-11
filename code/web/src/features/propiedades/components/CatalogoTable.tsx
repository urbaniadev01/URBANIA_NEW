import type { ReactNode } from "react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Pencil, Plus, Trash2, Building2 } from "lucide-react";
import { PageHeader } from "@/components/page-header";
import { EmptyState } from "@/components/empty-state";
import { LoadingState } from "@/components/loading-state";
import { PAGE_CONTAINER } from "@/lib/layout";
import type { CatalogoItem } from "../types";
import { isSystemCatalog } from "../types";

interface CatalogoTableProps {
  items: CatalogoItem[];
  isLoading: boolean;
  title: string;
  onCreate: () => void;
  onEdit: (item: CatalogoItem) => void;
  onDelete: (item: CatalogoItem) => void;
}

/**
 * Tabla compartida para catálogos (tipos y estados de propiedad).
 * Muestra columnas: nombre, descripción, origen, acciones.
 * Los catálogos del sistema muestran badge "Sistema" y no tienen acciones.
 */
export function CatalogoTable({
  items,
  isLoading,
  title,
  onCreate,
  onEdit,
  onDelete,
}: CatalogoTableProps): ReactNode {
  return (
    <div className={PAGE_CONTAINER}>
      <PageHeader
        title={title}
        description="Gestiona los catálogos de tu organización."
        actions={
          <Button onClick={onCreate}>
            <Plus className="mr-2 h-4 w-4" />
            Nuevo
          </Button>
        }
      />

      {/* Content */}
      {isLoading ? (
        <LoadingState />
      ) : items.length === 0 ? (
        <EmptyState
          icon={Building2}
          message="No hay elementos registrados."
          action={
            <Button variant="outline" onClick={onCreate}>
              <Plus className="mr-2 h-4 w-4" />
              Crear primero
            </Button>
          }
        />
      ) : (
        <div className="rounded-md border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-[200px]">Nombre</TableHead>
                <TableHead>Descripción</TableHead>
                <TableHead className="w-[130px]">Origen</TableHead>
                <TableHead className="w-[120px] text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {items.map((item) => (
                <TableRow key={item.id}>
                  <TableCell className="font-medium">{item.nombre}</TableCell>
                  <TableCell className="max-w-[300px] truncate text-muted-foreground">
                    {item.descripcion || "—"}
                  </TableCell>
                  <TableCell>
                    {isSystemCatalog(item) ? (
                      <Badge variant="info">Sistema</Badge>
                    ) : (
                      <Badge variant="success">Personalizado</Badge>
                    )}
                  </TableCell>
                  <TableCell className="text-right">
                    {isSystemCatalog(item) ? (
                      <span className="text-xs text-muted-foreground">
                        Solo lectura
                      </span>
                    ) : (
                      <div className="flex items-center justify-end gap-1">
                        <Button
                          variant="ghost"
                          size="icon"
                          onClick={() => onEdit(item)}
                          title="Editar"
                        >
                          <Pencil className="h-4 w-4" />
                          <span className="sr-only">Editar</span>
                        </Button>
                        <Button
                          variant="ghost"
                          size="icon"
                          onClick={() => onDelete(item)}
                          title="Eliminar"
                          className="text-destructive hover:text-destructive"
                        >
                          <Trash2 className="h-4 w-4" />
                          <span className="sr-only">Eliminar</span>
                        </Button>
                      </div>
                    )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}
    </div>
  );
}
