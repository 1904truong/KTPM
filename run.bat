@echo off
chcp 65001 >nul
cd /d "%~dp0backend"
title Sunny Education - Server

echo =============================================
echo   SUNNY EDUCATION
echo =============================================
echo  Truoc khi chay: mo XAMPP Control Panel, Start MySQL
echo  va import file database_center_app.sql qua phpMyAdmin.
echo.

rem --- Tim PHP: uu tien PHP cua XAMPP, sau do PHP trong PATH ---
set "PHP="
for %%D in (C:\xampp D:\xampp E:\xampp) do if not defined PHP if exist "%%D\php\php.exe" set "PHP=%%D\php\php.exe"
if not defined PHP for /f "delims=" %%P in ('where php 2^>nul') do if not defined PHP set "PHP=%%P"
if not defined PHP (
  echo [LOI] Khong tim thay PHP. Hay cai XAMPP vao C:\xampp
  pause & exit /b 1
)
echo PHP: %PHP%
"%PHP%" -r "exit(version_compare(PHP_VERSION, '8.2.0', '>=') ? 0 : 1);" || (
  echo [LOI] Can PHP 8.2 tro len. Hay cai XAMPP ban 8.2.
  pause & exit /b 1
)

if not exist .env (
  copy .env.example .env >nul
  "%PHP%" artisan key:generate --force
)
"%PHP%" artisan config:clear >nul 2>nul

echo.
echo  Dang mo http://127.0.0.1:8000
echo  Dang nhap: admin@gmail.com / 123456
echo  Dong cua so nay de tat chuong trinh.
echo.
start "" http://127.0.0.1:8000
"%PHP%" artisan serve --host=127.0.0.1 --port=8000
pause
