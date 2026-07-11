import { type ReactNode, useEffect, useState } from "react";
import { Palette } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { cn } from "@/lib/utils";

const RADIUS_OPTIONS = [
  { label: "Ninguno", value: "0rem" },
  { label: "Chico", value: "0.3rem" },
  { label: "Medio", value: "0.5rem" },
  { label: "Grande", value: "0.75rem" },
];

const ACCENT_OPTIONS = [
  { label: "Marino", value: "211 55% 23%" },
  { label: "Verde", value: "142 71% 30%" },
  { label: "Violeta", value: "262 52% 40%" },
  { label: "Terracota", value: "16 65% 40%" },
];

const SCALE_OPTIONS = [
  { label: "90%", value: "90%" },
  { label: "100%", value: "100%" },
  { label: "110%", value: "110%" },
  { label: "125%", value: "125%" },
];

const RADIUS_KEY = "urbania-ui-radius";
const ACCENT_KEY = "urbania-ui-accent";
const SCALE_KEY = "urbania-ui-scale";

function readStored(key: string): string | null {
  try {
    return window.localStorage.getItem(key);
  } catch {
    return null;
  }
}

function writeStored(key: string, value: string): void {
  try {
    window.localStorage.setItem(key, value);
  } catch {
    // Ignorar — la personalización sigue aplicándose en memoria.
  }
}

/**
 * Dropdown de personalización visual (radio de bordes + color de acento).
 * Aplica directo sobre las CSS variables del tema (--radius, --primary,
 * --ring), independiente del toggle claro/oscuro (ModeToggle).
 */
export function ThemeCustomizer(): ReactNode {
  const [radius, setRadius] = useState(
    () => readStored(RADIUS_KEY) ?? "0.5rem",
  );
  const [accent, setAccent] = useState(
    () => readStored(ACCENT_KEY) ?? "211 55% 23%",
  );
  const [scale, setScale] = useState(() => readStored(SCALE_KEY) ?? "100%");

  useEffect(() => {
    document.documentElement.style.setProperty("--radius", radius);
  }, [radius]);

  useEffect(() => {
    document.documentElement.style.setProperty("--primary", accent);
    document.documentElement.style.setProperty("--ring", accent);
  }, [accent]);

  useEffect(() => {
    document.documentElement.style.fontSize = scale;
  }, [scale]);

  function applyRadius(value: string): void {
    setRadius(value);
    writeStored(RADIUS_KEY, value);
  }

  function applyAccent(value: string): void {
    setAccent(value);
    writeStored(ACCENT_KEY, value);
  }

  function applyScale(value: string): void {
    setScale(value);
    writeStored(SCALE_KEY, value);
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon">
          <Palette className="h-5 w-5" />
          <span className="sr-only">Personalizar estilos</span>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-64">
        <DropdownMenuLabel>Radio de bordes</DropdownMenuLabel>
        <div className="grid grid-cols-4 gap-2 px-2 pb-2">
          {RADIUS_OPTIONS.map((opt) => (
            <button
              key={opt.value}
              type="button"
              onClick={() => applyRadius(opt.value)}
              className={cn(
                "flex flex-col items-center gap-1 rounded-md border p-2 text-[11px] transition-colors hover:bg-accent",
                radius === opt.value && "border-primary bg-accent",
              )}
            >
              <span
                className="h-4 w-4 border border-foreground/40 bg-muted"
                style={{ borderRadius: opt.value }}
              />
              {opt.label}
            </button>
          ))}
        </div>
        <DropdownMenuSeparator />
        <DropdownMenuLabel>Color de acento</DropdownMenuLabel>
        <div className="flex gap-2 px-2 pb-2">
          {ACCENT_OPTIONS.map((opt) => (
            <button
              key={opt.value}
              type="button"
              onClick={() => applyAccent(opt.value)}
              aria-label={opt.label}
              title={opt.label}
              className={cn(
                "h-7 w-7 rounded-full border-2 transition-transform hover:scale-110",
                accent === opt.value ? "border-foreground" : "border-transparent",
              )}
              style={{ backgroundColor: `hsl(${opt.value})` }}
            />
          ))}
        </div>
        <DropdownMenuSeparator />
        <DropdownMenuLabel>Escala</DropdownMenuLabel>
        <div className="grid grid-cols-4 gap-2 px-2 pb-2">
          {SCALE_OPTIONS.map((opt) => (
            <button
              key={opt.value}
              type="button"
              onClick={() => applyScale(opt.value)}
              className={cn(
                "rounded-md border px-2 py-1.5 text-[11px] transition-colors hover:bg-accent",
                scale === opt.value && "border-primary bg-accent",
              )}
            >
              {opt.label}
            </button>
          ))}
        </div>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
