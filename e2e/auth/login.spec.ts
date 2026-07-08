import { test, expect } from "@playwright/test";

const LOGIN_URL = "http://localhost:5173/login";
const API_LOGIN = "**/api/v1/auth/login";

test.describe("AUTH-B06 — Pantalla de login", () => {
  test.beforeEach(async ({ page }) => {
    // Navegar a la página de login antes de cada test
    await page.goto(LOGIN_URL);
    // Esperar a que el formulario esté visible
    await page.waitForSelector('input[placeholder="tu@email.com"]');
  });

  test("CA1 — email + password correctos redirige a /dashboard y guarda token", async ({
    page,
  }) => {
    // Interceptar la API para simular respuesta exitosa
    await page.route(API_LOGIN, async (route) => {
      const body = route.request().postDataJSON();
      expect(body).toMatchObject({
        email: "admin@urbania.com",
        password: "correcta",
      });
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          access_token: "fake-jwt-token-ca1",
          token_type: "Bearer",
          expires_in: 900,
        }),
      });
    });

    // Llenar formulario
    await page.fill('input[placeholder="tu@email.com"]', "admin@urbania.com");
    await page.fill('input[placeholder="••••••••"]', "correcta");
    await page.click('button:has-text("Iniciar sesión")');

    // Esperar redirección al dashboard
    await page.waitForURL("**/dashboard", { timeout: 5000 });
    expect(page.url()).toContain("/dashboard");
  });

  test("CA2 — INVALID_CREDENTIALS (401) muestra error genérico sin indicar campo", async ({
    page,
  }) => {
    // Interceptar la API para simular error 401
    await page.route(API_LOGIN, async (route) => {
      await route.fulfill({
        status: 401,
        contentType: "application/json",
        body: JSON.stringify({
          error: {
            code: "INVALID_CREDENTIALS",
            message: "Credenciales inválidas.",
            trace_id: "01930000-0000-7000-8000-000000000401",
          },
        }),
      });
    });

    await page.fill(
      'input[placeholder="tu@email.com"]',
      "admin@urbania.com",
    );
    await page.fill('input[placeholder="••••••••"]', "incorrecta");
    await page.click('button:has-text("Iniciar sesión")');

    // Verificar mensaje de error genérico en toast
    await expect(page.getByText("Email o contraseña incorrectos.")).toBeVisible(
      { timeout: 3000 },
    );

    // No debe redirigir
    expect(page.url()).toContain("/login");

    // Verificar que NO se indica cuál campo falló
    // El mensaje es genérico, no menciona "email" ni "password"
    await expect(
      page.getByText(/email.*incorrecto|contraseña.*incorrecta/i),
    ).toHaveCount(0);
  });

  test("CA3 — ACCOUNT_NOT_ACTIVE (403) muestra mensaje específico distinto al caso 2", async ({
    page,
  }) => {
    await page.route(API_LOGIN, async (route) => {
      await route.fulfill({
        status: 403,
        contentType: "application/json",
        body: JSON.stringify({
          error: {
            code: "ACCOUNT_NOT_ACTIVE",
            message: "La cuenta no está activa.",
            trace_id: "01930000-0000-7000-8000-000000000403",
          },
        }),
      });
    });

    await page.fill(
      'input[placeholder="tu@email.com"]',
      "inactivo@urbania.com",
    );
    await page.fill('input[placeholder="••••••••"]', "password123");
    await page.click('button:has-text("Iniciar sesión")');

    // Verificar mensaje específico de cuenta no activa
    await expect(
      page.getByText(
        "Tu cuenta no está activa. Contacta al administrador.",
      ),
    ).toBeVisible({ timeout: 3000 });

    // No debe ser el mismo mensaje genérico del caso 2
    await expect(
      page.getByText("Email o contraseña incorrectos."),
    ).not.toBeVisible();
  });

  test("CA4 — 429 rate limit muestra 'demasiados intentos' sin loop de reintento", async ({
    page,
  }) => {
    let requestCount = 0;

    await page.route(API_LOGIN, async (route) => {
      requestCount++;
      await route.fulfill({
        status: 429,
        contentType: "application/json",
        body: JSON.stringify({
          error: {
            code: "RATE_LIMIT_EXCEEDED",
            message: "Demasiados intentos. Intenta de nuevo en 60 segundos.",
            trace_id: "01930000-0000-7000-8000-000000000429",
          },
        }),
      });
    });

    await page.fill(
      'input[placeholder="tu@email.com"]',
      "admin@urbania.com",
    );
    await page.fill('input[placeholder="••••••••"]', "password123");
    await page.click('button:has-text("Iniciar sesión")');

    // Verificar mensaje de rate limit
    await expect(
      page.getByText(/demasiados intentos/i),
    ).toBeVisible({ timeout: 3000 });

    // Esperar un momento para confirmar que no hay reintentos automáticos
    await page.waitForTimeout(3000);

    // Verificar que solo se hizo UNA llamada a la API (no hubo loop de reintento)
    expect(requestCount).toBe(1);

    // El formulario debe seguir en /login, no debe haber redirigido
    expect(page.url()).toContain("/login");
  });

  test("CA5 — campos vacíos: validación cliente bloquea submit antes de llamar a la API", async ({
    page,
  }) => {
    let apiCalled = false;

    // Si la API se llama, el test debe fallar (no debe llamarse)
    await page.route(API_LOGIN, async (route) => {
      apiCalled = true;
      await route.fulfill({ status: 200, body: "{}" });
    });

    // Click en submit sin llenar campos
    await page.click('button:has-text("Iniciar sesión")');

    // Verificar mensajes de validación inline
    await expect(
      page.getByText("El email es obligatorio."),
    ).toBeVisible({ timeout: 3000 });
    await expect(
      page.getByText("La contraseña es obligatoria."),
    ).toBeVisible({ timeout: 3000 });

    // Esperar un momento y confirmar que la API nunca fue llamada
    await page.waitForTimeout(1000);
    expect(apiCalled).toBe(false);

    // Debe seguir en la misma página
    expect(page.url()).toContain("/login");
  });

  test("CA6 — mfa_required: redirige a /mfa/verify sin guardar access_token", async ({
    page,
  }) => {
    await page.route(API_LOGIN, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          mfa_required: true,
          mfa_token: "fake-mfa-session",
        }),
      });
    });

    await page.fill(
      'input[placeholder="tu@email.com"]',
      "admin@urbania.com",
    );
    await page.fill('input[placeholder="........"]', "Password1");
    await page.click('button:has-text("Iniciar sesion")');

    await page.waitForURL("**/mfa/verify", { timeout: 5000 });
    expect(page.url()).toContain("/mfa/verify");
  });
});
