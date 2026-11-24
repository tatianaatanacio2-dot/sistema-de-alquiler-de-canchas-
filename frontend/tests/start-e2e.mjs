import { spawn, execFileSync } from "node:child_process";
import { resolve } from "node:path";
const php = process.env.CANCHAS_TEST_PHP || "php";
const backend = resolve("../backend");
const database = execFileSync(php, ["tests/preparar-e2e.php"], {
  cwd: backend,
  encoding: "utf8"
}).trim();
if (!/^canchas_qa_e2e_[a-zA-Z0-9_]+$/.test(database))
  throw new Error("Base de pruebas inválida");
console.log("Base aislada conservada: " + database);
const child = spawn(
  php,
  [
    "-S",
    "127.0.0.1:8014",
    "-t",
    ".",
    resolve(
      backend,
      "vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"
    )
  ],
  {
    cwd: resolve(backend, "public"),
    stdio: "inherit",
    windowsHide: true,
    env: {
      ...process.env,
      APP_ENV: "local",
      APP_DEBUG: "false",
      DB_CONNECTION: "mysql",
      DB_DATABASE: database,
      DB_URL: "",
      CACHE_PATH: resolve("../.local/cache-" + database),
      CACHE_STORE: "file"
    }
  }
);
for (const signal of ["SIGINT", "SIGTERM"])
  process.on(signal, () => {
    child.kill();
  });
child.on("exit", code => process.exit(code || 0));
