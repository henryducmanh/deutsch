@echo off
setlocal
set ROOT=C:\twv_share\app\deutsch
set PHP=C:\php\php74\php.exe
set PYTHON=python
set LOG=%ROOT%\module\scan_extract\logs\lesson_gen_%date:~-4,4%%date:~-10,2%%date:~-7,2%.log

echo [%date% %time%] === HOREN LESSON GEN START === >> "%LOG%" 2>&1

:: Step 1: Push 4.x chua co len LingQ (idempotent — bai da push se skip tu dong)
%PHP% %ROOT%\module\lingq_sync\lessons_push.php --batch "input\html\deutsch-vorbereitung\horen\4.*" --apply --sleep 2.0 >> "%LOG%" 2>&1

:: Step 2: Sync lingq_lessons.csv (cap nhat audio_url moi)
%PHP% %ROOT%\module\lingq_sync\lessons_sync.php >> "%LOG%" 2>&1

:: Step 3: Generate lesson JSON
%PYTHON% %ROOT%\module\scan_extract\horen_to_lesson_json.py --apply >> "%LOG%" 2>&1

echo [%date% %time%] === HOREN LESSON GEN END === >> "%LOG%" 2>&1
endlocal
