# vpride

Monorepo for **VP Ride**.

| Path | What it is |
|------|------------|
| **`index.html`**, `favicon.png`, `assets/` | Static “coming soon” / marketing shell for the main domain. Replace or extend with your public landing when you launch. |
| **`mobile/`** | Flutter app (iOS, Android, web, desktop). Run Flutter commands from this folder. |
| **`backend/`** | PHP admin API, MySQL, public config JSON for the app. See `backend/README.md`. |

## Flutter app

```bash
cd mobile
flutter pub get
flutter run --dart-define=API_BASE_URL=http://localhost:8080
```

## Public site (vpride.ca)

Upload the repository **root** files used by the static page: `index.html`, `favicon.png`, `assets/`, and optionally root `.htaccess` (Apache `DirectoryIndex`).

**Optional:** if you need the same repository layout on the host and want `/` to redirect into `backend/`, use `backend/docs/root-redirect-to-backend.example.php` (rename to `index.php` only if you are **not** serving `index.html` as the home page).
