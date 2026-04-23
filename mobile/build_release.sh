#!/usr/bin/env sh
# Production release: API base defaults to https://vpride.ca/backend in AppConfig (release mode).
# Pass extra defines (Maps, Google) as needed, e.g.:
#   ./build_release.sh
#   ./build_release.sh --dart-define=MAPS_API_KEY=... --dart-define=GOOGLE_SERVER_CLIENT_ID=...
set -e
cd "$(dirname "$0")"
exec flutter build apk --release "$@"
