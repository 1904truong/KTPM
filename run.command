#!/bin/bash
# macOS: nhấp đúp để khởi động chương trình (gọi run.sh).
cd "$(dirname "$0")" || exit 1
bash run.sh
echo; read -n 1 -s -r -p "Nhấn phím bất kỳ để đóng..."
