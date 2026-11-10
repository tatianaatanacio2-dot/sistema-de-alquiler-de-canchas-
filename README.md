# Sistema de alquiler de cancha

Agenda para una cancha de fútbol de césped sintético. Incluye clientes, reservas con horarios sin cruces e historial de cancelaciones. La tarifa inicial es S/ 30 por hora y la agenda usa la hora de Bogotá.

## Instalación en Windows

1. Descarga el paquete **Sistema-alquiler-de-canchas.zip** desde [Entregas](../../releases/latest) y descomprímelo. Incluye Laravel, la interfaz Quasar compilada y las dependencias PHP de producción.
2. Inicia MySQL en XAMPP. En phpMyAdmin, crea la base `canchas_alquiler` e importa `canchas_alquiler.sql`.
3. Usa PHP 8.3 o superior con la extensión `pdo_mysql`. En PowerShell, desde la carpeta descomprimida, configura la aplicación.

   ```powershell
   $php = 'C:\ruta\a\php.exe'
   Copy-Item backend\.env.example backend\.env
   & $php backend\artisan key:generate
   powershell -ExecutionPolicy Bypass -File .\iniciar.ps1 -Php $php
   ```

   Si tu instalación usa otra contraseña o usuario de MariaDB, ajusta `DB_USERNAME` y `DB_PASSWORD` en `backend/.env`.
4. Abre [la web local](http://127.0.0.1:8012/) y crea la primera cuenta de administrador.

## Android

Descarga **Alquiler-de-cancha.apk** desde [Entregas](../../releases/latest). Para usarlo desde el teléfono, inicia la web con la opción `-RedLocal`. Conecta el teléfono a la misma red y escribe en el APK la dirección que indica el script. Usa la misma cuenta de la web.

## Compilar desde el código fuente

Instala las dependencias PHP con Composer en `backend` y las de JavaScript con `npm ci` en `frontend`. Desde la raíz, ejecuta `compilar.ps1` para generar la web. Para generar el APK de pruebas, instala también las dependencias de `frontend/src-capacitor` y ejecuta `compilar.ps1 -Android` con Java 21 y Android SDK 36.
