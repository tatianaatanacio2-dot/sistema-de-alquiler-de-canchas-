# Auditoría y preparación para producción

Auditoría realizada el 24 de noviembre de 2026. Se implementaron mejoras sobre el código existente de una cancha y se conservaron los cambios locales previos. El sistema está preparado para instalarse y verificarse en un VPS; la publicación requiere dominio, servidor, firma Android y las comprobaciones externas indicadas abajo.

## Respaldo y conservación de datos

Respaldo inicial: `C:/Users/IES/.codex/backups/canchas-20261124-153139`. Incluye el código, configuración local privada, `cambios-previos.patch` y `database.sql`. Excluye dependencias reconstruibles, cachés, carpetas de compilación, Git y binarios Android. Las claves de firma no se modificaron. El AAB anterior era un archivo generado y fue reemplazado por la compilación actual sin firma.

El dump se restauró en `canchas_qa_restore_20261124_145005`. Se compararon los datos completos ordenados por ID, mediante SHA256, de administrador, cancha, clientes, reservas, pagos, gastos e historial con la base local después de migrar: todos coinciden. No se eliminaron bases de datos ni datos originales. Las bases `canchas_qa_*` se conservan para inspección; contienen únicamente fixtures y copias de prueba y no se deben publicar.

## Problemas corregidos

| Hallazgo | Cambio implementado |
| --- | --- |
| Instalación nueva dependía de importar SQL manual | Migración base compatible con tablas preexistentes, relaciones e índice de cruces; pruebas desde una base vacía |
| PHP de XAMPP es 8.2 y Laravel necesita 8.3 | Verificación en script de inicio, resolución Composer fijada a PHP 8.3.33 y compatibilidad de constantes PDO entre PHP 8.3 y 8.5 |
| Avisos de seguridad en dependencias PHP | Laravel 13.35 a 13.43 y actualización compatible de dependencias, incluidos componentes Symfony 7.4 corregidos |
| Aviso en serialize-javascript | Actualización del lock de npm y nueva auditoría sin avisos |
| No existían cuentas ni permisos de clientes | Tabla de cuentas separada, registro/login, middleware propio y portal de reservas propias |
| Riesgo de vincular a alguien con reservas previas por correo | El registro crea un contacto independiente; nunca toma posesión de contactos por coincidencia de correo |
| Recuperación administrativa dependía del entorno local | Comandos de creación y restablecimiento por consola, con clave oculta e invalidación de sesiones |
| Tokens accesibles de forma persistente en sessionStorage | Token exclusivamente en memoria en web y Android; debe iniciarse sesión al recargar o reiniciar |
| Faltaba una política global de API | Límites separados para API y autenticación, respuestas privadas, cabeceras y rechazo de HTTP en producción |
| Zona configurada como Bogotá | America/Lima en servidor y presentación de fechas |
| Reservas aceptaban intervalos desmesurados | Máximo 24 horas y rechazo explícito de total/tarifa enviados por el cliente |
| Consultas frecuentes sin índices específicos | Índices de nombre de cliente, pagos por reserva e ID y reservas por inicio/estado |
| No había despliegue independiente de Windows | Scripts Ubuntu y releases, Nginx, entorno privado, cachés, backup programado y comprobación de salud con SQL |
| Build Android release incompleto para APK | Gradle genera AAB y APK release, firma opcional por entorno, versión 1.2 código 3 y HTTP deshabilitado en release |
| Pruebas frontend de formato fallaban | Formato normalizado; oxlint revisa reglas y vue-tsc comprueba los tipos Vue |

## Controles conservados y comprobados

La agenda y las operaciones financieras bloquean la fila de la cancha mediante `SELECT ... FOR UPDATE` dentro de transacciones InnoDB. El mismo bloqueo ordena las escrituras de reservas, cancelaciones, cobros, devoluciones y gastos. La comprobación de cruces ocurre después del bloqueo; los extremos adyacentes son válidos. La consulta y el cálculo de tarifa se hacen en el servidor, con céntimos enteros y redondeo. Se conservan pagos idempotentes por UUID, límites de saldo y registro de cambios.

Los clientes no pueden registrar cobros, alterar precios, seleccionar otro cliente, consultar el historial administrativo ni cancelar reservas ajenas. La cancelación de cliente se limita a horarios futuros y conserva el importe pagado para que administración gestione la devolución. Las contraseñas usan hash bcrypt y los tokens del servidor se guardan como claves derivadas con SHA256, con caducidad de 12 horas. No hay autenticación por cookies: las mutaciones requieren Bearer y no dependen de cookies ambientales para autorizarse. CORS queda limitado por entorno y Vue escapa el contenido interpolado. La exportación CSV ya neutralizaba fórmulas y se conserva.

La interfaz conserva la identidad verde y los componentes existentes. Se añadió selector de rol, portal adaptable, aviso de desconexión, errores de servidor genéricos y mensajes de confirmación. Las rutas administrativas mantienen autorización en el backend independientemente de la navegación frontend.

## Pruebas y resultados

