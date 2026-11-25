#!/bin/bash
cd "$(dirname "$0")" || exit 1
clear
bash test-ui.sh
echo; read -n 1 -s -r -p "Nhấn phím bất kỳ để đóng..."
