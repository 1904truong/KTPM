#!/bin/bash
# Kiểm thử giao diện (Playwright) – dùng CSDL riêng e2e/ui.sqlite.
#   bash mac/test-ui.sh         chạy 66 test (khoảng 10 phút)
#   bash mac/test-ui.sh xss     trình diễn lỗi XSS, có hiện trình duyệt
HERE="$(cd "$(dirname "$0")/.." && pwd)"
ROOT="$(cd "$HERE/.." && pwd)"
PHP=/Applications/XAMPP/xamppfiles/bin/php
[ -x "$PHP" ] || PHP=php
command -v node >/dev/null || . "$HOME/.nvm/nvm.sh"
for p in 8000 5173; do
  if lsof -iTCP:$p -sTCP:LISTEN >/dev/null 2>&1; then echo "Cổng $p đang bận. Hãy tắt server đang chạy rồi thử lại."; exit 1; fi
done
cd "$HERE/e2e" || exit 1
if [ ! -d node_modules/playwright ]; then
  echo "Cài Playwright (chỉ lần đầu)..."
  npm install --no-audit --no-fund && npx playwright install chromium || exit 1
fi

export APP_CONFIG_CACHE=bootstrap/cache/ktpm-none.php
export DB_CONNECTION=sqlite DB_DATABASE="$HERE/e2e/ui.sqlite" DB_URL=
export CACHE_STORE=file QUEUE_CONNECTION=sync MAIL_MAILER=array
export FRONTEND_URL=http://localhost:5173 VITE_API_URL=http://127.0.0.1:8000/api
touch "$DB_DATABASE"
"$PHP" "$HERE/support/seed-ui.php" || exit 1

trap 'pkill -P $BE 2>/dev/null; kill $BE $FE 2>/dev/null' EXIT
cd "$ROOT/backend" && { "$PHP" artisan serve --port=8000 --no-reload >"$HERE/e2e/backend.log" 2>&1 & BE=$!; }
cd "$ROOT/frontend" && { node node_modules/vite/bin/vite.js --host localhost --port 5173 --strictPort >"$HERE/e2e/frontend.log" 2>&1 & FE=$!; }
echo "Đang chờ server..."
curl -s -o /dev/null --retry 30 --retry-connrefused --retry-delay 1 http://127.0.0.1:8000 || exit 1
curl -s -o /dev/null --retry 30 --retry-connrefused --retry-delay 1 http://localhost:5173 || exit 1
cd "$HERE/e2e" || exit 1
if [ "$1" = "loi" ]; then
  # Chỉ hiện 9 test giao diện được ghi Fail trong Excel; các test khác trong nhóm là bước chuẩn bị.
  IDS='QLHS_UI_(05|11|12|19|24)|QLND_UI_(16|17)|TTHP_UI_(03|08)'
  for group in \
    QLND_UI_06,QLHS_UI_01,QLHS_UI_05,QLHS_UI_07,QLHS_UI_11,QLHS_UI_12,QLHS_UI_13,QLHS_UI_16,QLHS_UI_19 \
    QLND_UI_06,QLHS_UI_01,QLHS_UI_23,QLHS_UI_24 \
    QLND_UI_06,QLND_UI_08,QLND_UI_10,QLND_UI_11,QLND_UI_12,QLND_UI_13,QLND_UI_14,QLND_UI_15,QLND_UI_16,QLND_UI_17 \
    QLND_UI_06,TTHP_UI_01,TTHP_UI_03,TTHP_UI_04,TTHP_UI_08
  do
    # Xóa bộ đếm giới hạn request để không phải chờ 65 giây giữa các nhóm
    (cd "$ROOT/backend" && "$PHP" artisan cache:clear >/dev/null)
    ONLY=$group node ui_tests.js | grep --line-buffered -E "^(✔|✘) ($IDS) "
  done
elif [ "$1" = "xss" ]; then
  HEADED=1 ONLY=QLND_UI_06,QLHS_UI_01,QLHS_UI_23,QLHS_UI_24 node ui_tests.js
else
  node ui_tests.js
fi
