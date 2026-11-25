#!/bin/bash
# Hiện toàn bộ test Fail ghi trong file Excel: 40 test API + 9 test giao diện.
HERE="$(cd "$(dirname "$0")" && pwd)"
echo "=============================================================="
echo " PHẦN 1: LỖI API (bản trước khi sửa) - Excel ghi 40 test Fail"
echo "=============================================================="
bash "$HERE/test-api.sh" baocao | perl -ne '
  s/\e\[[0-9;]*m//g;
  if (/^ (✔|✘) /) { $f = /✘/; if ($f) { $n++; s/^ ✘ //; print "\n\e[31m$n. ✘ $_\e[0m" } }
  elsif ($f && /├ (?=\S)/ && /Expected|Failed|empty|matches/) { s/^\s*├ /     /; print }
  elsif (/^Tests: /) { print "\n$_" }'
echo
echo "=============================================================="
echo " PHẦN 2: LỖI GIAO DIỆN - Excel ghi 9 test Fail (khoảng 2 phút)"
echo "=============================================================="
bash "$HERE/test-ui.sh" loi
