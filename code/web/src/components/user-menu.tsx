import type { ReactNode } from "react";
import { LogOut } from "lucide-react";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useLogoutMutation } from "@/features/auth/api/logout";
import type { AuthUser } from "@/features/dashboard/types";

/**
 * `name` puede ser `null` (usuario sin contacto asociado — ver
 * api/endpoints/AUTH.md GET /auth/me) aunque AuthUser lo tipe como string;
 * se resuelve con fallback al email o "?" para no romper el render.
 */
function initials(name: string | null | undefined, email: string): string {
  const trimmed = name?.trim();
  if (!trimmed) {
    return email.charAt(0).toUpperCase() || "?";
  }
  const parts = trimmed.split(/\s+/);
  const first = parts[0]?.charAt(0) ?? "";
  const last = parts.length > 1 ? parts[parts.length - 1]?.charAt(0) ?? "" : "";
  return (first + last).toUpperCase();
}

/** Menú de usuario (avatar + logout) — ver web/WEB_VISUAL_STANDARDS.md §6. */
export function UserMenu({ user }: { user: AuthUser }): ReactNode {
  const logoutMutation = useLogoutMutation();

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          className="relative h-10 w-10 rounded-full"
          aria-label="Menú de usuario"
        >
          <Avatar className="h-9 w-9">
            <AvatarFallback className="bg-accent-brand text-accent-brand-foreground text-sm font-medium">
              {initials(user.name, user.email)}
            </AvatarFallback>
          </Avatar>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-56">
        <DropdownMenuLabel className="font-normal">
          <div className="flex flex-col gap-0.5">
            <span className="text-sm font-medium leading-none">
              {user.name || user.email}
            </span>
            {user.name && (
              <span className="text-xs leading-none text-muted-foreground">
                {user.email}
              </span>
            )}
          </div>
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuItem
          onClick={() => logoutMutation.mutate()}
          disabled={logoutMutation.isPending}
        >
          <LogOut className="h-4 w-4" />
          Cerrar sesión
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
