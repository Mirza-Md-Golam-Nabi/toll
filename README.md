# Toll Management System

Toll collection software for **Syed Mash-ud Rumi Bridge** (quotation QT-001, Phase 1).
Built with Laravel 13, Filament 5 and MySQL 8.

## Features (Phase 1)

- **Vehicle categories & rates**: rate history with effective dates; past tickets keep their amounts.
- **Collection screen**: one key press (F1–F11) issues and prints a ticket. Number plates are optional, with suggestions from earlier passes.
- **Shifts**: opening cash at the start, then cash declaration and reconciliation at close.
- **Receipts**: 58mm thermal printing over ESC/POS, with a browser-print fallback. Reprints are marked DUPLICATE.
- **Offline first**: each booth runs on its own local database and syncs with the central server every minute.
- **Audit trail**: logs every login, shift, ticket and override. Voids and category corrections need a manager PIN.
- **Roles**: Super Admin, Plaza Manager and Booth Operator.
- **Reports**: daily, shift-wise, category-wise and operator-wise collection, with CSV export and print. The dashboard shows charts.
- **Language**: Bangla / English interface.
- **Phase 2 ready**: tickets already carry `plate_source`, `plate_image` and `anpr_confidence` for ANPR.

## Requirements

- PHP 8.3 with the `mysql`, `mbstring`, `intl`, `gd` and `zip` extensions
- Composer 2
- MySQL 8
- A web server (Nginx/Apache) for a live server; `php artisan serve` is enough locally

No `npm` build is needed: the whole UI is a Filament panel and its assets are published with `php artisan filament:assets`.

## ✅ Project Setup Instructions

The same code runs in two modes, set with `TOLL_MODE` in `.env`:

| Mode | Where | Purpose |
|---|---|---|
| `booth` | Windows PC at the toll booth | Operators issue tickets; works without internet |
| `central` | Online server (VPS / cPanel) | Collects data from every booth; managers see reports from anywhere |

A central server is optional. Without one, a booth runs stand-alone.

### 1. Local machine (development)

1. Clone the repository

```sh
git clone <repository-url> toll-management
cd toll-management
```

2. Install dependencies

```sh
composer install
```

3. Create the **.env** file and the application key

```sh
cp .env.example .env
php artisan key:generate
```

4. Create a MySQL database named:

```sh
toll_management
```

Then set `DB_USERNAME` and `DB_PASSWORD` in `.env` if they are not `root` with an empty password.

5. Run migrations and seeders

```sh
php artisan migrate --seed
```

This creates the booth, the three shifts, sample vehicle categories with rates and the demo users.
Optionally, add 30 days of demo traffic for the reports and dashboard:

```sh
php artisan db:seed --class=DemoDataSeeder
```

6. Publish the panel assets

```sh
php artisan filament:assets
```

7. Run the application

```sh
php artisan serve --port=8010
```

Open http://127.0.0.1:8010/admin

8. (Optional) Run the tests. They use a separate MySQL database named `toll_management_test`, so create it first:

```sh
php artisan test --compact
```

### 2. Live server (central server on a Linux VPS)

1. Install PHP 8.3 (with the extensions above), Composer, MySQL 8 and Nginx. Point the web server's document root to the project's `public` folder.

2. Clone the project and go to the folder

```sh
cd /var/www
git clone <repository-url> toll-management
cd toll-management
```

3. Create a MySQL database and a user for it.

4. Create the **.env** file from the central example

```sh
cp deploy/central/.env.central.example .env
```

Edit `.env` and set:

- `APP_URL`: the site address, e.g. `https://toll.example.com`
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `TOLL_SYNC_TOKEN`: a long random value. Generate one with:

```sh
php -r "echo bin2hex(random_bytes(32));"
```

- `MAIL_*`: an SMTP account. Email verification codes are sent by email, so the default `log` mailer will not deliver them.

5. Run the deploy script (use the same command for every later update)

```sh
bash deploy/central/deploy.sh
```

It installs dependencies, generates the key, migrates, seeds, publishes assets, caches config and, on the first run, asks you to create the first **Super Admin**.

6. Add the scheduler to cron (`crontab -e`)

```sh
* * * * * cd /var/www/toll-management && php artisan schedule:run >> /dev/null 2>&1
```

7. Enable HTTPS (e.g. with `certbot`). Booths send the sync token, so the site must use TLS.

8. In the panel, create one booth per toll booth under **Configuration → Booths**. Each booth's code must match the `TOLL_BOOTH_CODE` of that booth PC.

### 3. Live server on cPanel

