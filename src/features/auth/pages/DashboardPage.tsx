import { useAuthStore } from "@/stores/auth-store";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { LogOut } from "lucide-react";
import { useNavigate } from "react-router-dom";

export function DashboardPage(): React.ReactNode {
  const accessToken = useAuthStore((s) => s.accessToken);
  const clearAccessToken = useAuthStore((s) => s.clearAccessToken);
  const navigate = useNavigate();

  function handleLogout(): void {
    clearAccessToken();
    navigate("/login");
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-muted/50 px-4">
      <Card className="w-full max-w-md">
        <CardHeader className="space-y-1">
          <CardTitle className="text-2xl font-bold text-center">
            Urbania
          </CardTitle>
          <CardDescription className="text-center">
            Panel de administración
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <p className="text-sm text-muted-foreground text-center">
            Has iniciado sesión correctamente.
          </p>
          {accessToken && (
            <p className="text-xs text-muted-foreground text-center break-all font-mono">
              Token: {accessToken.slice(0, 20)}...
            </p>
          )}
          <Button
            variant="outline"
            className="w-full"
            onClick={handleLogout}
          >
            <LogOut className="mr-2 h-4 w-4" />
            Cerrar sesión
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
