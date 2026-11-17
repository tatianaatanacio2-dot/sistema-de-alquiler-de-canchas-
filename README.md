# Sistema de alquiler de cancha

Agenda para una cancha de fútbol de césped sintético. Incluye clientes, reservas con horarios sin cruces e historial de cancelaciones. La tarifa inicial es S/ 30 por hora y la agenda usa la hora de Bogotá.

La rama `tatiana` incorpora Pagos, Caja diaria e Historial de cambios. Los cobros admiten adelantos y devoluciones en efectivo, Yape, Plin, transferencia o tarjeta. Caja muestra cobros, devoluciones, gastos y neto por día y método. Los cambios realizados desde la activación del historial guardan el usuario y los valores anteriores y nuevos.

Para actualizar una instalación existente, ejecuta `php artisan migrate --force` dentro de `backend`, con PHP 8.3 o superior, y después compila la interfaz con `compilar.ps1`. Cancelar una reserva conserva los pagos y permite registrar la devolución desde Pagos. Los gastos pueden anularse conservando su registro.

En Nueva reserva puedes registrar el adelanto o el pago completo y seleccionar el método de pago. La reserva y su cobro se guardan juntos. El listado muestra el importe abonado, el saldo y el último método utilizado.

Agenda y disponibilidad muestra los horarios ocupados y los intervalos libres, y permite preparar una nueva reserva. El Resumen incluye los cobros, gastos, neto del día y saldos por cobrar.

Desde Reservas puedes abrir la constancia del alquiler e imprimirla o guardarla en PDF con el navegador. Clientes ofrece el historial de reservas y sus pagos. Reportes filtra las reservas por fecha de inicio y estado, muestra los saldos actuales y exporta los resultados a CSV.

Para cargar datos ficticios de uso diario, ejecuta `php artisan canchas:demostracion` desde `backend`. Agrega ejemplos para hoy y los siguientes seis días, reutiliza los ya creados y evita horarios ocupados. Los cobros y gastos se identifican con DEMO y el historial usa el usuario Demostración.

## Instalación en Windows

1. Descarga el paquete **Sistema-alquiler-de-canchas.zip** desde [Entregas](../../releases/latest) y descomprímelo. Incluye Laravel, la interfaz Quasar compilada y las dependencias PHP de producción.
2. Inicia MySQL en XAMPP. En phpMyAdmin, crea la base `canchas_alquiler` e importa `canchas_alquiler.sql`.
3. Usa PHP 8.3 o superior con la extensión `pdo_mysql`. En PowerShell, desde la carpeta descomprimida, configura la aplicación.

   ```powershell
   $php = 'C:\ruta\a\php.exe'
   Copy-Item backend\.env.example backend\.env
   & $php backend\artisan key:generate
   & $php backend\artisan migrate --force
   powershell -ExecutionPolicy Bypass -File .\iniciar.ps1 -Php $php
   ```

   Si tu instalación usa otra contraseña o usuario de MariaDB, ajusta `DB_USERNAME` y `DB_PASSWORD` en `backend/.env`.
4. Abre [la web local](http://127.0.0.1:8012/) y crea la primera cuenta de administrador.

## Android

Descarga **Alquiler-de-cancha.apk** desde [Entregas](../../releases/latest). Para usarlo desde el teléfono, inicia la web con la opción `-RedLocal`. Conecta el teléfono a la misma red y escribe en el APK la dirección que indica el script. Usa la misma cuenta de la web.

## Compilar desde el código fuente

Instala las dependencias PHP con Composer en `backend` y las de JavaScript con `npm ci` en `frontend`. Desde la raíz, ejecuta `compilar.ps1` para generar la web. Para generar el APK de pruebas, instala también las dependencias de `frontend/src-capacitor` y ejecuta `compilar.ps1 -Android` con Java 21 y Android SDK 36.