1. Clone into the folder that will serve the site:

```sh
git clone <repository-url> .
```

If the domain's document root cannot point to the `public` folder, set it from **cPanel → Domains** to `<project-folder>/public`.

2. Check that Composer is installed:

```sh
composer -v
```

If you see "**Composer Not Found**", follow [Composer install in cPanel](https://github.com/Mirza-Md-Golam-Nabi/tips/blob/master/laravel/composer/README.md#composer-install-in-cpanel-%EF%B8%8F).

3. Use PHP 8.3 on the command line:

```sh
alias php="/opt/cpanel/ea-php83/root/usr/bin/php"
```

4. Create the database and its user from **cPanel → MySQL® Databases**, then create `.env`:

```sh
cp deploy/central/.env.central.example .env
```

Fill in `APP_URL`, the `DB_*` values, `TOLL_SYNC_TOKEN` and the `MAIL_*` values as in the VPS steps above.

5. Install and set up:

```sh
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --seed --force
php artisan filament:assets
php artisan storage:link
php artisan optimize
php artisan toll:create-admin
```

6. Add a cron job from **cPanel → Cron Jobs** (every minute):

```sh
cd /home/<cpanel-user>/<project-folder> && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

7. Enable SSL from **cPanel → SSL/TLS Status** (AutoSSL).

### 4. Booth PC (Windows)

1. Install [Laragon](https://laragon.org) (PHP 8.3 + MySQL 8) and copy the project to `C:\laragon\www\toll-management`.

2. Copy `deploy\booth\.env.booth.example` to `.env` in the project folder and set:

- `TOLL_BOOTH_CODE`: this booth's code, e.g. `B01`
- `TOLL_CENTRAL_URL` and `TOLL_SYNC_TOKEN`: only if a central server exists; otherwise leave them empty
- `TOLL_PRINTER_DRIVER`, `TOLL_PRINTER_CONNECTOR`, `TOLL_PRINTER_TARGET`: thermal printer settings

3. From a Laragon terminal, run:

```bat
cd C:\laragon\www\toll-management
deploy\booth\install.bat
```

On a booth connected to central, users come from the server, so press Ctrl+C at the Super Admin prompt.

4. Run `deploy\booth\register-scheduler.bat` **as Administrator** (automatic sync every minute).

5. Put a shortcut to `deploy\booth\start-collection.bat` on the desktop. It opens the collection screen full screen.

Printer setup, opcache and other details: [docs/deployment.md](docs/deployment.md).

### After changing `.env` or updating the code

```sh
composer install --no-dev -o
php artisan migrate --force
php artisan filament:assets
php artisan optimize
```

On a VPS, `bash deploy/central/deploy.sh` does all of this.

## 🔑 Default Login Credentials

Users log in with their **mobile number**. On a local machine (`APP_ENV=local`), the seeder creates these demo accounts with the password `password`:

| Role | Mobile number | Approval PIN |
|---|---|---|
| Super Admin | 01700000001 | 1234 |
| Plaza Manager | 01700000002 | 4321 |
| Booth Operator | 01700000003 | — |

> ⚠️ Demo accounts are never created on a production server or booth. There, create the first Super Admin with `php artisan toll:create-admin`. The same command resets a forgotten password.

## Useful commands

| Task | Command |
|---|---|
| Sync a booth with central manually | `php artisan toll:sync` |
| Create a Super Admin / reset a password | `php artisan toll:create-admin` |
| Rebuild caches after `.env` changes | `php artisan optimize` |
| Back up the database | `mysqldump -uroot toll_management > backup.sql` |

## Deployment & training

- [docs/deployment.md](docs/deployment.md): booth PC and central server setup, printer, sync, maintenance (Bangla)
- [docs/operator-guide.md](docs/operator-guide.md): operator and manager training guide (Bangla)
- `deploy/booth/*.bat`, `deploy/central/deploy.sh`: installation scripts

## Code map

| Path | Purpose |
|---|---|
| `app/Services` | All business logic: tickets, shifts, rates, reports, sync, receipts, audit |
| `app/Filament/Support`, `app/Filament/Actions` | Shared Filament columns, fields and actions (ticket overrides, shift open/close) |
| `app/Filament/Pages/TollCollection.php` | Operator collection screen |
| `app/Services/Sync/SyncRegistry.php` | What syncs, in which direction and in which order |
| `app/Services/TransactionAggregates.php` | Single definition of the vehicles/revenue aggregates used by every report |
| `config/toll.php` | Mode, booth code, sync and printer settings |
