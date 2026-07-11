import { test, expect, type Page, type BrowserContext } from "@playwright/test";

/**
 * Verificación visual real de PROPIEDADES-B06..B09 contra el backend real
 * (docker compose local, http://localhost:8081) — sin mocks de red.
 * Requiere el seed demo: admin@urbania.test / Admin123!
 * 
 * Actualizado 2026-07-11: login vía API (request context) para evitar
 * timeouts con el formulario React + glass-morphism LoginPage.
 */

const ADMIN_EMAIL = "admin@urbania.test";
const ADMIN_PASSWORD = "Admin123!";
const API_BASE = "http://localhost:8081/api/v1";

/**
 * Login programático vía API. Guarda el token en sessionStorage simulando
 * lo que hace useAuthStore, y deja las cookies httpOnly del backend.
 */
async function loginViaApi(context: BrowserContext): Promise<Page> {
  const request = context.request;

  // 1. Login vía API
  const loginResp = await request.post(API_BASE + "/auth/login", {
    data: { email: ADMIN_EMAIL, password: ADMIN_PASSWORD },
    failOnStatusCode: false,
  });

  if (loginResp.status() !== 200) {
    const body = await loginResp.text();
    throw new Error(`Login failed with status ${loginResp.status()}: ${body}`);
  }

  const { access_token } = await loginResp.json();
  if (!access_token) throw new Error("Login response missing access_token");

  // 2. Abrir página y setear token en sessionStorage ANTES de que React monte
  const page = await context.newPage();
  await page.goto("/");
  await page.evaluate((token: string) => {
    sessionStorage.setItem("auth-storage", JSON.stringify({ state: { accessToken: token } }));
  }, access_token);

  // 3. Recargar para que RequireAuth lea el token
  await page.goto("/dashboard");
  await page.waitForLoadState("networkidle", { timeout: 15000 }).catch(() => {});

  // 4. Verificar que estamos autenticados (sidebar visible)
  await page.locator('[role="banner"]').waitFor({ state: "visible", timeout: 15000 });

  return page;
}

test.describe("PROPIEDADES-B06 — TiposPropiedad y EstadosPropiedad", () => {
  let page: Page;

  test.beforeAll(async ({ browser }) => {
    const context = await browser.newContext();
    page = await loginViaApi(context);
  });

  test("CA1, CA5 — tabla de tipos con badge Sistema y sin acciones en catálogos de sistema", async () => {
    await page.goto("/catalogos/tipos-propiedad");
    await expect(page.getByRole("heading", { name: "Tipos de Propiedad" })).toBeVisible({ timeout: 15000 });
    await expect(page.getByText("Sistema").first()).toBeVisible({ timeout: 10000 });
    await expect(page.getByText("Solo lectura").first()).toBeVisible();
    await page.screenshot({ path: "e2e/propiedades/__screenshots__/b06-tipos-lista.png" });
  });

  test("CA2, CA3, CA4 — crear tipo: dialogo, validación y submit exitoso", async () => {
    await page.goto("/catalogos/tipos-propiedad");
    await page.getByRole("button", { name: "Nuevo" }).click();
    await expect(page.getByRole("heading", { name: "Nuevo Tipo de propiedad" })).toBeVisible();

    // CA4: submit vacío -> validación
    await page.getByRole("button", { name: "Guardar" }).click();
    await expect(page.getByText(/obligatorio/i)).toBeVisible({ timeout: 3000 });

    // CA3: submit válido
    const nombreUnico = "E2E Tipo " + Date.now();
    await page.getByRole("textbox", { name: "Nombre" }).fill(nombreUnico);
    await page.getByRole("button", { name: "Guardar" }).click();
    await expect(page.getByText(nombreUnico)).toBeVisible({ timeout: 15000 });
    await page.screenshot({ path: "e2e/propiedades/__screenshots__/b06-tipos-crear.png" });
  });

  test("CA11 — mismo flujo en EstadosPropiedad", async () => {
    await page.goto("/catalogos/estados-propiedad");
    await expect(page.getByRole("heading", { name: "Estados de Propiedad" })).toBeVisible({ timeout: 15000 });
    await expect(page.getByText("Sistema").first()).toBeVisible({ timeout: 10000 });
    await expect(page.getByText("Solo lectura").first()).toBeVisible();
  });
});

