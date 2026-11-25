@echo off
chcp 65001 >nul
setlocal
set "NOPAUSE=1"
set "LOC=gon"
set "TXT=%~dp0..\support\hop-xam"
call :one 1 QLHS_API_01
call :one 2 QLHS_API_49
call :one 3 TTHP_API_40
cls
type "%TXT%\4.txt"
echo.
pause
exit /b 0
:one
cls
type "%TXT%\%1.txt"
echo.
echo ---------------- KET QUA CHAY ----------------
call "%~dp0chay-test-api.bat" baocao %2
echo.
echo Nhan phim bat ky de xem tiep...
pause >nul
exit /b 0
