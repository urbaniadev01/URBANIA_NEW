import { test, expect } from "@playwright/test";

const RESET_URL =
  "http://localhost:5173/reset-password?token=abc123def456&email=usuario@urbania.com";
const API_RESET = "**/api/v1/auth/reset-password";

test.describe("AUTH-B13 — Pantalla de nueva contraseña", () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(RESET_URL);
    await page.waitForSelector('input[placeholder="........"]');
  });

  test("CA1 — reset exitoso redirige a /login con toast", async ({
    page,
  }) => {
    await page.route(API_RESET, async (route) => {
      const body = route.request().postDataJSON();
      expect(body.token).toBe("abc123def456");
      expect(body.password).toBe("NewPass1");
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          message: "Contraseña actualizada exitosamente.",
        }),
      });
    });

    await page.fill('input[placeholder="........"] >> nth=0', "NewPass1");
    await page.fill('input[placeholder="........"] >> nth=1', "NewPass1");
    await page.click('button:has-text("Actualizar contrasena")');

    await expect(
      page.getByText("Contraseña actualizada exitosamente"),
    ).toBeVisible({ timeout: 3000 });

    await page.waitForURL("**/login", { timeout: 5000 });
    expect(page.url()).toContain("/login");
  });

  test("CA2 — checklist en tiempo real muestra 4 requisitos", async ({
    page,
  }) => {
    // Inicialmente todos en rojo
    await expect(page.getByText("Al menos 8 caracteres")).toBeVisible();
    await expect(page.getByText("Al menos una mayuscula")).toBeVisible();
    await expect(page.getByText("Al menos una minuscula")).toBeVisible();
    await expect(page.getByText("Al menos un numero")).toBeVisible();

    // Escribir contraseña que cumple todo
    await page.fill('input[placeholder="........"] >> nth=0', "Password1");

    // Verificar que los checks están verdes (span con texto verde aparece)
    await expect(page.locator(".text-green-600").first()).toBeVisible({
      timeout: 2000,
    });
  });

  test("CA3 — contraseñas no coinciden muestra error", async ({ page }) => {
    await page.fill('input[placeholder="........"] >> nth=0', "Password1");
    await page.fill('input[placeholder="........"] >> nth=1', "Diferente1");
    await page.click('button:has-text("Actualizar contrasena")');

    await expect(
      page.getByText("Las contrasenas no coinciden."),
    ).toBeVisible({ timeout: 3000 });
  });

  test("CA4 — campos vacíos: validación cliente bloquea submit", async ({
    page,
  }) => {
    let apiCalled = false;

    await page.route(API_RESET, async (route) => {
      apiCalled = true;
      await route.fulfill({ status: 200, body: "{}" });
    });

    await page.click('button:has-text("Actualizar contrasena")');

    await expect(
      page.getByText("La confirmacion es obligatoria."),
    ).toBeVisible({ timeout: 3000 });

    await page.waitForTimeout(1000);
    expect(apiCalled).toBe(false);
  });

  test("CA5 — sin token ni email en URL muestra mensaje de enlace inválido", async ({
    page,
  }) => {
    await page.goto("http://localhost:5173/reset-password");
    await page.waitForTimeout(1000);

    await expect(page.getByText("Enlace invalido")).toBeVisible({
      timeout: 3000,
    });
    await expect(
      page.getByText(/Enlace invalido o incompleto/),
    ).toBeVisible();
  });
});
