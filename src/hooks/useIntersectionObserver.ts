import { useEffect, useRef, useState } from "react";

interface UseIntersectionObserverOptions {
  /** Margen alrededor del root (formato CSS margin). Default: "200px" para lazy loading. */
  rootMargin?: string;
  /** Umbral de visibilidad. Default: 0 (se dispara apenas entra 1px). */
  threshold?: number | number[];
  /** Si true, deja de observar después del primer trigger (one-shot). Default: true. */
  triggerOnce?: boolean;
}

interface UseIntersectionObserverResult {
  /** Ref callback a adjuntar al elemento observado. */
  ref: (node: HTMLElement | null) => void;
  /** true cuando el elemento entró al viewport (o al rootMargin). */
  isIntersecting: boolean;
}

/**
 * Hook reutilizable de IntersectionObserver para lazy loading.
 *
 * Uso típico en DashboardGrid:
 * ```tsx
 * const { ref, isIntersecting } = useIntersectionObserver({ rootMargin: "200px" });
 * return <div ref={ref}>{isIntersecting && <LazyWidget />}</div>;
 * ```
 *
 * Por defecto usa triggerOnce: true — una vez que el elemento es visible,
 * nunca se vuelve a ocultar (el chunk JS ya se descargó).
 */
export function useIntersectionObserver(
  options: UseIntersectionObserverOptions = {},
): UseIntersectionObserverResult {
  const { rootMargin = "200px", threshold = 0, triggerOnce = true } = options;

  const [isIntersecting, setIsIntersecting] = useState(false);
  const hasTriggered = useRef(false);
  const observerRef = useRef<IntersectionObserver | null>(null);

  const ref: (node: HTMLElement | null) => void = (node) => {
    // Limpiar observer anterior
    if (observerRef.current) {
      observerRef.current.disconnect();
      observerRef.current = null;
    }

    if (!node) return;

    // Si ya se disparó y es one-shot, no crear nuevo observer
    if (triggerOnce && hasTriggered.current) {
      setIsIntersecting(true);
      return;
    }

    observerRef.current = new IntersectionObserver(
      (entries) => {
        const entry = entries[0];
        if (!entry) return;

        if (entry.isIntersecting) {
          setIsIntersecting(true);

          if (triggerOnce) {
            hasTriggered.current = true;
            observerRef.current?.disconnect();
            observerRef.current = null;
          }
        } else if (!triggerOnce) {
          setIsIntersecting(false);
        }
      },
      { rootMargin, threshold },
    );

    observerRef.current.observe(node);
  };

  // Cleanup al desmontar
  useEffect(() => {
    return () => {
      observerRef.current?.disconnect();
    };
  }, []);

  return { ref, isIntersecting };
}
