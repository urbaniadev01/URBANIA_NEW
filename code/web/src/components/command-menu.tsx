import { type ReactNode, useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { Home, LayoutDashboard, Search } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from "@/components/ui/command";
import { getVisibleSidebar } from "@/features/dashboard/registry";
import type { AuthUser } from "@/features/dashboard/types";

interface CommandMenuProps {
  user: AuthUser | null;
}

/**
 * Barra de búsqueda de features — Cmd/Ctrl+K, ver shadcn/ui Command.
 *
 * La lista de features sale de getVisibleSidebar(user) — el mismo filtro
 * de permisos que usa la sidebar — para no mostrar pantallas de
 * administración a usuarios sin el permiso correspondiente.
 */
export function CommandMenu({ user }: CommandMenuProps): ReactNode {
  const [open, setOpen] = useState(false);
  const navigate = useNavigate();

  const items = useMemo(() => {
    const visible = getVisibleSidebar(user);
    const flat = visible.flatMap((item) => [item, ...(item.children ?? [])]);
    return [{ id: "inicio", to: "/dashboard", label: "Inicio", icon: Home }, ...flat];
  }, [user]);

  useEffect(() => {
    function onKeyDown(e: KeyboardEvent) {
      if (e.key === "k" && (e.metaKey || e.ctrlKey)) {
        e.preventDefault();
        setOpen((prev) => !prev);
      }
    }
    document.addEventListener("keydown", onKeyDown);
    return () => document.removeEventListener("keydown", onKeyDown);
  }, []);

  function go(to: string) {
    setOpen(false);
    navigate(to);
  }

  return (
    <>
      <Button
        variant="outline"
        className="relative h-9 w-full max-w-64 justify-start gap-2 text-sm text-muted-foreground sm:pr-12"
        onClick={() => setOpen(true)}
      >
        <Search className="h-4 w-4" />
        Buscar features...
        <kbd className="pointer-events-none absolute right-1.5 top-1.5 hidden h-6 select-none items-center gap-1 rounded border bg-muted px-1.5 font-mono text-[10px] font-medium opacity-100 sm:flex">
          <span className="text-xs">⌘</span>K
        </kbd>
      </Button>
      <CommandDialog open={open} onOpenChange={setOpen}>
        <CommandInput placeholder="Buscar una feature..." />
        <CommandList>
          <CommandEmpty>Sin resultados.</CommandEmpty>
          <CommandGroup heading="Features">
            {items.map((item) => {
              const Icon = item.icon ?? LayoutDashboard;
              return (
                <CommandItem
                  key={item.id}
                  value={item.label}
                  onSelect={() => go(item.to)}
                >
                  <Icon />
                  {item.label}
                </CommandItem>
              );
            })}
          </CommandGroup>
        </CommandList>
      </CommandDialog>
    </>
  );
}
