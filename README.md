# vpride

Monorepo for **VP Ride**.

| Path | What it is |
|------|------------|
| **`index.html`**, `assets/` (e.g. `app-icon.png` for the logo + favicon) | Static “coming soon” / marketing shell for the main domain. Replace or extend with your public landing when you launch. |
| **`mobile/`** | Flutter app (iOS, Android, web, desktop). Run Flutter commands from this folder. |
| **`backend/`** | PHP admin API, MySQL, public config JSON. See `backend/README.md`. |

**Production on `vpride.ca`:** the **website root** is the static page. The **backend** (API + **admin** UI) is served at **`https://vpride.ca/backend/`** (no trailing slash in `API_BASE_URL` / `PUBLIC_BASE_URL` config — use `https://vpride.ca/backend`).

## Flutter app

```bash
cd mobile
flutter pub get
flutter run --dart-define=API_BASE_URL=http://localhost:8080
```

For a device hitting your production API base (same as admin URL path):

```bash
flutter run --dart-define=API_BASE_URL=https://vpride.ca/backend
```

## Public site (vpride.ca)

Upload the repository **root** static files: `index.html`, `robots.txt` (disallows `/backend/` and `/mobile/` for crawlers), `assets/` (include `app-icon.png`), and optionally root `.htaccess` (Apache `DirectoryIndex`).

Upload the **`backend/`** folder so the admin and API are available at **`/backend/`** on the host. See `backend/README.md` for `APP_BASE_PATH` and `PUBLIC_BASE_URL`.

**Optional:** to send visitors from a bare `index.html` or folder index into `backend/`, you can use `backend/docs/root-redirect-to-backend.example.php` (only if you are not relying on a different `index` for the marketing page — usually the static site and `/backend` coexist without redirect).
