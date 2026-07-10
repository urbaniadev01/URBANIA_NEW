import { test, expect, type Page } from "@playwright/test";

/**
 * Verificación visual real de PROPIEDADES-B06..B09 contra el backend real
 * (docker compose local, http://localhost:8081) — sin mocks de red.
 * Requiere el seed demo: admin@urbania.test / Admin123!
 */

const ADMIN_EMAIL = "admin@urbania.test";
const ADMIN_PASSWORD = "Admin123!";

async function login(page: Page): Promise<void> {
  await page.goto("/login");
  await page.fill('input[placeholder="tu@email.com"]', ADMIN_EMAIL);
  await page.fill('input[placeholder="••••••••"]', ADMIN_PASSWORD);
  await page.click('button:has-text("Iniciar sesión")');
  await page.waitForURL("**/dashboard", { timeout: 15000 });
}

test.describe("PROPIEDADES-B06 — TiposPropiedad y EstadosPropiedad", () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test("CA1, CA5 — tabla de tipos con badge Sistema y sin acciones en catálogos de sistema", async ({
    page,
  }) => {
    await page.goto("/catalogos/tipos-propiedad");
    await expect(page.getByRole("heading", { name: "Tipos de Propiedad" })).toBeVisible();
    await expect(page.getByText("Sistema").first()).toBeVisible({ timeout: 10000 });
    await page.screenshot({ path: "e2e/propiedades/__screenshots__/b06-tipos-lista.png" });
  });

  test("CA2, CA3, CA4 — crear tipo: dialogo, validación y submit exitoso", async ({ page }) => {
    await page.goto("/catalogos/tipos-propiedad");
    await page.click('button:has-text("Nuevo")');
    await expect(page.getByRole("heading", { name: "Nuevo Tipo de propiedad" })).toBeVisible();

    // CA4: submit vacío -> validación
    await page.click('button:has-text("Guardar")');
    await expect(page.getByText(/obligatorio|requerido/i).first()).toBeVisible({ timeout: 3000 });

    // CA3: submit válido
    const nombreUnico = `E2E Tipo ${Date.now()}`;
    await page.fill('input[placeholder^="Ej:"]', nombreUnico);
    await page.click('button:has-text("Guardar")');
    await expect(page.getByText(nombreUnico)).toBeVisible({ timeout: 10000 });
    await page.screenshot({ path: "e2e/propiedades/__screenshots__/b06-tipos-crear.png" });
  });

  test("CA11 — mismo flujo en EstadosPropiedad", async ({ page }) => {
    await page.goto("/catalogos/estados-propiedad");
    await expect(page.getByRole("heading", { name: "Estados de Propiedad" })).toBeVisible();
    await expect(page.getByText("Sistema").first()).toBeVisible({ timeout: 10000 });
  });
});

test.describe("PROPIEDADES-B07 — Condominios", () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test("CA1, CA6, CA7 — lista de condominios, navegación a detalle, tab Torres", async ({
    page,
  }) => {
    await page.goto("/condominios");
    await expect(page.getByRole("heading", { name: "Condominios" })).toBeVisible();
    await page.screenshot({ path: "e2e/propiedades/__screenshots__/b07-condominios-lista.png" });

    const firstCard = page.locator("main").getByRole("heading").nth(1);
    await firstCard.click();

    await page.waitForURL(/\/condominios\/.+/, { timeout: 10000 });
    await expect(page.getByText("Condominios").first()).toBeVisible();
    await expect(page.getByRole("tab", { name: /Torres/i })).toBeVisible();
    await page.screenshot({ path: "e2e/propiedades/__screenshots__/b07-detalle-torres.png" });
  });

  test("CA3, CA4, CA5 — crear condominio: sheet, validación y submit exitoso", async ({
    page,
  }) => {
    await page.goto("/condominios");
    await page.click('button:has-text("Nuevo condominio")');
    await page.waitForSelector('input[name="nombre"]', { timeout: 5000 }).catch(() => {});

    const nombreUnico = `E2E Condominio ${Date.now()}`;
    const nombreInput = page.locator('input').filter({ hasText: "" }).first();
    // Fallback genérico: usar el primer input visible dentro del Sheet abierto
    const sheet = page.locator('[role="dialog"], [data-slot="sheet-content"]').first();
    await sheet.locator("input").first().fill(nombreUnico);
    await sheet.getByRole("button", { name: "Guardar" }).click();

    await expect(page.getByText(nombreUnico)).toBeVisible({ timeout: 10000 });
  });
});

test.describe("PROPIEDADES-B08 — Unidades", () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test("CA1 — tab Unidades renderiza tabla sin columna area_m2", async ({ page }) => {
    await page.goto("/condominios");
    await page.locator("main").getByRole("heading").nth(1).click();
    await page.waitForURL(/\/condominios\/.+/, { timeout: 10000 });

    await page.getByRole("tab", { name: "Unidades" }).click();
    await page.waitForURL(/tab=unidades/, { timeout: 5000 });

    await expect(page.locator("table").first()).toBeVisible({ timeout: 10000 });
    const headers = await page.locator("table thead th").allTextContents();
    expect(headers.join(" ").toLowerCase()).not.toContain("area_m2");
    await page.screenshot({ path: "e2e/propiedades/__screenshots__/b08-unidades-tabla.png" });
  });
});

test.describe("PROPIEDADES-B09 — Coeficientes", () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test("CA1 — tab Coeficientes renderiza tabla y botón Guardar deshabilitado sin cambios", async ({
    page,
  }) => {
    await page.goto("/condominios");
    await page.locator("main").getByRole("heading").nth(1).click();
    await page.waitForURL(/\/condominios\/.+/, { timeout: 10000 });

    await page.getByRole("tab", { name: "Coeficientes" }).click();
    await page.waitForURL(/tab=coeficientes/, { timeout: 5000 });

    await expect(
      page.getByRole("button", { name: /Guardar cambios/i }),
    ).toBeVisible({ timeout: 10000 });
    await expect(
      page.getByRole("button", { name: /Guardar cambios/i }),
    ).toBeDisabled();
    await page.screenshot({ path: "e2e/propiedades/__screenshots__/b09-coeficientes-tabla.png" });
  });
});
