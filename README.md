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

## Local development

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed            # booth, shifts, sample categories, demo users
php artisan db:seed --class=DemoDataSeeder   # optional: 30 days of demo traffic
php artisan filament:assets
php artisan serve --port=8010
```

Open http://127.0.0.1:8010/admin. Demo accounts (local only, password `password`):

| Role | Mobile number | Approval PIN |
|---|---|---|
| Super Admin | 01700000001 | 1234 |
| Plaza Manager | 01700000002 | 4321 |
| Booth Operator | 01700000003 | — |

Tests run against the MySQL database `toll_management_test`:

```bash
php artisan test --compact
```

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
