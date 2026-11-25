#!/bin/bash
# Kiểm thử API (PHPUnit) – chạy trên SQLite trong bộ nhớ.
#   bash mac/test-api.sh                  chạy 158 test trên mã hiện tại của dự án
#   bash mac/test-api.sh TTHP_API_28      chạy 1 hoặc nhiều test
#   bash mac/test-api.sh demo             trình diễn 5 lỗi, bấm phím để xem tiếp
#   bash mac/test-api.sh baocao           chạy trên bản 91bd384 (trước khi sửa lỗi, đúng số liệu báo cáo)
HERE="$(cd "$(dirname "$0")/.." && pwd)"
PHP=/Applications/XAMPP/xamppfiles/bin/php
[ -x "$PHP" ] || PHP=php
BACKEND="$HERE/../backend"

if [ "$1" = "baocao" ]; then
  shift
  OLD="$HERE/.tmp/baocao"
  if [ ! -f "$OLD/.ready" ]; then
    echo "Đang chuẩn bị bản 91bd384 (chỉ lần đầu)..."
    rm -rf "$OLD"; mkdir -p "$OLD"
    git -C "$HERE/.." archive 91bd384 backend | tar -x -C "$OLD" || exit 1
    cp -R "$BACKEND/vendor" "$OLD/backend/vendor" || exit 1
    mkdir -p "$OLD/backend/storage/framework/views" "$OLD/backend/storage/logs" "$OLD/backend/bootstrap/cache"
    echo "APP_KEY=base64:$(openssl rand -base64 32)" > "$OLD/backend/.env"
    touch "$OLD/.ready"
  fi
  BACKEND="$OLD/backend"
fi

cd "$BACKEND" || exit 1
export KTPM_BACKEND="$PWD"
run() { "$PHP" vendor/bin/phpunit -c "$HERE/phpunit.local.xml" --testdox --colors=always "$@"; }
one() {
  case "$1" in
    QLHS_API_*) f=StudentManagementTest ;;
    QLND_API_*) f=UserManagementTest ;;
    TTHP_API_*) f=TuitionPaymentTest ;;
    *) echo "Mã test không hợp lệ: $1"; return 1 ;;
  esac
  run --filter "${f}::test_api_${1##*_}_"
}

if [ $# -eq 0 ]; then
  run
elif [ "$1" = "demo" ]; then
  for id in QLHS_API_14 QLHS_API_19 QLND_API_07 TTHP_API_28 QLHS_API_49; do
    clear; one "$id" | grep -vE '(vendor|Users).*\.php'
    echo; read -n 1 -s -r -p "Nhấn phím bất kỳ để xem tiếp..."; echo
  done
else
  for id in "$@"; do one "$id"; done
fi
