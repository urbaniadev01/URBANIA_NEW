import { test, expect } from "@playwright/test";

const REGISTER_URL = "http://localhost:5173/register/test-token-123";
const API_REGISTER = "**/api/v1/auth/register";

test.describe("AUTH-B07 — Pantalla de registro", () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(REGISTER_URL);
    await page.waitForSelector('input[placeholder="Tu nombre"]');
  });

  test("CA1 — registro exitoso redirige a /login con toast de éxito", async ({
    page,
  }) => {
    await page.route(API_REGISTER, async (route) => {
      const body = route.request().postDataJSON();
      expect(body).toMatchObject({
        invitation_token: "test-token-123",
        name: "María García",
      });
      await route.fulfill({
        status: 201,
        contentType: "application/json",
        body: JSON.stringify({
          user: { id: 1, name: "María García", email: "maria@urbania.com" },
          access_token: "fake-jwt-token",
          token_type: "Bearer",
          expires_in: 900,
        }),
      });
    });

    await page.fill('input[placeholder="Tu nombre"]', "María García");
    await page.fill('input[placeholder="........"] >> nth=0', "Password1");
    await page.fill('input[placeholder="........"] >> nth=1', "Password1");
    await page.click('button:has-text("Crear cuenta")');

    // Toast de éxito
    await expect(
      page.getByText("Cuenta creada, inicia sesion"),
    ).toBeVisible({ timeout: 3000 });

    // Redirige a /login
    await page.waitForURL("**/login", { timeout: 5000 });
    expect(page.url()).toContain("/login");
  });

  test("CA2 — INVITATION_TOKEN_INVALID muestra mensaje de error", async ({
    page,
  }) => {
    await page.route(API_REGISTER, async (route) => {
      await route.fulfill({
        status: 403,
        contentType: "application/json",
        body: JSON.stringify({
          error: {
            code: "INVITATION_TOKEN_INVALID",
            message: "La invitación no es válida o ya fue utilizada.",
            trace_id: "01930000-0000-7000-8000-000000000403",
          },
        }),
      });
    });

    await page.fill('input[placeholder="Tu nombre"]', "María García");
    await page.fill('input[placeholder="........"] >> nth=0', "Password1");
    await page.fill('input[placeholder="........"] >> nth=1', "Password1");
    await page.click('button:has-text("Crear cuenta")');

    await expect(
      page.getByText("La invitacion no es valida o ya fue utilizada."),
    ).toBeVisible({ timeout: 3000 });

    expect(page.url()).toContain("/register/");
  });

  test("CA3 — validación cliente: campos vacíos bloquea submit", async ({
    page,
  }) => {
    let apiCalled = false;

    await page.route(API_REGISTER, async (route) => {
      apiCalled = true;
      await route.fulfill({ status: 200, body: "{}" });
    });

    await page.click('button:has-text("Crear cuenta")');

    await expect(
      page.getByText("El nombre debe tener al menos 2 caracteres."),
    ).toBeVisible({ timeout: 3000 });

    await page.waitForTimeout(1000);
    expect(apiCalled).toBe(false);
    expect(page.url()).toContain("/register/");
  });

  test("CA4 — validación cliente: contraseñas no coinciden", async ({
    page,
  }) => {
    await page.fill('input[placeholder="Tu nombre"]', "María García");
    await page.fill('input[placeholder="........"] >> nth=0', "Password1");
    await page.fill('input[placeholder="........"] >> nth=1', "Diferente1");
    await page.click('button:has-text("Crear cuenta")');

    await expect(
      page.getByText("Las contrasenas no coinciden."),
    ).toBeVisible({ timeout: 3000 });
  });
});
