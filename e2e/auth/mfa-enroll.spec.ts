import { test, expect } from "@playwright/test";

const MFA_ENROLL_URL = "http://localhost:5173/mfa/enroll";
const API_MFA_ENROLL = "**/api/v1/auth/mfa/enroll";
const API_MFA_CONFIRM = "**/api/v1/auth/mfa/confirm";
const API_MFA_DISABLE = "**/api/v1/auth/mfa/disable";
const API_MFA_RECOVERY = "**/api/v1/auth/mfa/recovery";

test.describe("AUTH-B11 — Pantalla de enrollment MFA", () => {
  test.beforeEach(async ({ page }) => {
    // Mock auth store — simular usuario autenticado con access_token
    await page.goto(MFA_ENROLL_URL);
    // La página redirige a /login si no hay access_token — mockeamos antes
  });

  test("CA1 — acceso sin token redirige a /login", async ({ page }) => {
    // Sin access_token en Zustand, debe redirigir
    await page.goto(MFA_ENROLL_URL);
    await page.waitForURL("**/login", { timeout: 5000 });
    expect(page.url()).toContain("/login");
  });

  test("CA2 — flujo de activación MFA: enroll → QR + recovery codes", async ({
    page,
  }) => {
    // Simular token en Zustand cargando la página con localStorage mock
    await page.evaluate(() => {
      const store = (window as unknown as Record<string, unknown>)
        .__ZUSTAND_STORE__ as Record<string, string> | undefined;
      if (store) {
        store.accessToken = "fake-access-token";
      }
    });

    // Interceptar enroll
    await page.route(API_MFA_ENROLL, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          qr_code_data_uri: "data:image/png;base64,fakeQR",
          recovery_codes: [
            "AAAAA-BBBBB",
            "CCCCC-DDDDD",
            "EEEEE-FFFFF",
            "GGGGG-HHHHH",
            "IIIII-JJJJJ",
            "KKKKK-LLLLL",
            "MMMMM-NNNNN",
            "OOOOO-PPPPP",
          ],
          enrollment_token: "fake-enrollment-token",
        }),
      });
    });

    await page.goto(MFA_ENROLL_URL);

    // Verificar que se muestra QR
    await expect(page.locator('img[src^="data:image/png;base64"]')).toBeVisible(
      { timeout: 5000 },
    );
    await expect(page.getByText("AAAAA-BBBBB")).toBeVisible();
    await expect(page.getByText("OOOOO-PPPPP")).toBeVisible();
  });

  test("CA3 — confirmación MFA redirige a panel de gestión", async ({
    page,
  }) => {
    // Mock: enrollment ya activado, confirmar con TOTP
    await page.route(API_MFA_CONFIRM, async (route) => {
      const body = route.request().postDataJSON();
      expect(body.code).toBe("123456");
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          message: "MFA activado exitosamente.",
        }),
      });
    });

    // Necesitaríamos estar en el paso 2 del enrollment — este test asume
    // que la UI ya está en ese estado. Para un test real se necesitaría
    // mockear el estado completo del enrollment.
    // Este test es un esqueleto que documenta el caso de aceptación.
    test.skip(true, "Requiere mock completo del estado de enrollment");
  });

  test("CA4 — MFA_ALREADY_ENABLED muestra mensaje apropiado", async ({
    page,
  }) => {
    await page.route(API_MFA_ENROLL, async (route) => {
      await route.fulfill({
        status: 409,
        contentType: "application/json",
        body: JSON.stringify({
          error: {
            code: "MFA_ALREADY_ENABLED",
            message: "MFA ya está activado para este usuario.",
            trace_id: "01930000-0000-7000-8000-000000000409",
          },
        }),
      });
    });

    // Este test también requiere mock de access_token
    test.skip(true, "Requiere mock de access_token en Zustand");
  });
});
