#!/usr/bin/env bash
# macOS / Linux: chạy web tại http://127.0.0.1:8000 (cần PHP >= 8.2 và MySQL đã import database_center_app.sql)
cd "$(dirname "$0")/backend"
PHP=php
[ -x /Applications/XAMPP/xamppfiles/bin/php ] && PHP=/Applications/XAMPP/xamppfiles/bin/php
if [ ! -f .env ]; then cp .env.example .env && "$PHP" artisan key:generate --force; fi
"$PHP" artisan config:clear >/dev/null 2>&1
echo "Mở http://127.0.0.1:8000  (admin@gmail.com / 123456) — Ctrl+C để tắt"
(sleep 2; open http://127.0.0.1:8000 2>/dev/null || xdg-open http://127.0.0.1:8000 2>/dev/null) &
"$PHP" artisan serve --host=127.0.0.1 --port=8000
