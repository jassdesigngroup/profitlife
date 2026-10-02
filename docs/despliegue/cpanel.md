# Despliegue en cPanel — app.profit-life.co

Guía para publicar PROFITLIFE en un hosting cPanel con el subdominio `app.profit-life.co`.
`USUARIO` es su usuario de cPanel: en este hosting es `profitlife` (carpeta `/home/profitlife`).
El servidor web es LiteSpeed (existe la carpeta `lscache`); es compatible con el `.htaccess` de Laravel.

## 0. Requisitos del hosting

Verificado en este hosting (octubre de 2026):

| Requisito | Servidor | Estado |
|---|---|---|
| PHP 8.3 o superior | PHP 8.4 | ✅ Suite completa probada con PHP 8.4 |
| Base de datos | MariaDB 11.4.13 | ✅ Suite completa probada con MariaDB 11.4.13 (conexión `mariadb`) |
| Extensiones `intl`, `pdo_mysql`, `mbstring`, `bcmath`, `fileinfo`, `sodium`, `openssl`, `gd`, `zip` | *Select PHP Version → Extensions* | Revisar que `intl` esté activa |
| Acceso SSH o *Terminal* | *Terminal* o *SSH Access* | Necesario para `composer` y `php artisan` |
| Cron jobs | *Cron Jobs* | Para la cola de correos |

En MariaDB las columnas JSON se guardan como `LONGTEXT` con validación JSON; Laravel lo maneja de forma
transparente y no cambia nada del ERD.

## 1. Subdominio y SSL

El subdominio ya existe y cPanel creó su carpeta `/home/profitlife/app.profit-life.co`.
El código se instala **dentro de esa carpeta** y la web debe apuntar solo a su subcarpeta `public`:

1. *Domains* → `app.profit-life.co` → *Manage* → **Document Root**: cambiar `app.profit-life.co`
   por **`app.profit-life.co/public`** y guardar.
   Así `.env`, el código y `storage` quedan fuera del alcance de la web.
2. *SSL/TLS Status → Run AutoSSL* para emitir el certificado del subdominio.
3. *LiteSpeed Web Cache Manager* (si aparece): no active la caché para este subdominio; la aplicación
   tiene sesiones y datos privados por usuario.

Si su cPanel no permite editar el Document Root, use la alternativa del paso 4.

## 2. Base de datos

En *MySQL Databases*:

1. Crear la base `app` (cPanel la nombra `profitlife_app`).
2. Crear el usuario `app` (queda `profitlife_app`) con una contraseña fuerte.
3. Añadir el usuario a la base con **ALL PRIVILEGES**.

## 3. Correo de envío

1. *Email Accounts*: crear `no-responder@profit-life.co`.
2. *Email Deliverability*: comprobar que SPF y DKIM de `profit-life.co` estén en verde (si no, los correos de invitación llegarán a spam).
3. *Connect Devices* muestra el servidor SMTP (normalmente `mail.profit-life.co`, puerto 465, SSL).

## 4. Subir el código

Por Terminal/SSH:

```bash
cd ~/app.profit-life.co
ls -A                  # debe estar vacía; si cPanel dejó algo (p. ej. cgi-bin, .htaccess), bórrelo
git clone https://github.com/jassdesigngroup/profitlife.git .
git checkout main      # cuando el PR de la Fase 2 esté aprobado y fusionado
composer install --no-dev --optimize-autoloader
```

**Alternativa sin editar el Document Root**: instale el código en otra carpeta y convierta la del
subdominio en un enlace a `public`:

```bash
cd ~
git clone https://github.com/jassdesigngroup/profitlife.git profitlife
rm -rf ~/app.profit-life.co            # solo si está vacía
ln -s ~/profitlife/public ~/app.profit-life.co
```
En ese caso, en el resto de la guía use `~/profitlife` en lugar de `~/app.profit-life.co`.

Si el repositorio es privado, cree un *token* de GitHub de solo lectura o una *deploy key* para clonar.
Si `composer` no existe en el servidor: `curl -sS https://getcomposer.org/installer | php` y use `php composer.phar`.

### Assets (CSS, JS y fuentes)

Los hostings cPanel casi nunca tienen Node. Compílelos en su equipo y suba solo la carpeta resultante:

```bash
# en su equipo, dentro del proyecto
npm ci && npm run build
# subir la carpeta public/build completa a /home/profitlife/app.profit-life.co/public/build
# (File Manager → Upload un .zip y Extract, o scp/rsync)
```

## 5. Archivo `.env` de producción

```bash
cp .env.example .env
php artisan key:generate
```

Edite `.env` (File Manager o `nano .env`) con estos valores:

```dotenv
APP_NAME="PROFITLIFE"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.profit-life.co

APP_DISPLAY_TIMEZONE=America/Bogota
APP_LOCALE=es_CO
APP_CURRENCY=COP

LOG_STACK=daily
LOG_LEVEL=warning

DB_CONNECTION=mariadb
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=profitlife_app
DB_USERNAME=profitlife_app
DB_PASSWORD="la-contraseña-de-la-base"

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=app.profit-life.co

QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=mail.profit-life.co
MAIL_PORT=465
MAIL_USERNAME=no-responder@profit-life.co
MAIL_PASSWORD="la-contraseña-del-correo"
MAIL_FROM_ADDRESS="no-responder@profit-life.co"
MAIL_FROM_NAME="PROFITLIFE"
```

`APP_DEBUG` debe quedar en `false`: con `true` un error mostraría credenciales en pantalla.

## 6. Instalar

```bash
php artisan migrate --force
php artisan db:seed --force                 # roles, permisos y ajustes (sin datos de ejemplo)
php artisan profitlife:create-super-admin   # pide nombres, correo y contraseña
php artisan storage:link
php artisan optimize                        # cachea configuración, rutas y vistas
chmod -R 775 storage bootstrap/cache
```

Luego entre a `https://app.profit-life.co`, configure la verificación en dos pasos y cree desde el panel
las sedes, sus horarios y salas, e invite al equipo.

## 7. Cron (cola de correos y tareas)

En *Cron Jobs*, añada una tarea **cada minuto** (`* * * * *`):

```bash
cd /home/profitlife/app.profit-life.co && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

La ruta de PHP puede variar; compruébela en Terminal con `which php` (debe ser la versión 8.3, p. ej.
`/opt/cpanel/ea-php83/root/usr/bin/php`). Sin este cron las invitaciones y correos de recuperación no se envían.

## 8. Actualizar a una nueva versión

```bash
cd ~/app.profit-life.co
php artisan down
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --force     # añade permisos nuevos sin pisar los cambios hechos en el panel
php artisan optimize
php artisan up
```

Y vuelva a subir `public/build` si cambiaron estilos o scripts.

## 9. Comprobaciones finales

- `https://app.profit-life.co/up` responde 200.
- `https://app.profit-life.co/.env` responde 403/404 (nunca el contenido).
- Invitar a un usuario de prueba y verificar que el correo llega en menos de 2 minutos.
- Copias de seguridad: *Backup* de cPanel incluye la base de datos; programe una copia diaria.
