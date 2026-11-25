@echo off
chcp 65001 >nul
setlocal
set "HERE=%~dp0..\"
set "PHP=php"
for %%D in (C D E) do if exist "%%D:\xampp\php\php.exe" set "PHP=%%D:\xampp\php\php.exe"

rem chay-test-giao-dien.bat      : 66 test
rem chay-test-giao-dien.bat xss  : demo loi XSS, hien trinh duyet
rem chay-test-giao-dien.bat loi  : chi hien 9 test Fail ghi trong Excel
set "MODE=%~1"
if not exist "%HERE%..\backend\vendor\autoload.php" goto nosetup
if not exist "%HERE%..\frontend\node_modules\vite\bin\vite.js" goto nosetup
if not exist "%HERE%e2e\node_modules\playwright" goto nosetup
netstat -ano | findstr /r /c:":8000 .*LISTENING" >nul && goto busy
netstat -ano | findstr /r /c:":5173 .*LISTENING" >nul && goto busy

set "APP_CONFIG_CACHE=bootstrap/cache/ktpm-none.php"
set "DB_CONNECTION=sqlite"
set "DB_DATABASE=%HERE%e2e\ui.sqlite"
set "DB_URL="
set "CACHE_STORE=file"
set "QUEUE_CONNECTION=sync"
set "MAIL_MAILER=array"
set "FRONTEND_URL=http://localhost:5173"
set "VITE_API_URL=http://127.0.0.1:8000/api"
if not exist "%DB_DATABASE%" type nul > "%DB_DATABASE%"
"%PHP%" "%HERE%support\seed-ui.php" || goto fail

cd /d "%HERE%..\backend"
start "Backend KTPM" /min "%PHP%" artisan serve --port=8000 --no-reload
cd /d "%HERE%..\frontend"
start "Frontend KTPM" /min node node_modules\vite\bin\vite.js --host localhost --port 5173 --strictPort
echo Dang cho server...
"%PHP%" "%HERE%support\wait-ports.php" || goto stopfail
cd /d "%HERE%e2e"
if /i "%MODE%"=="loi" goto loi
if /i not "%MODE%"=="xss" goto all
set "HEADED=1"
set "ONLY=QLND_UI_06,QLHS_UI_01,QLHS_UI_23,QLHS_UI_24"
:all
node ui_tests.js
goto stop
:loi
call :group "QLND_UI_06,QLHS_UI_01,QLHS_UI_05,QLHS_UI_07,QLHS_UI_11,QLHS_UI_12,QLHS_UI_13,QLHS_UI_16,QLHS_UI_19"
call :group "QLND_UI_06,QLHS_UI_01,QLHS_UI_23,QLHS_UI_24"
call :group "QLND_UI_06,QLND_UI_08,QLND_UI_10,QLND_UI_11,QLND_UI_12,QLND_UI_13,QLND_UI_14,QLND_UI_15,QLND_UI_16,QLND_UI_17"
call :group "QLND_UI_06,TTHP_UI_01,TTHP_UI_03,TTHP_UI_04,TTHP_UI_08"
:stop
call :killport 8000
call :killport 5173
goto end

:group
rem Xoa bo dem gioi han request de khong phai cho 65 giay giua cac nhom
pushd "%HERE%..\backend"
"%PHP%" artisan cache:clear >nul
popd
set "ONLY=%~1"
node ui_tests.js | "%PHP%" "%HERE%support\loc.php" ui-loi
exit /b 0

:killport
for /f "tokens=5" %%P in ('netstat -ano ^| findstr /r /c:":%1 .*LISTENING"') do taskkill /F /T /PID %%P >nul 2>nul
exit /b 0

:busy
echo Cong 8000 hoac 5173 dang ban. Hay tat server dang chay (vi du cua so run.bat) roi thu lai.
goto end
:nosetup
echo Chua cai thu vien. Hay chay file 00-Cai-dat-lan-dau.bat truoc.
goto end
:stopfail
call :killport 8000
call :killport 5173
:fail
echo Co loi, xem thong bao phia tren.
:end
if not defined NOPAUSE pause
