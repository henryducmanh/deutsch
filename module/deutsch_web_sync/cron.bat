@echo off
REM Deutsch Web Sync — pull events Web → local, Windows Task Scheduler (mỗi 30').
REM Đăng ký:
REM   schtasks /create /tn "Deutsch Web Sync" /tr "C:\twv_share\app\deutsch\module\deutsch_web_sync\cron.bat" /sc minute /mo 30
REM
REM Chạy pull_events.php live. Log riêng cron_<date>.log (cộng thêm log <date>.log của script).

setlocal
cd /d C:\twv_share\app\deutsch

set DATESTAMP=%date:~-4%-%date:~3,2%-%date:~0,2%
set LOG=module\deutsch_web_sync\logs\cron_%DATESTAMP%.log
set PHP=C:\php\php74\php.exe

echo. >> %LOG%
echo [cron.bat] %DATE% %TIME% — pull start >> %LOG%

%PHP% module\deutsch_web_sync\pull_events.php >> %LOG% 2>&1
if errorlevel 1 (
    echo [cron.bat] %DATE% %TIME% — pull FAILED (exit 1) >> %LOG%
    endlocal
    exit /b 1
)

echo [cron.bat] %DATE% %TIME% — pull OK >> %LOG%

REM ── Vocab pull: kéo web-add (curated=0) → staging vocab_new_web.csv (1 lần/ngày là đủ) ──
REM Chỉ chạy nhánh vocab khi biến RUN_VOCAB=1 (đăng ký task riêng daily, tránh chạy mỗi 30').
if "%RUN_VOCAB%"=="1" (
    echo [cron.bat] %DATE% %TIME% — pull_vocab start >> %LOG%
    %PHP% module\deutsch_web_sync\pull_vocab.php >> %LOG% 2>&1
    if errorlevel 1 (
        echo [cron.bat] %DATE% %TIME% — pull_vocab FAILED (exit 1) >> %LOG%
    ) else (
        echo [cron.bat] %DATE% %TIME% — pull_vocab OK >> %LOG%
    )
)

REM push_vocab.php KHÔNG để trong cron tự động (đẩy 1926 row) — chạy tay khi vocab_master đổi:
REM   %PHP% module\deutsch_web_sync\push_vocab.php --dry-run   (review)
REM   %PHP% module\deutsch_web_sync\push_vocab.php             (live)
endlocal
exit /b 0
