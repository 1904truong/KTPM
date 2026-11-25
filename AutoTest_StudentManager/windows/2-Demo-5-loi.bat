@echo off
chcp 65001 >nul
setlocal
set "WIN=%~dp0"
set "NOPAUSE=1"
set "LOC=gon"
for %%T in (QLHS_API_14 QLHS_API_19 QLND_API_07 TTHP_API_28 QLHS_API_49) do call :one %%T
exit /b 0
:one
cls
call "%WIN%chay-test-api.bat" %1
echo.
echo Nhan phim bat ky de xem tiep...
pause >nul
exit /b 0
