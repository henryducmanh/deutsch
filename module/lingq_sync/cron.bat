@echo off
REM LingQ Sync daily — Windows Task Scheduler wrapper.
REM schtasks /create /tn "LingQ Sync Daily" /tr "C:\twv_share\app\deutsch\module\lingq_sync\cron.bat" /sc daily /st 06:00
cd /d C:\twv_share\app\deutsch
C:\php\php74\php.exe module\lingq_sync\sync.php >> module\lingq_sync\logs\cron_%date:~-4%-%date:~3,2%-%date:~0,2%.log 2>&1
