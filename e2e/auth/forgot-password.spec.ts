import { test, expect } from "@playwright/test";

const FORGOT_URL = "http://localhost:5173/forgot-password";
const API_FORGOT = "**/api/v1/auth/forgot-password";

test.describe("AUTH-B12 — Pantalla de recuperación de contraseña", () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(FORGOT_URL);
    await page.waitForSelector('input[placeholder="tu@email.com"]');
  });

  test("CA1 — email registrado muestra mensaje genérico de éxito", async ({
    page,
  }) => {
    await page.route(API_FORGOT, async (route) => {
      const body = route.request().postDataJSON();
      expect(body.email).toBe("admin@urbania.com");
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          message:
            "Si el email está registrado, recibirás un enlace de recuperación.",
        }),
      });
    });

    await page.fill(
      'input[placeholder="tu@email.com"]',
      "admin@urbania.com",
    );
    await page.click('button:has-text("Enviar enlace de recuperacion")');

    await expect(
      page.getByText("Revisa tu correo electronico"),
    ).toBeVisible({ timeout: 3000 });
    await expect(
      page.getByText(/Si el email esta registrado/),
    ).toBeVisible();
  });

  test("CA2 — email NO registrado muestra el mismo mensaje genérico (anti-enumeration)", async ({
    page,
  }) => {
    await page.route(API_FORGOT, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          message:
            "Si el email está registrado, recibirás un enlace de recuperación.",
        }),
      });
    });

    await page.fill(
      'input[placeholder="tu@email.com"]',
      "noexiste@urbania.com",
    );
    await page.click('button:has-text("Enviar enlace de recuperacion")');

    // Mismo mensaje genérico
    await expect(
      page.getByText("Revisa tu correo electronico"),
    ).toBeVisible({ timeout: 3000 });
    await expect(
      page.getByText(/Si el email esta registrado/),
    ).toBeVisible();

    // No revela si el email existe o no
    await expect(
      page.getByText(/no existe|no encontrado|no registrado/i),
    ).toHaveCount(0);
  });

  test("CA3 — campo vacío muestra validación", async ({ page }) => {
    let apiCalled = false;

    await page.route(API_FORGOT, async (route) => {
      apiCalled = true;
      await route.fulfill({ status: 200, body: "{}" });
    });

    await page.click('button:has-text("Enviar enlace de recuperacion")');

    await expect(
      page.getByText("El email es obligatorio."),
    ).toBeVisible({ timeout: 3000 });

    await page.waitForTimeout(1000);
    expect(apiCalled).toBe(false);
  });

  test("CA4 — enlace 'Volver a inicio de sesión' redirige a /login", async ({
    page,
  }) => {
    await page.click('a:has-text("Volver a inicio de sesion")');
    await page.waitForURL("**/login", { timeout: 5000 });
    expect(page.url()).toContain("/login");
  });

  test("CA5 — email con formato inválido muestra validación", async ({
    page,
  }) => {
    await page.fill('input[placeholder="tu@email.com"]', "no-es-email");
    await page.click('button:has-text("Enviar enlace de recuperacion")');

    await expect(
      page.getByText("Ingresa un email valido."),
    ).toBeVisible({ timeout: 3000 });
  });
});
