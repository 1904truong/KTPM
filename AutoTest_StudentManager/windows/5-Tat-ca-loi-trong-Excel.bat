@echo off
chcp 65001 >nul
setlocal
set "NOPAUSE=1"
echo ==============================================================
echo  PHAN 1: LOI API (ban truoc khi sua) - Excel ghi 40 test Fail
echo ==============================================================
set "LOC=api-loi"
call "%~dp0chay-test-api.bat" baocao
set "LOC="
echo.
echo ==============================================================
echo  PHAN 2: LOI GIAO DIEN - Excel ghi 9 test Fail (khoang 2 phut)
echo ==============================================================
call "%~dp0chay-test-giao-dien.bat" loi
echo.
pause
