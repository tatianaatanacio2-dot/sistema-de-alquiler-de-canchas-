# Sistema de alquiler de cancha

Agenda para una cancha de fútbol de césped sintético. Incluye clientes, reservas con horarios sin cruces e historial de cancelaciones. La tarifa inicial es S/ 30 por hora y la agenda usa la hora de Perú (America/Lima).

La preparación para VPS, Nginx, HTTPS, copias, actualizaciones y Android está en [Instalación y mantenimiento](deploy/OPERACION.md). Consulta [la auditoría y sus pruebas](deploy/AUDITORIA.md) antes de publicar. La aplicación incluye un portal de clientes con registro, reservas propias y cancelación de horarios futuros. Selecciona **Cliente** al iniciar sesión; las pantallas administrativas requieren una cuenta administrativa.

En una instalación nueva basta con crear una base vacía y ejecutar `php artisan migrate --force`; las migraciones crean las tablas sin importar el SQL. Las instalaciones existentes conservan sus datos. Crea la cuenta administrativa con `php artisan canchas:administrador`, también en producción. La web y Android mantienen el token solo en memoria: al recargar o cerrar debes volver a ingresar.

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

1. Instala **Alquiler-de-cancha.apk** desde [Entregas](../../releases/latest) o compílalo con las instrucciones de abajo.
2. Para usarlo por Wi-Fi, inicia el servidor en la computadora del local con `powershell -ExecutionPolicy Bypass -File .\iniciar.ps1 -RedLocal -Php $php`. Conecta el teléfono a la misma red. Si el teléfono no alcanza el servidor, comprueba que el firewall de Windows permita conexiones al puerto TCP 8012 en la red privada.
3. En la app, escribe en **Servidor** la dirección que imprime el script, por ejemplo `http://192.168.1.10:8012`, y pulsa **Conectar**. También puedes escribir `192.168.1.10:8012`. Para un servidor accesible por Internet, usa una dirección HTTPS con certificado válido, por ejemplo `https://canchas.ejemplo.com`. Introduce solo la dirección base, sin `/api` ni otras rutas.
4. Inicia sesión con la cuenta de la web. Si aún no existe, créala primero en el navegador de la computadora que aloja el sistema.

La app comprueba la conexión antes de guardar la dirección. En los siguientes inicios se conecta a la última dirección guardada. Para cambiar de servidor con una sesión abierta, cierra sesión y edita el campo **Servidor**.

## Compilar desde el código fuente

Instala las dependencias PHP con Composer en `backend` y las de JavaScript con `npm ci` en `frontend`. Desde la raíz, ejecuta `.\compilar.ps1` para generar la web.

Para compilar Android, ejecuta también `npm ci` en `frontend/src-capacitor`. Usa Java 21 (`JAVA_HOME` o `-JavaHome`) y Android SDK 36 (`ANDROID_HOME` o la ubicación de Android Studio en `%LOCALAPPDATA%\Android\Sdk`). Desde la raíz, ejecuta lo siguiente.

```powershell
.\compilar.ps1 -Android
```

El script genera **Alquiler-de-cancha.apk** en la raíz. Es un APK de pruebas (debug), que admite HTTP para direcciones IPv4 privadas en la red local.

Para incluir un servidor inicial, define `CANCHAS_SERVIDOR` antes de compilar.

```powershell
$env:CANCHAS_SERVIDOR = 'https://canchas.ejemplo.com'
.\compilar.ps1 -Android
Remove-Item Env:CANCHAS_SERVIDOR
```

El APK intenta conectarse al servidor inicial cuando todavía no hay una dirección guardada. El campo sigue disponible para conectar con otro servidor, incluida la dirección HTTP que imprime `iniciar.ps1 -RedLocal`. También puedes compilar con esa dirección local como valor inicial.

## Preparar una publicación en Google Play

1. Aloja Laravel detrás de un servidor web que sirva `backend/public` con un certificado HTTPS válido. Configura la base de datos, conserva o genera `APP_KEY` según sea una actualización o una instalación nueva, y ejecuta las migraciones. Crea la primera cuenta con `php artisan canchas:administrador` por consola. Usa `APP_ENV=production`, `APP_DEBUG=false` y genera la caché con `php artisan config:cache`. En producción se desactiva la creación de la primera cuenta por HTTP.
2. Introduce la dirección HTTPS del sitio al compilar el Android App Bundle. La app usará esa dirección en el primer inicio.

   ```powershell
   $env:CANCHAS_SERVIDOR = 'https://canchas.ejemplo.com'
   .\compilar.ps1 -Publicacion
   Remove-Item Env:CANCHAS_SERVIDOR
   ```

3. Firma `Alquiler-de-cancha-sin-firmar.aab` con la clave de subida asociada a tu cuenta de Play Console. `jarsigner` solicitará la contraseña. Guarda la clave fuera del repositorio e incrementa `versionCode` en `frontend/src-capacitor/android/app/build.gradle` para cada entrega.

   ```powershell
   jarsigner -keystore 'C:\ruta\subida.jks' -signedjar Alquiler-de-cancha.aab Alquiler-de-cancha-sin-firmar.aab alias-de-subida
   jarsigner -verify Alquiler-de-cancha.aab
   ```

4. Sube el AAB firmado a una pista de pruebas en Play Console. Comprueba el acceso, las reservas y el cierre de sesión en un dispositivo con el servidor HTTPS definitivo. Completa la ficha, las declaraciones de privacidad y los datos de acceso para revisión antes de solicitar producción. [Publicar con Play Console](https://support.google.com/googleplay/android-developer/answer/9859152?hl=es)

La aplicación administra una cancha. Los contactos previos se conservan separados de las cuentas creadas en el nuevo portal; no se vinculan automáticamente por correo para evitar que alguien pueda apropiarse de reservas antiguas. El panel administrativo sigue usando la cuenta administrativa del servidor.

Para preparar un paquete de publicación, establece `CANCHAS_SERVIDOR` con la dirección HTTPS pública y ejecuta `.\compilar.ps1 -Publicacion`. Esta variante acepta conexiones HTTPS y genera **Alquiler-de-cancha-sin-firmar.aab** en la raíz para firmarlo antes de publicarlo.
