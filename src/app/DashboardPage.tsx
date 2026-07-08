import { useAuthStore } from "@/stores/auth-store";
import { useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { LogOut, ShieldCheck } from "lucide-react";

/**
 * Dashboard — página principal post-autenticación.
 * Reemplaza a TestPage como ruta raíz (/) y sirve como destino
 * unificado post-login y post-MFA-verify.
 */
export function DashboardPage(): React.ReactNode {
  const accessToken = useAuthStore((s) => s.accessToken);
  const clearAccessToken = useAuthStore((s) => s.clearAccessToken);
  const navigate = useNavigate();

  const handleLogout = () => {
    clearAccessToken();
    navigate("/login");
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-background p-8">
      <Card className="w-full max-w-md">
        <CardHeader className="text-center">
          <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
            <ShieldCheck className="h-6 w-6 text-primary" />
          </div>
          <CardTitle className="text-2xl">Dashboard</CardTitle>
          <CardDescription>
            {accessToken
              ? "Has iniciado sesión correctamente."
              : "No se detectó sesión activa."}
          </CardDescription>
        </CardHeader>
        <CardContent className="flex justify-center">
          <Button variant="outline" onClick={handleLogout}>
            <LogOut className="mr-2 h-4 w-4" />
            Cerrar sesión
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