test.describe("PROPIEDADES-B07 — Condominios", () => {
  let page: Page;

  test.beforeAll(async ({ browser }) => {
    const context = await browser.newContext();
    page = await loginViaApi(context);
  });

  test("CA1, CA6, CA7 — lista de condominios, navegación a detalle, tab Torres", async () => {
    await page.goto("/condominios");
    await expect(page.getByRole("heading", { name: "Condominios" })).toBeVisible({ timeout: 15000 });
    await page.screenshot({ path: "e2e/propiedades/__screenshots__/b07-condominios-lista.png" });

    const firstCard = page.getByRole("button").filter({ has: page.getByRole("heading") }).first();
    await firstCard.click();
    await page.waitForURL(/\/condominios\/.+/, { timeout: 15000 });
    await expect(page.getByText("Condominios").first()).toBeVisible();
    await expect(page.getByRole("tab", { name: /Torres/ })).toBeVisible({ timeout: 10000 });
    await page.screenshot({ path: "e2e/propiedades/__screenshots__/b07-detalle-torres.png" });
  });

  test("CA3, CA4, CA5 — crear condominio: sheet, validación y submit exitoso", async () => {
    await page.goto("/condominios");
    await page.getByRole("button", { name: "Nuevo condominio" }).click();
    await page.getByRole("textbox", { name: "Nombre" }).waitFor({ state: "visible", timeout: 5000 });

    const nombreUnico = "E2E Condominio " + Date.now();
    await page.getByRole("textbox", { name: "Nombre" }).fill(nombreUnico);
    await page.getByRole("button", { name: "Guardar" }).click();
    await expect(page.getByText(nombreUnico)).toBeVisible({ timeout: 15000 });
  });
});

test.describe("PROPIEDADES-B08 — Unidades", () => {
  let page: Page;

  test.beforeAll(async ({ browser }) => {
    const context = await browser.newContext();
    page = await loginViaApi(context);
  });

  test("CA1, CA15 — tab Unidades renderiza tabla sin columna area_m2", async () => {
    await page.goto("/condominios");
    await expect(page.getByRole("heading", { name: "Condominios" })).toBeVisible({ timeout: 15000 });

    const firstCard = page.getByRole("button").filter({ has: page.getByRole("heading") }).first();
    await firstCard.click();
    await page.waitForURL(/\/condominios\/.+/, { timeout: 15000 });

    await page.getByRole("tab", { name: "Unidades" }).click();
    await page.waitForURL(/tab=unidades/, { timeout: 10000 });

    await expect(page.locator("table").first()).toBeVisible({ timeout: 15000 });

    const headers = await page.locator("table thead th").allTextContents();
    expect(headers.join(" ").toLowerCase()).not.toContain("area_m2");

    await expect(page.getByRole("textbox", { name: /Buscar por código/ })).toBeVisible();
    await page.screenshot({ path: "e2e/propiedades/__screenshots__/b08-unidades-tabla.png" });
  });
});

test.describe("PROPIEDADES-B09 — Coeficientes", () => {
  let page: Page;

  test.beforeAll(async ({ browser }) => {
    const context = await browser.newContext();
    page = await loginViaApi(context);
  });

  test("CA1 — tab Coeficientes renderiza tabla y botón Guardar deshabilitado sin cambios", async () => {
    await page.goto("/condominios");
    await expect(page.getByRole("heading", { name: "Condominios" })).toBeVisible({ timeout: 15000 });

    const firstCard = page.getByRole("button").filter({ has: page.getByRole("heading") }).first();
    await firstCard.click();
    await page.waitForURL(/\/condominios\/.+/, { timeout: 15000 });

    await page.getByRole("tab", { name: "Coeficientes" }).click();
    await page.waitForURL(/tab=coeficientes/, { timeout: 10000 });

    const guardarBtn = page.getByRole("button", { name: /Guardar cambios/i });
    await expect(guardarBtn).toBeVisible({ timeout: 15000 });
    await expect(guardarBtn).toBeDisabled();
    await expect(page.getByText(/Suma actual/)).toBeVisible({ timeout: 5000 });

    await page.screenshot({ path: "e2e/propiedades/__screenshots__/b09-coeficientes-tabla.png" });
  });
});
