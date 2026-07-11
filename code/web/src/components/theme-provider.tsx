import { useEffect, useState } from "react";
import type { ReactNode } from "react";
import {
  ThemeProviderContext,
  type Theme,
  type ThemeProviderState,
} from "@/components/theme-context";

interface ThemeProviderProps {
  children: ReactNode;
  defaultTheme?: Theme;
  storageKey?: string;
}

// localStorage puede no estar disponible o lanzar (modo privado, storage
// deshabilitado, o el bug de Node >=22 con --localstorage-file en jsdom
// durante tests — ver _state/RUNBOOK.md#E-005). Degrada a defaultTheme.
function readStoredTheme(storageKey: string): Theme | null {
  try {
    return window.localStorage.getItem(storageKey) as Theme | null;
  } catch {
    return null;
  }
}

function writeStoredTheme(storageKey: string, theme: Theme): void {
  try {
    window.localStorage.setItem(storageKey, theme);
  } catch {
    // Ignorar — el tema sigue aplicándose en memoria para esta sesión.
  }
}

/**
 * Receta oficial de shadcn/ui para Vite (sin next-themes, que es Next-only).
 * Persiste en localStorage; default = preferencia del sistema.
 * Ver web/WEB_VISUAL_STANDARDS.md §6.
 */
export function ThemeProvider({
  children,
  defaultTheme = "system",
  storageKey = "urbania-ui-theme",
  ...props
}: ThemeProviderProps): ReactNode {
  const [theme, setTheme] = useState<Theme>(
    () => readStoredTheme(storageKey) ?? defaultTheme,
  );

  useEffect(() => {
    const root = window.document.documentElement;
    root.classList.remove("light", "dark");

    if (theme === "system") {
      const systemTheme = window.matchMedia("(prefers-color-scheme: dark)")
        .matches
        ? "dark"
        : "light";
      root.classList.add(systemTheme);
      return;
    }

    root.classList.add(theme);
  }, [theme]);

  const value: ThemeProviderState = {
    theme,
    setTheme: (nextTheme: Theme) => {
      writeStoredTheme(storageKey, nextTheme);
      setTheme(nextTheme);
    },
  };

  return (
    <ThemeProviderContext.Provider {...props} value={value}>
      {children}
    </ThemeProviderContext.Provider>
  );
}
