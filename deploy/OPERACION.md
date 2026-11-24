# Instalación y mantenimiento de canchas

El sistema permite administrar una cancha, sus contactos, reservas, cobros y gastos. El portal de clientes permite crear una cuenta, reservar, consultar sus reservas y cancelar horarios futuros. Los pagos se registran exclusivamente desde administración; Yape, Plin y tarjeta son métodos de registro contable, sin pasarela bancaria ni cargos automáticos.

## Preparar Ubuntu

Utiliza Ubuntu 24.04 con PHP 8.3.33 o superior compatible, Node.js 24 LTS, Composer 2 y MariaDB con InnoDB. La resolución de Composer está fijada a PHP 8.3.33 para mantener compatibilidad; el PHP 8.2 de XAMPP no es suficiente. Consulta los [requisitos de Laravel 13](https://laravel.com/framework/docs/13.x/deployment).

1. Copia el repositorio al VPS sin `.env`, firmas, `.local`, dependencias ni APK. Ejecuta `sudo bash deploy/install-ubuntu.sh`. Instala Node 24 y comprueba `node --version`, `php --version` y `composer --version`.
2. Configura acceso SSH por clave, actualizaciones de seguridad y firewall con SSH, HTTP y HTTPS. MariaDB y PHP-FPM deben escuchar solo localmente. Conserva una sesión SSH antes de cambiar el firewall.
3. Crea `canchas_alquiler` con `utf8mb4_unicode_ci`. Crea un usuario de aplicación restringido a esa base, con SELECT, INSERT, UPDATE y DELETE. Usa una cuenta de despliegue con permisos de estructura exclusivamente durante las migraciones; no uses root en la aplicación. El script de despliegue ejecuta las migraciones con la cuenta configurada: durante ese paso debe contar con CREATE, ALTER, INDEX y REFERENCES; revócalos después.
4. Copia `deploy/production.env.example` a `/srv/canchas/shared/backend.env`, con propietario `canchas:www-data`, permiso `640`. Cambia URL, usuario y contraseña; crea una `APP_KEY` con `php artisan key:generate --show` en una copia de preparación. Guarda esa clave en el archivo privado. No regeneres la clave de instalaciones existentes.
5. Si hay datos previos, crea y verifica un dump antes de migrar. Una instalación nueva requiere únicamente la base vacía y `php artisan migrate --force`, sin importar el SQL antiguo.
6. Copia el código a una carpeta legible por `canchas` y ejecuta `sudo -u canchas bash deploy/deploy.sh /ruta/al/codigo`. El frontend se compila y se sirve desde `backend/public`; backend y web usan el mismo dominio.
7. Crea la cuenta con `sudo -u canchas php /srv/canchas/current/backend/artisan canchas:administrador`. La contraseña se pide sin mostrarla. Para recuperar la cuenta administrativa usa el mismo comando con `--restablecer`; invalida las sesiones anteriores.

## Dominio y HTTPS

Apunta el registro DNS del dominio al VPS. Prepara primero un sitio Nginx HTTP temporal que sirva `backend/public`, sin exponer el directorio backend. Obtén el certificado con `sudo certbot certonly --nginx -d TU_DOMINIO` y sustituye todas las apariciones de `canchas.example.com` en `deploy/nginx.conf`. Instala el archivo en `/etc/nginx/sites-available/canchas`, activa su enlace en `sites-enabled`, elimina o desactiva el sitio predeterminado si intercepta el dominio, ejecuta `sudo nginx -t` y recarga Nginx. Comprueba `sudo certbot renew --dry-run`.

La configuración sirve exclusivamente `backend/public`, permite ejecutar solo `index.php`, bloquea archivos ocultos y añade HTTPS, CSP y cabeceras de seguridad. No publiques el repositorio bajo una raíz web general. La aplicación rechaza HTTP en producción. Esta configuración termina TLS en Nginx y no necesita confiar en proxies externos; si introduces un balanceador configura explícitamente sus IP y las cabeceras reenviadas.

Comprueba `curl --fail https://TU_DOMINIO/up`: verifica arranque y conexión SQL. Desde fuera del VPS, monitoriza esa URL y configura alertas con tu proveedor elegido. El proceso de PHP-FPM y MariaDB se inicia mediante systemd y no depende de Windows. No hay trabajos asíncronos actuales: `QUEUE_CONNECTION=sync`, sin worker ni cron de Laravel necesario.

## Copias y restauración

Configura `/etc/canchas/backup.cnf`, propietario `canchas`, permiso `600`, con `[client]`, `user=USUARIO_BACKUP`, `password=CLAVE` y `host=127.0.0.1`. Otorga a ese usuario SELECT, SHOW VIEW y TRIGGER sobre `canchas_alquiler`; la aplicación no crea rutinas. Crea `/var/backups/canchas` con propietario `canchas` y permiso `700`. Ejecuta `sudo -u canchas bash deploy/backup.sh` y valida el resultado. El archivo `private-*` contiene secretos: cópialo cifrado a almacenamiento externo con acceso restringido.

Instala `canchas-backup.service` y `canchas-backup.timer` en `/etc/systemd/system/`, ejecuta `systemctl daemon-reload` y `systemctl enable --now canchas-backup.timer`. Comprueba `systemctl list-timers` y `journalctl -u canchas-backup.service`. La copia se programa a las 03:00 de Lima y usa una transacción consistente. No elimina copias: establece una retención según el negocio y verifica espacio disponible.

Cada mes prueba la restauración en otra base vacía con credenciales y entorno aislados. Verifica SHA256, ejecuta `gzip -t`, importa con `gzip -dc database-FECHA.sql.gz | mariadb --defaults-extra-file=/ruta/credenciales.cnf BASE_PRUEBAS`, configura una copia de la aplicación hacia esa base y comprueba reservas y saldos. Restaura también `storage/app` y la clave original solo en el entorno autorizado. Nunca importes sobre la base real sin una ventana de mantenimiento y una copia reciente. El backup inicial de esta auditoría está fuera del repositorio y conserva el SQL, el código y los cambios previos; las dependencias se reconstruyen desde sus archivos lock.

## Actualizar y revertir

Prueba primero en una copia aislada: `composer install`, `php vendor/phpunit/phpunit/phpunit`, `npm ci`, `npm run lint:check`, `npm run typecheck`, `npm run build`. Ejecuta `php tests/concurrencia-mysql.php` únicamente con una cuenta local de pruebas con permiso CREATE DATABASE; conserva una base `canchas_qa_*` sin modificar `canchas_alquiler`.

Despliega con `deploy.sh` como `canchas`. El script bloquea despliegues simultáneos, construye una release separada, respalda la instalación anterior, migra, genera cachés y cambia el enlace `current`. Comprueba `/up`, inicio de sesión, creación/cancelación de reserva y registro de un cobro en staging antes de abrir a clientes. Conserva la release anterior; para revertir código crea otro enlace simbólico y reemplaza `current` atómicamente. Las migraciones nuevas son aditivas y sus reversiones están bloqueadas para conservar datos. No uses `migrate:fresh`, `db:wipe` ni un rollback de datos en producción.

Revisa semanalmente `storage/logs`, `/var/log/nginx`, disco, memoria, certificados y resultados de backups. Los logs Laravel rotan a diario y conservan 14 archivos. Los logs Nginx usan la rotación de Ubuntu. Las sesiones duran 12 horas y sus tokens se almacenan con hash en el cache del servidor. Cambiar una contraseña invalida sus sesiones; vaciar el caché también cierra todas las sesiones. Las credenciales del navegador y Android se conservan solo en memoria: recargar o cerrar la app requiere ingresar de nuevo.

## Android

Usa Java 21 y SDK 36. Define `CANCHAS_SERVIDOR=https://TU_DOMINIO` y ejecuta `./compilar.ps1 -Publicacion` en Windows. Se generan un AAB y un APK release sin firmar si no hay clave de subida. Los paquetes entregados con `https://canchas.example.com` son artefactos de compilación y necesitan recompilarse con el servidor definitivo.

Para firmar durante Gradle, configura `CANCHAS_KEYSTORE`, `CANCHAS_STORE_PASSWORD`, `CANCHAS_KEY_ALIAS` y `CANCHAS_KEY_PASSWORD` en el entorno de compilación y elimínalos al terminar. La clave debe estar fuera del repositorio. Con firma se generan `Alquiler-de-cancha.aab` y `Alquiler-de-cancha-produccion.apk`. No inventes ni reemplaces una firma existente. Verifica con `jarsigner -verify` para AAB y `apksigner verify` para APK. Incrementa `versionCode` en cada publicación. Los builds de publicación bloquean HTTP; debug permite HTTP privado para desarrollo.

La app tiene permiso de Internet, backup desactivado y no contiene claves de BD ni tokens persistentes. Conserva los iconos y splash existentes. Antes de distribuir prueba en dispositivos físicos: arranque, teclado, orientación, cambio de red, sesión expirada, pagos, impresión y accesibilidad con TalkBack. Completa política de privacidad, seguridad de datos, clasificación y acceso para revisión en Play Console. El SDK objetivo es 36; confirma los [requisitos vigentes de Google Play](https://developer.android.com/google/play/requirements/target-sdk) en cada publicación.

## Datos personales y tareas del titular

Identifica al responsable del tratamiento y publica un aviso que explique finalidad, datos necesarios, destinatarios, conservación y canal para ejercer derechos. Evalúa la inscripción del banco de datos y contratos con el proveedor VPS y de copias. Define el procedimiento de solicitudes y respuesta a incidentes conforme a la Ley 29733 y al [Reglamento publicado por la ANPD](https://www.gob.pe/institucion/anpd/normas-legales/6554453-16-2024-jus). El código no certifica cumplimiento legal.

El historial conserva cambios de datos personales y está restringido a administración. No borra automáticamente información financiera: define plazos y un proceso de anonimización que respete obligaciones de conservación. No introduzcas números de tarjeta, CVV ni claves bancarias en notas de pago. El registro de clientes no verifica correo ni incluye recuperación por email. Tras verificar la identidad por soporte, recupera una cuenta con `php artisan canchas:cliente-restablecer correo@ejemplo.com`; la nueva clave se pide sin mostrarla e invalida sesiones anteriores.
