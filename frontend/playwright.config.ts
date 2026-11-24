import { defineConfig } from "@playwright/test";
export default defineConfig({
  testDir: "./tests/e2e",
  workers: 1,
  timeout: 90000,
  use: {
    baseURL: "http://127.0.0.1:8014",
    channel: "chrome",
    screenshot: "only-on-failure"
  },
  webServer: {
    command: "node tests/start-e2e.mjs",
    url: "http://127.0.0.1:8014/up",
    reuseExistingServer: false,
    timeout: 30000
  }
});
