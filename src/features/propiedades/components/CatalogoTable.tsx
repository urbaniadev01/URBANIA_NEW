import type { ReactNode } from "react";
import { Button } from "@/components/ui/button";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Loader2, Pencil, Plus, Trash2, Building2 } from "lucide-react";
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
    <div className="container mx-auto max-w-5xl px-8 py-8">
      {/* Header */}
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
          <p className="text-sm text-muted-foreground">
            Gestiona los catálogos de tu organización.
          </p>
        </div>
        <Button onClick={onCreate}>
          <Plus className="mr-2 h-4 w-4" />
          Nuevo
        </Button>
      </div>

      {/* Content */}
      {isLoading ? (
        <div className="flex items-center justify-center py-16">
          <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
        </div>
      ) : items.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed py-16 text-center">
          <Building2 className="mb-4 h-10 w-10 text-muted-foreground/60" />
          <p className="text-sm text-muted-foreground">
            No hay elementos registrados.
          </p>
          <Button variant="outline" className="mt-4" onClick={onCreate}>
            <Plus className="mr-2 h-4 w-4" />
            Crear primero
          </Button>
        </div>
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
                      <span className="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">
                        Sistema
                      </span>
                    ) : (
                      <span className="inline-flex items-center rounded-full border border-green-200 bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700">
                        Personalizado
                      </span>
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
