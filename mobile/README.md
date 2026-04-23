# VP Ride (Flutter)

Rider and driver app for the `vpride` monorepo. The PHP API in production is at **`https://vpride.ca/backend`** (same path as the hosted backend directory; all JSON routes are under `/api/v1/...` on that origin).

## API base URL (production sync)

- **Release builds** (`flutter build apk|ipa|appbundle` with default Flutter release mode) use **`https://vpride.ca/backend`** automatically when you do **not** pass `API_BASE_URL`, so the next store build matches `vpride.ca` and the `/backend` directory layout.
- **Debug / profile** use an empty base until you set `--dart-define=API_BASE_URL=...` (see below), so you can work offline with region fallbacks.
- **Override any time (staging, `www`, etc.):**  
  `--dart-define=API_BASE_URL=https://vpride.ca/backend`  
  (No trailing slash; a trailing slash is stripped if present.)

Config lives in `lib/core/config/app_config.dart` ([`defaultProductionApiBaseUrl`](lib/core/config/app_config.dart)).

## Local development

```bash
cd mobile
flutter pub get
flutter run --dart-define=API_BASE_URL=http://localhost:8080
```

Use the same host you use for `php -S` in `backend/public` (or your tunnel). For a device on the LAN, replace `localhost` with your machine’s IP.

## Production on device (explicit define)

```bash
flutter run --dart-define=API_BASE_URL=https://vpride.ca/backend
```

## Release build helpers (optional)

- `build_release.sh` / `build_release.ps1` — run `flutter build apk --release`; you can append extra `--dart-define` for Maps or Google (see `app_config.dart`).

## Other defines

| Define | Purpose |
|--------|--------|
| `API_BASE_URL` | API origin, e.g. `https://vpride.ca/backend` (optional in **release**; required for **debug** to hit the network) |
| `MAPS_API_KEY` | Google Maps / geocoding |
| `GOOGLE_SERVER_CLIENT_ID` | Web client ID for Google Sign-In ID token |

## Resources

- [Flutter documentation](https://docs.flutter.dev/)
