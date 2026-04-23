# VP Ride PHP backend (admin + region API)

## What you get

- **MySQL** schema: `admins` (RBAC roles), `region_configs` (JSON payload + active flag).
- **Public API:** `GET /api/v1/config/regions` — same JSON shape as the Flutter app (`lib/core/region/region_config_dto.dart`).
- **Admin web UI:** log in, edit JSON, create drafts, **Activate** any version to switch what mobile apps receive (no app reinstall).

Only **`system_admin`** may create/edit configs or activate. Other roles (`dispatcher`, `support`) can be added in SQL for future features; they see the dashboard read-only.

## Setup

1. **PHP 8.1+** and **MySQL 8+** (JSON column).

2. Create database and tables:

   ```bash
   mysql -u root -p < sql/schema.sql
   ```

   Edit `DB_DATABASE` in `.env` if you use a different database name (match `schema.sql` or create DB manually).

3. **Configure environment:**

   ```bash
   cd backend
   copy .env.example .env
   ```

   Set `DB_*` and, for production, `APP_BASE_PATH=backend` and `PUBLIC_BASE_URL=https://vpride.ca/backend` (admin and API at **`/backend/`** on the site; see [Production](#production--shared-hosting) below). For local `php -S` from `public/`, use empty `APP_BASE_PATH` and `http://localhost:8080`.

4. **Seed** the first system admin + default “Modern Canada” active config:

   - **CLI:** `php scripts/seed.php`
   - **phpMyAdmin / no SSH:** import **`sql/seed_data.sql`** after the schema (run once).

   Default login (change in production):

   - **Email:** `admin@vpride.local`
   - **Password:** `Admin@123`

5. **Run the server** (from repo root or `backend`):

   ```bash
   cd backend/public
   php -S 0.0.0.0:8080 index.php
   ```

   - API: `http://localhost:8080/api/v1/config/regions`
   - Admin: `http://localhost:8080/admin/login`

## Flutter app

The Flutter project is in the repo’s **`mobile/`** folder. Point the app at this host:

```bash
cd mobile
flutter run --dart-define=API_BASE_URL=http://localhost:8080
```

- **Android emulator:** use `http://10.0.2.2:8080` instead of `localhost`.
- **Physical device on same LAN:** use your PC’s LAN IP, e.g. `http://192.168.1.50:8080`.

## Switching region worldwide (or any JSON)

1. Sign in as **system_admin**.
2. **New draft config** — starts from a template with multiple countries (Canada + Nigeria); edit JSON (add countries/cities, change `defaults.countryCode` / `cityId`, `branding.serviceAreaLabel`).
3. **Save**, then on the dashboard click **Activate** on that row.

All apps that call the API on next refresh (or after pull-to-refresh / `RegionConfigRepository.refresh()`) get the new active payload.

## Production / shared hosting (`https://vpride.ca`)

**Standard layout:** the **marketing or static** site is at the domain **root** (`/`), and the **PHP backend** (admin + public API) lives at **`/backend/`**:

- **Admin (login, dashboard, settings):** `https://vpride.ca/backend/admin/…`
- **Public API (mobile app, config):** `https://vpride.ca/backend/api/…` (e.g. `…/api/v1/config/regions`)

Use **`backend/index.php`** + **`backend/.htaccess`** so the URL does **not** need `/public` in the path.

1. Upload the full **`backend/`** folder. You should have **`backend/index.php`** next to **`backend/public/`**.
2. In **`backend/.htaccess`**, set **`RewriteBase /backend/`** so requests under `/backend/` are routed to `public/index.php` (this repo is already set that way).
3. In **`backend/.env`:** **`APP_BASE_PATH=backend`**, **`PUBLIC_BASE_URL=https://vpride.ca/backend`** (no trailing slash).
4. Ensure **Apache `mod_rewrite`** is on (most cPanel hosts allow `.htaccess`).

**Check:**

- `https://vpride.ca/backend/` — should reach the app (e.g. redirect to admin login)
- `https://vpride.ca/backend/api/v1/config/regions` — JSON (when the region API is up)
- `https://vpride.ca/backend/admin/login` — admin sign-in

**If you do not** use a subfolder and instead run the PHP app at the **host root** (no `/backend`), set **`APP_BASE_PATH=`** (empty) and **`PUBLIC_BASE_URL=https://vpride.ca`**, and adjust **`.htaccess`** `RewriteBase` to `/` or what your host requires.

**Optional:** a tiny **`index.php`** at the **site** root (next to the static `index.html`) that redirects to `backend/` is exactly **`backend/docs/root-redirect-to-backend.example.php`**. Only add it if you want `/` to jump to the admin entry and your host is not using `index.html` as the home page by default. Many setups keep the static homepage at `/` and do not need that redirect.

```bash
cd mobile
flutter run --dart-define=API_BASE_URL=https://vpride.ca/backend
```

Release builds and CI should use that same `API_BASE_URL` base, with commands run from `mobile/`.

## Production notes

- Use HTTPS, strong passwords, and restrict admin by IP or VPN if needed.
- Set secure session cookies (`session.cookie_secure`, `HttpOnly`, `SameSite`).
- Add rate limiting and logging on `/admin/*`.

## Testing from Nigeria (or anywhere)

- Point **defaults** in the active JSON to a Nigerian city (e.g. `countryCode` `NG`, `cityId` `los`) so map center and copy match your QA scenario, or keep Canada for production and use an **Android emulator** with GPS set to Toronto/Vancouver.
- For **closed beta in Canada**, use Play internal testing / TestFlight and ask testers to install the build; they validate real addresses and networks.
