# PROFITLIFE

Sistema web multi-sede para un centro de acondicionamiento físico y fisioterapia.
Monolito Laravel 13 · PHP 8.3 · MySQL 8 · Blade + Livewire 4 + Tailwind 4 + Alpine.

El esquema aprobado está en [`docs/fase-1/erd.md`](docs/fase-1/erd.md).
Despliegue en producción (cPanel, `app.profit-life.co`): [`docs/despliegue/cpanel.md`](docs/despliegue/cpanel.md).

## Puesta en marcha local

Requisitos: PHP 8.3+ (extensiones `intl`, `pdo_mysql`, `mbstring`), Composer 2, Node 20+, MySQL 8.

```bash
git clone <repo> profitlife && cd profitlife
composer install
cp .env.example .env
php artisan key:generate

# Base de datos (ajuste usuario y clave en .env si usa otros)
mysql -uroot -p -e "CREATE DATABASE profitlife CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE DATABASE profitlife_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'profitlife'@'localhost' IDENTIFIED BY 'secret';
  GRANT ALL ON profitlife.* TO 'profitlife'@'localhost';
  GRANT ALL ON profitlife_testing.* TO 'profitlife'@'localhost';"

php artisan migrate --seed      # roles, permisos, ajustes y datos de ejemplo (APP_ENV=local)
npm install && npm run build

php artisan serve               # http://localhost:8000
php artisan queue:work          # en otra terminal: envía las invitaciones y correos
```

Correo en local: el `.env.example` apunta a SMTP `127.0.0.1:1025` (p. ej. [Mailpit](https://mailpit.axllent.org),
interfaz en http://localhost:8025). Para no instalar nada, use `MAIL_MAILER=log` y los correos quedan en `storage/logs/laravel.log`.

### Usuarios de ejemplo (contraseña `Password123`)

| Rol | Correo | Sedes |
|---|---|---|
| Super Admin | superadmin@profitlife.test | Cabecera, Provenza |
| Administrador | admin@profitlife.test | Cabecera, Provenza |
| Gerente de sede | gerente.cabecera@profitlife.test | Cabecera |
| Gerente de sede | gerente.provenza@profitlife.test | Provenza |
| Recepción | recepcion@profitlife.test | Cabecera |
| Fisioterapeuta | fisio@profitlife.test | Cabecera, Provenza |
| Entrenador | entrenador@profitlife.test | Provenza |
| Cliente | cliente@profitlife.test | — (sin acceso al panel) |

Super Admin, Administrador, Gerente de sede y Fisioterapeuta deben configurar la verificación en dos pasos (TOTP)
en su primer ingreso: el panel los lleva a *Mi perfil → Seguridad*.

## Pruebas

```bash
vendor/bin/pest          # usa MySQL (base profitlife_testing, ver phpunit.xml)
vendor/bin/pint          # estilo de código
```

## Estructura

```
app/Domain/{Identity,Locations,Staff,Audit,Notifications,Settings,Shared}/
    Models · Actions · Enums · Policies · DTOs · Services · Listeners · Notifications
app/Http/{Admin,Portal,Kiosk,Api}/{Controllers,Requests,Middleware}
app/Livewire/{Admin,Portal,Kiosk}
app/Support/            Money, LocationScope (global scope por sede), CurrentLocation (selector de sede)
resources/views/{admin,portal,kiosk,components}
routes/{web,admin,portal,kiosk,api}.php
tests/{Feature/{Admin,Auth,Portal,Kiosk,Api},Unit/Domain}
```

Reglas clave:

- **Alcance por sede**: los modelos con sede usan el trait `HasLocationScope`; quien no tiene
  `locations.view-all` solo ve sus sedes de `location_staff`. Las Policies vuelven a validar cada registro.
  El selector de sede de la barra superior es solo un filtro de interfaz.
- **Permisos**: catálogo en `App\Domain\Identity\Enums\Permission` (`recurso.accion`); matriz por defecto en
  `RolesAndPermissionsSeeder` (idempotente: no pisa cambios hechos desde el panel).
- **Fechas**: la base guarda UTC; la interfaz muestra la zona de `settings` (`general.timezone`).
- **Marca, moneda y zona horaria**: en `settings` (grupo `general`), con respaldo en `config/profitlife.php`.
