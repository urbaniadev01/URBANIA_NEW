import { test, expect } from "@playwright/test";

const MFA_VERIFY_URL = "http://localhost:5173/mfa/verify";
const API_MFA_VERIFY = "**/api/v1/auth/mfa/verify";

test.describe("AUTH-B10 — Pantalla de verificación MFA", () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(MFA_VERIFY_URL);
    await page.waitForSelector('input[placeholder="000000 o XXXXX-XXXXX"]');
  });

  test("CA1 — código TOTP correcto redirige a /dashboard", async ({
    page,
  }) => {
    await page.route(API_MFA_VERIFY, async (route) => {
      const body = route.request().postDataJSON();
      expect(body.code).toBe("123456");
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          access_token: "fake-jwt-token-mfa",
          token_type: "Bearer",
          expires_in: 900,
        }),
      });
    });

    await page.fill(
      'input[placeholder="000000 o XXXXX-XXXXX"]',
      "123456",
    );
    await page.click('button:has-text("Verificar")');

    await page.waitForURL("**/dashboard", { timeout: 5000 });
    expect(page.url()).toContain("/dashboard");
  });

  test("CA2 — recovery code correcto redirige a /dashboard", async ({
    page,
  }) => {
    await page.route(API_MFA_VERIFY, async (route) => {
      const body = route.request().postDataJSON();
      expect(body.code).toBe("ABCDE-12345");
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          access_token: "fake-jwt-token-mfa",
          token_type: "Bearer",
          expires_in: 900,
        }),
      });
    });

    await page.fill(
      'input[placeholder="000000 o XXXXX-XXXXX"]',
      "ABCDE-12345",
    );
    await page.click('button:has-text("Verificar")');

    await page.waitForURL("**/dashboard", { timeout: 5000 });
    expect(page.url()).toContain("/dashboard");
  });

  test("CA3 — código inválido muestra mensaje de error", async ({
    page,
  }) => {
    await page.route(API_MFA_VERIFY, async (route) => {
      await route.fulfill({
        status: 422,
        contentType: "application/json",
        body: JSON.stringify({
          error: {
            code: "MFA_CODE_INVALID",
            message: "Código inválido.",
            trace_id: "01930000-0000-7000-8000-000000000422",
          },
        }),
      });
    });

    await page.fill(
      'input[placeholder="000000 o XXXXX-XXXXX"]',
      "000000",
    );
    await page.click('button:has-text("Verificar")');

    await expect(
      page.getByText("Código inválido. Intenta de nuevo."),
    ).toBeVisible({ timeout: 3000 });

    expect(page.url()).toContain("/mfa/verify");
  });

  test("CA4 — campo vacío muestra validación cliente", async ({
    page,
  }) => {
    let apiCalled = false;

    await page.route(API_MFA_VERIFY, async (route) => {
      apiCalled = true;
      await route.fulfill({ status: 200, body: "{}" });
    });

    await page.click('button:has-text("Verificar")');

    await expect(
      page.getByText("El código es obligatorio."),
    ).toBeVisible({ timeout: 3000 });

    await page.waitForTimeout(1000);
    expect(apiCalled).toBe(false);
  });
});
