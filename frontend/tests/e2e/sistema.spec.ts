import { expect, test } from "@playwright/test";

test("disponibilidad y latencia local de veinte comprobaciones de salud", async ({
  request
}) => {
  const tiempos: number[] = [];
  for (let i = 0; i < 20; i++) {
    const inicio = performance.now();
    expect((await request.get("/up")).status()).toBe(200);
    tiempos.push(Math.round(performance.now() - inicio));
  }
  tiempos.sort((a, b) => a - b);
  console.log(
    "Salud local ms: " +
      JSON.stringify({
        minimo: tiempos[0],
        mediana: tiempos[10],
        maximo: tiempos[19],
        solicitudes: 20
      })
  );
});

test("administración adaptable y flujo real del portal de clientes", async ({
  page
}) => {
  const errors: string[] = [];
  page.on("pageerror", e => errors.push(e.message));
  await page.goto("/#/ingresar");
  await page.getByLabel("Correo electrónico").fill("admin@example.test");
  await page
    .getByLabel("Contraseña", { exact: true })
    .fill("Clave-e2e-segura-2026");
  await page.getByRole("button", { name: "Ingresar al sistema" }).click();
  await expect(page).toHaveURL(/#\/$/);
  for (const width of [320, 390, 768, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    await expect(page.locator("h1")).toBeVisible();
    await expect
      .poll(() =>
        page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)
      )
      .toBe(true);
  }
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.getByRole("button", { name: "Menú de cuenta" }).click();
  await page.getByText("Cerrar sesión", { exact: true }).click();
  await expect(page).toHaveURL(/ingresar/);
  await page.getByRole("button", { name: "Cliente", exact: true }).click();
  await page.getByText("Soy nuevo, crear mi cuenta", { exact: true }).click();
  await page.getByLabel("Nombre", { exact: true }).fill("Cliente de prueba");
  await page.getByLabel("Correo electrónico").fill("cliente-e2e@example.test");
  await page
    .getByLabel("Contraseña", { exact: true })
    .fill("Clave-e2e-cliente-2026");
  await page.getByRole("button", { name: "Crear cuenta e ingresar" }).click();
  await expect(page).toHaveURL(/mi-cuenta/);
  await page
    .getByLabel("Inicio (Perú)", { exact: true })
    .fill("2030-12-04T18:00");
  await page.getByLabel("Fin (Perú)", { exact: true }).fill("2030-12-04T19:30");
  await page
    .getByRole("button", { name: "Ver disponibilidad del día" })
    .click();
  await expect(
    page.getByRole("status").filter({ hasText: "hasta" })
  ).toBeVisible();
  await page.getByRole("button", { name: "Confirmar reserva" }).click();
  await expect(page.getByText("Total S/")).toContainText("45");
  for (const width of [320, 390, 768, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    await expect
      .poll(() =>
        page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)
      )
      .toBe(true);
  }
  await page.getByRole("button", { name: "Cancelar", exact: true }).click();
  await page.getByRole("button", { name: "OK", exact: true }).click();
  await expect(page.getByText(/#\d+ · Cancelada/)).toBeVisible();
  await page.context().setOffline(true);
  await expect(
    page.getByRole("status").filter({ hasText: "Sin conexión" })
  ).toBeVisible();
  await page.context().setOffline(false);
  await page.getByRole("button", { name: "Salir", exact: true }).click();
  await expect(page).toHaveURL(/ingresar/);
  expect(errors).toEqual([]);
});
