import { type ReactNode, lazy, Suspense } from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Routes, Route } from "react-router-dom";
import { TestPage } from "@/app/TestPage";

const DevIndicatorLazy = import.meta.env.DEV
  ? lazy(() =>
      import("@/components/DevIndicator").then((m) => ({ default: m.DevIndicator })),
    )
  : null;

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000, // 5 min — conservador para datos de panel
      retry: 1,
    },
  },
});

function DevIndicatorWrapper(): ReactNode {
  if (!DevIndicatorLazy) return null;
  return (
    <Suspense fallback={null}>
      <DevIndicatorLazy />
    </Suspense>
  );
}

export function App(): ReactNode {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <Routes>
          <Route path="/" element={<TestPage />} />
        </Routes>
        <DevIndicatorWrapper />
      </BrowserRouter>
    </QueryClientProvider>
  );
}
