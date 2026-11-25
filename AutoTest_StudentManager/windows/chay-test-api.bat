@echo off
chcp 65001 >nul
setlocal
set "HERE=%~dp0..\"
set "PHP=php"
for %%D in (C D E) do if exist "%%D:\xampp\php\php.exe" set "PHP=%%D:\xampp\php\php.exe"

rem chay-test-api.bat                      : 158 test tren ma hien tai
rem chay-test-api.bat TTHP_API_28          : 1 test
rem chay-test-api.bat baocao               : 158 test tren ban 91bd384 (dung so lieu bao cao)
rem chay-test-api.bat baocao QLHS_API_49   : 1 test tren ban 91bd384
rem Bien LOC=gon hoac api-loi de rut gon ket qua; NOPAUSE=1 de khong dung cuoi.
set "BACKEND=%HERE%..\backend"
if /i not "%~1"=="baocao" goto ready
shift /1
"%PHP%" "%HERE%support\prepare-baocao.php" || goto fail
set "BACKEND=%HERE%.tmp\baocao\backend"
:ready
cd /d "%BACKEND%" || goto fail
if not exist vendor\autoload.php goto novendor
set "KTPM_BACKEND=%CD%"
set "ID=%~1"
set "FILTER="
if "%ID%"=="" goto run
set "FILE="
if /i "%ID:~0,4%"=="QLHS" set "FILE=StudentManagementTest"
if /i "%ID:~0,4%"=="QLND" set "FILE=UserManagementTest"
if /i "%ID:~0,4%"=="TTHP" set "FILE=TuitionPaymentTest"
if not defined FILE goto badid
set FILTER=--filter "%FILE%::test_api_%ID:~-2%_"
:run
if defined LOC goto filtered
"%PHP%" vendor\bin\phpunit -c "%HERE%phpunit.local.xml" --testdox --colors=always %FILTER%
goto end
:filtered
"%PHP%" vendor\bin\phpunit -c "%HERE%phpunit.local.xml" --testdox --colors=always %FILTER% | "%PHP%" "%HERE%support\loc.php" %LOC%
goto end
:badid
echo Ma test khong hop le: %ID%
goto end
:novendor
echo Chua cai thu vien. Hay chay file 00-Cai-dat-lan-dau.bat truoc.
goto end
:fail
echo Co loi, xem thong bao phia tren.
:end
if not defined NOPAUSE pause
