# Wedding RSVP

Foundation for a Wedding RSVP SaaS, built with the official Laravel Vue starter kit. Product features have not been added.

## Stack

- Laravel 12.70.0, PHP 8.2.33, Composer 2.10.2
- Vue 3.5.44, Inertia.js 2.0.3 (Vue adapter), Inertia Laravel 2.0.28
- TypeScript 5.7.3, Tailwind CSS 3.4.19, Vite 6.4.4
- Node.js 24.15.0, npm 11.12.1
- MySQL (configure local credentials before database operations)
- Starter kit: `laravel/vue-starter-kit` v1.0.2

The `composer.lock` and `package-lock.json` files record the resolved dependency versions. The `.env` file is local and ignored by Git.

## Prerequisites

PHP 8.2+ with `bcmath`, `ctype`, `fileinfo`, `json`, `mbstring`, `openssl`, `PDO`, `pdo_mysql`, `tokenizer`, `xml`, and `zip`; Composer 2; Node.js and npm; and a running local MySQL server.

## Local setup

1. Install PHP and Node dependencies:

   ```powershell
   composer install
   npm.cmd ci
   ```

2. Copy `.env.example` to `.env` if needed, then set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` to your local MySQL details. Never commit `.env`.
3. In MySQL, create the dedicated database if it does not already exist:

   ```sql
   CREATE DATABASE wedding_rsvp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

   Use an account permitted to create this database. The project does not create databases or assume a blank root password.
4. Generate an application key if this is a fresh environment, then run default migrations:

   ```powershell
   php artisan key:generate
   php artisan migrate
   ```

## Run locally (Windows PowerShell)

Open two terminals in the project directory:

```powershell
php artisan serve
```

```powershell
npm.cmd run dev
```

Open `http://127.0.0.1:8000`. `npm.cmd` avoids PowerShell's script-execution policy blocking the `npm.ps1` shim.

## Build, check, and test

```powershell
npm.cmd run build
npm.cmd run typecheck
php artisan test
vendor\bin\pint --test
```

For an auto-format pass, run `vendor\bin\pint` and `npm.cmd run format`.

## Structure

- `app/`, `routes/`, `database/`, `tests/`: Laravel application, routes, migrations, and tests
- `resources/js/pages/`: Inertia Vue pages
- `resources/js/components/`, `layouts/`, `composables/`, `types/`: shared frontend code
- `resources/css/`, `vite.config.ts`: Tailwind and Vite setup

## Common setup issues

- **`npm` is blocked in PowerShell:** use `npm.cmd` / `npx.cmd`; this does not change machine policy.
- **MySQL access denied or connection refused:** ensure the MySQL service is running and `.env` contains the correct host, port, username, and password.
- **Unknown database `wedding_rsvp`:** create it using the SQL above, then run `php artisan migrate`.
- **Missing PHP extension:** enable it in the active PHP configuration (`C:\php\php.ini` in the inspected environment) and restart terminal commands.
- **Tests report `could not find driver` for SQLite:** enable `pdo_sqlite` and `sqlite3` in the active PHP configuration, then rerun `php artisan test`.
- **Configuration changes appear ignored:** run `php artisan config:clear`.
