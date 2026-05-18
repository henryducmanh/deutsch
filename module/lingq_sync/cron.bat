@echo off
REM LingQ Sync daily — Windows Task Scheduler orchestrator (Phase D, 4 step).
REM Đã đăng ký với:
REM   schtasks /create /tn "LingQ Sync Daily" /tr "C:\twv_share\app\deutsch\module\lingq_sync\cron.bat" /sc daily /st 10:00
REM
REM 4 step tuần tự, abort nếu step trước fail:
REM   1. sync.php          → pull snapshot mới (lấy status user vừa review)
REM   2. update_local.php  → regenerate target từ vocab_master
REM   3. push.php --apply --auto-confirm → POST/PATCH/DELETE diff
REM      (cron threshold 20% — abort hẳn nếu vocab_master accidentally empty)
REM   4. sync.php          → post-push refresh để snapshot khớp server

setlocal
cd /d C:\twv_share\app\deutsch

set DATESTAMP=%date:~-4%-%date:~3,2%-%date:~0,2%
set LOG=module\lingq_sync\logs\cron_%DATESTAMP%.log
set PHP=C:\php\php74\php.exe

echo. >> %LOG%
echo [cron.bat] %DATE% %TIME% — orchestrator start >> %LOG%

echo [1/4] sync.php (pull snapshot) >> %LOG%
%PHP% module\lingq_sync\sync.php >> %LOG% 2>&1
if errorlevel 1 (
    echo [cron.bat] ABORT — sync.php step 1 failed >> %LOG%
    exit /b 1
)

echo [2/4] update_local.php (regenerate target) >> %LOG%
%PHP% module\lingq_sync\update_local.php >> %LOG% 2>&1
if errorlevel 1 (
    echo [cron.bat] ABORT — update_local.php step 2 failed >> %LOG%
    exit /b 1
)

echo [3/4] push.php --apply --auto-confirm >> %LOG%
%PHP% module\lingq_sync\push.php --apply --auto-confirm >> %LOG% 2>&1
if errorlevel 1 (
    echo [cron.bat] ABORT — push.php step 3 failed (có thể do threshold guard) >> %LOG%
    exit /b 1
)

echo [4/4] sync.php (post-push refresh) >> %LOG%
%PHP% module\lingq_sync\sync.php >> %LOG% 2>&1
if errorlevel 1 (
    echo [cron.bat] WARN — sync.php step 4 failed nhưng push step 3 đã xong >> %LOG%
    exit /b 1
)

echo [cron.bat] %DATE% %TIME% — orchestrator OK >> %LOG%
endlocal
exit /b 0
