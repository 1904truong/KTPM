@echo off
chcp 65001 >nul
setlocal
set "HERE=%~dp0..\"
set "PHP=php"
for %%D in (C D E) do if exist "%%D:\xampp\php\php.exe" set "PHP=%%D:\xampp\php\php.exe"

rem Chay 1 lan tren may Windows moi. Can Internet, XAMPP (PHP 8.2+), Composer, Node.js, Git.
for %%T in (composer node npm git) do where %%T >nul 2>nul || echo [THIEU] Chua cai %%T
"%PHP%" -r "exit(version_compare(PHP_VERSION,'8.2.0','>=') && extension_loaded('pdo_sqlite') ? 0 : 1);" || goto badphp

echo === 1/3 Thu vien backend
cd /d "%HERE%..\backend" || goto fail
call composer install || goto fail
if exist .env goto frontend
copy .env.example .env >nul
"%PHP%" artisan key:generate --force || goto fail

:frontend
echo === 2/3 Thu vien frontend
cd /d "%HERE%..\frontend" || goto fail
rem Neu thu muc chep tu Mac sang thi node_modules khong dung duoc, nen cai lai
call npm ci || goto fail

echo === 3/3 Playwright va trinh duyet Chromium
cd /d "%HERE%e2e" || goto fail
call npm install --no-audit --no-fund || goto fail
call npx playwright install chromium || goto fail

echo.
echo Cai dat xong. Chay tiep cac file danh so 0 den 6.
goto end
:badphp
echo Can PHP 8.2 tro len co bat extension pdo_sqlite (XAMPP 8.2).
goto end
:fail
echo Co loi, xem thong bao phia tren.
:end
pause
