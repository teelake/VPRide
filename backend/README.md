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

   Set `DB_*` and, for production, `PUBLIC_BASE_URL` (e.g. `https://vpride.ca` for a root deploy).

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

Point the app at this host:

```bash
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

Use **`backend/index.php`** + **`backend/.htaccess`** so the URL does **not** need `/public` in the path.

**Backend at the domain root** (e.g. document root points at `backend/` or you map the site to it):

1. Upload the full **`backend/`** folder. You should have **`backend/index.php`** next to **`backend/public/`**.
2. In **`backend/.htaccess`**, set **`RewriteBase /`** (or the path your host documents).
3. In **`backend/.env`**: **`APP_BASE_PATH=`** (empty), **`PUBLIC_BASE_URL=https://vpride.ca`**

**Backend in a subfolder** (e.g. `https://vpride.ca/backend/`):

1. Set **`RewriteBase /backend/`** in **`backend/.htaccess`** to match the URL.
2. **`APP_BASE_PATH=backend`**, **`PUBLIC_BASE_URL=https://vpride.ca/backend`**

3. Ensure **Apache `mod_rewrite`** is on (most cPanel hosts allow `.htaccess`).

Open (adjust path if you use a subfolder):

- `https://vpride.ca/` → redirects to admin login (root deploy)
- `https://vpride.ca/api/v1/config/regions` → JSON
- `https://vpride.ca/admin/login` → login

**Optional:** whole Flutter **web** build under a folder on the same host: add the repo’s root **`index.php`** (redirects to `backend/`) if you split app vs admin paths. If a route 404s, set the host’s **DirectoryIndex** to include `index.php` or remove a conflicting `index.html`.

```bash
flutter run --dart-define=API_BASE_URL=https://vpride.ca
```

Release builds and CI should use the same `API_BASE_URL` (or the subfolder URL, e.g. `https://vpride.ca/backend`, if that is how you deploy).

## Production notes

- Use HTTPS, strong passwords, and restrict admin by IP or VPN if needed.
- Set secure session cookies (`session.cookie_secure`, `HttpOnly`, `SameSite`).
- Add rate limiting and logging on `/admin/*`.

## Testing from Nigeria (or anywhere)

- Point **defaults** in the active JSON to a Nigerian city (e.g. `countryCode` `NG`, `cityId` `los`) so map center and copy match your QA scenario, or keep Canada for production and use an **Android emulator** with GPS set to Toronto/Vancouver.
- For **closed beta in Canada**, use Play internal testing / TestFlight and ask testers to install the build; they validate real addresses and networks.
