# Production: release APK; API base defaults to https://vpride.ca/backend (see lib/core/config/app_config.dart).
# Pass-through example:
#   .\build_release.ps1 --dart-define=MAPS_API_KEY=... --dart-define=GOOGLE_SERVER_CLIENT_ID=...
Set-Location $PSScriptRoot
& flutter build apk --release @args
