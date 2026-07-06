import { useState } from "react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Toaster } from "@/components/ui/sonner";
import { Terminal, CheckCircle } from "lucide-react";

const sampleUsers = [
  { id: 1, name: "María García", role: "Administradora", status: "Activo" },
  { id: 2, name: "Carlos López", role: "Vigilante", status: "Activo" },
  { id: 3, name: "Ana Martínez", role: "Residente", status: "Inactivo" },
];

export function TestPage(): React.ReactNode {
  const [inputValue, setInputValue] = useState("");

  return (
    <div className="min-h-screen bg-background p-8">
      <div className="mx-auto max-w-2xl space-y-8">
        {/* Header */}
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Urbania Bootstrap</h1>
          <p className="text-muted-foreground mt-1">
            WEB_BOOTSTRAP-B01 — Vite + React 19 + shadcn/ui verificado.
          </p>
        </div>

        {/* Alert */}
        <Alert>
          <Terminal className="h-4 w-4" />
          <AlertTitle>Bootstrap exitoso</AlertTitle>
          <AlertDescription>
            Todos los componentes base de shadcn/ui están instalados y renderizando con el tema definido.
          </AlertDescription>
        </Alert>

        {/* Card with form input */}
        <Card>
          <CardHeader>
            <CardTitle>Formulario de prueba</CardTitle>
            <CardDescription>
              Campo de texto vinculado al tema con foco visible y mensaje de validación.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="test-input">Nombre de prueba</Label>
              <Input
                id="test-input"
                placeholder="Escribe algo aquí..."
                value={inputValue}
                onChange={(e) => setInputValue(e.target.value)}
              />
            </div>
            <p className="text-sm text-muted-foreground">
              {inputValue
                ? `Valor actual: "${inputValue}"`
                : "El campo está vacío."}
            </p>
          </CardContent>
          <CardFooter className="flex gap-2">
            <Button
              onClick={() => {
                toast.success("¡Funciona!", {
                  description: inputValue
                    ? `Recibido: ${inputValue}`
                    : "El campo estaba vacío, pero el toast funciona igual.",
                });
              }}
            >
              <CheckCircle className="h-4 w-4" />
              Probar Toast
            </Button>
            <Button variant="outline" onClick={() => setInputValue("")}>
              Limpiar
            </Button>
          </CardFooter>
        </Card>

        {/* Table */}
        <Card>
          <CardHeader>
            <CardTitle>Usuarios de ejemplo</CardTitle>
            <CardDescription>
              Tabla con filas hoverables y cabecera muted.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Nombre</TableHead>
                  <TableHead>Rol</TableHead>
                  <TableHead>Estado</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {sampleUsers.map((user) => (
                  <TableRow key={user.id}>
                    <TableCell className="font-medium">{user.name}</TableCell>
                    <TableCell>{user.role}</TableCell>
                    <TableCell>{user.status}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>

        {/* Dialog */}
        <Dialog>
          <DialogTrigger asChild>
            <Button variant="secondary">Abrir modal de prueba</Button>
          </DialogTrigger>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Modal de prueba</DialogTitle>
              <DialogDescription>
                Este modal usa @radix-ui/react-dialog. Debería poder cerrarse con Escape y tener foco atrapado.
              </DialogDescription>
            </DialogHeader>
            <div className="space-y-4 py-4">
              <p className="text-sm text-muted-foreground">
                Probá navegar con Tab para verificar que el foco queda atrapado dentro del modal.
                Presioná Escape para cerrarlo.
              </p>
              <Input placeholder="Campo dentro del modal..." />
            </div>
            <DialogFooter>
              <DialogClose asChild>
                <Button variant="outline">Cancelar</Button>
              </DialogClose>
              <DialogClose asChild>
                <Button>Cerrar</Button>
              </DialogClose>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>

      <Toaster richColors closeButton />
    </div>
  );
}
