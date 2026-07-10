import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Download, Copy } from "lucide-react";

interface RecoveryCodesProps {
  codes: string[];
}

const RECOVERY_HEADER =
  "CÓDIGOS DE RESPALDO — Urbania\n" +
  "Guarda estos códigos en un lugar seguro. Cada código solo se puede usar una vez.\n\n";

export function RecoveryCodes({ codes }: RecoveryCodesProps): React.ReactNode {
  if (codes.length === 0) return null;

  function handleCopyAll(): void {
    navigator.clipboard
      .writeText(codes.join("\n"))
      .then(() => {
        toast.success(`${codes.length} códigos copiados al portapapeles.`);
      })
      .catch(() => {
        toast.error("No se pudo copiar al portapapeles.");
      });
  }

  function handleDownloadTxt(): void {
    const content = RECOVERY_HEADER + codes.join("\n");
    const blob = new Blob([content], { type: "text/plain" });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement("a");
    anchor.href = url;
    anchor.download = "urbania-recovery-codes.txt";
    document.body.appendChild(anchor);
    anchor.click();
    document.body.removeChild(anchor);
    URL.revokeObjectURL(url);
  }

  return (
    <div className="space-y-3">
      <div className="rounded-lg border bg-muted/40 p-4">
        <ul className="list-none space-y-1">
          {codes.map((code) => (
            <li
              key={code}
              className="font-mono text-sm tracking-wider"
            >
              {code}
            </li>
          ))}
        </ul>
      </div>
      <div className="flex gap-2">
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={handleCopyAll}
        >
          <Copy className="mr-2 h-4 w-4" />
          Copiar todos
        </Button>
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={handleDownloadTxt}
        >
          <Download className="mr-2 h-4 w-4" />
          Descargar TXT
        </Button>
      </div>
    </div>
  );
}