| Prueba ejecutada | Resultado y alcance |
| --- | --- |
| PHPUnit con PHP 8.3.33 y SQLite aislado | 22 pruebas, 222 aserciones; finanzas, autenticación, permisos, validaciones, recuperación y revocación de sesiones, migraciones y producción |
| Concurrencia con procesos independientes y MariaDB | Tres carreras: reservas, cobros y devoluciones. En cada carrera: una respuesta 200 y otra 409; una única reserva y saldos correctos |
| Migraciones sobre canchas_alquiler local | Ambas migraciones nuevas aplicadas después del backup; datos originales verificados contra copia restaurada |
| Chrome con Playwright y API/MariaDB reales aislados | Flujo administrativo y cliente: registro, disponibilidad, reserva de S/45, cancelación, cierre y modo sin conexión; adaptación entre 320 y 1440 px |
| Rendimiento básico de salud local | 20 respuestas HTTP 200; mínimo 195 ms, mediana 204 ms y máximo 222 ms. Dos pruebas Playwright aprobadas en 29,3 s. Es una comprobación local ligera, sin equivalencia con capacidad bajo carga en VPS |
| npm run lint:check y npm run typecheck | Correctos; los tipos .vue los comprueba vue-tsc |
| npm run build | SPA de producción compilada y copiada a backend/public |
| Composer validate --strict y composer audit | Configuración válida, sin avisos conocidos al consultar el registro |
| npm audit en frontend y src-capacitor | Sin avisos conocidos; no equivale a ausencia de vulnerabilidades inéditas |
| Gradle bundleRelease assembleRelease | Compilación correcta con Java 21, SDK 36; incluye lintVitalRelease |
| bash -n de scripts de instalación, despliegue, backup y salud | Sintaxis correcta; ejecución Ubuntu pendiente |
| git diff --check | Sin errores de espacios |
| Restauración SQL aislada y comparación de datos | Correcta en siete tablas originales; no se alteraron datos reales |

Los primeros intentos de pruebas revelaron falta de driver SQLite en el PHP auxiliar 8.5, una incompatibilidad de migración, interferencia entre límites numéricos y errores del servidor de pruebas y sincronización de diseño. Se corrigieron y las comprobaciones se repitieron con PHP 8.3.33, límites nombrados y el entorno de pruebas aislado. No se presenta un intento fallido como prueba aprobada.

## Artefactos de entrega

- Web compilada: `frontend/dist/spa` y copia servida en `backend/public`.
- Código backend, migraciones y pruebas: `backend`.
- APK release sin firma: `Alquiler-de-cancha-produccion-sin-firmar.apk`.
- AAB sin firma: `Alquiler-de-cancha-sin-firmar.aab`.
- Despliegue y mantenimiento: `deploy/OPERACION.md`, `nginx.conf`, `production.env.example`, scripts y unidades systemd.
- Relación de archivos: `deploy/ARCHIVOS.txt`. Incluye cambios anteriores conservados y normalización de formato, además de cambios funcionales; los archivos ignorados compilados se indican arriba.
- Integridad de APK y AAB: `deploy/SHA256SUMS.txt`.

Los paquetes Android contienen `https://canchas.example.com` como ejemplo. No están listos para distribuir a clientes: requieren URL real y firma propia. El código fuente permite seleccionar otro servidor HTTPS, pero la entrega comercial debe recompilarse con el dominio definitivo. No se firmó con claves inventadas ni se publicó en Play.

## Pendientes para abrir el servicio

1. Elegir VPS y dominio, configurar DNS, credenciales de BD, APP_KEY, SSL, firewall y usuario administrativo según el manual. No se contrató ni publicó ningún servicio.
2. Ejecutar Nginx y los scripts en Ubuntu real; verificar permisos, renovación SSL, arranque automático y restauración de copias externas cifradas. Los scripts se comprobaron sintácticamente en Windows, sin simular un despliegue real.
3. Probar APK firmado en dispositivos Android físicos, teclado, rotación, TalkBack, redes móviles, certificados y sesiones. La compilación no sustituye esa validación.
4. Proporcionar la clave de subida de Play por un canal local seguro y configurar firma sin incluirla en el repositorio. Completar ficha y política de privacidad de Google Play; confirmar requisitos vigentes al publicar.
5. Publicar aviso de privacidad y definir conservación, atención de derechos, contratos con proveedores y respuesta a incidentes conforme a la normativa peruana. El software no certifica cumplimiento legal.
6. Configurar correo y verificación de identidad si se requiere recuperación autoservicio. Actualmente la recuperación es por consola tras verificación por soporte. Los correos no se consideran identidad verificada.
7. Medir capacidad con datos y tráfico representativos en staging. Los listados conservan compatibilidad de respuesta y algunos devuelven todos los registros; para grandes volúmenes conviene paginación de servidor y búsqueda de clientes. El bloqueo por cancha favorece consistencia y serializa escrituras; el sistema sigue limitado a una cancha y una instancia de aplicación con cache en disco.

Consulta los [requisitos de Laravel](https://laravel.com/framework/docs/13.x/deployment), los [requisitos de Android](https://developer.android.com/google/play/requirements/target-sdk) y el [Reglamento de protección de datos publicado por la ANPD](https://www.gob.pe/institucion/anpd/normas-legales/6554453-16-2024-jus). Estos requisitos pueden cambiar; revisa los enlaces antes de publicar.
