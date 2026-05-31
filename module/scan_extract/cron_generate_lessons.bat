@echo off
setlocal
set ROOT=C:\twv_share\app\deutsch
set PHP=C:\php\php74\php.exe
set PYTHON=python
set LOG=%ROOT%\module\scan_extract\logs\lesson_gen_%date:~-4,4%%date:~-10,2%%date:~-7,2%.log

echo [%date% %time%] === HOREN LESSON GEN START === >> "%LOG%" 2>&1

:: Step 1: Push tat ca series chua co len LingQ (idempotent — bai da push se skip)
::   - Bat dau tu series nho (1.x nhanh, 2.x+3.x text-only), 4.x cuoi
::   - --sleep 2.0s chong rate-limit LingQ
%PHP% %ROOT%\module\lingq_sync\lessons_push.php --batch "input\html\deutsch-vorbereitung\horen\1.*" --apply --sleep 2.0 >> "%LOG%" 2>&1
%PHP% %ROOT%\module\lingq_sync\lessons_push.php --batch "input\html\deutsch-vorbereitung\horen\2.*" --apply --sleep 2.0 >> "%LOG%" 2>&1
%PHP% %ROOT%\module\lingq_sync\lessons_push.php --batch "input\html\deutsch-vorbereitung\horen\3.*" --apply --sleep 2.0 >> "%LOG%" 2>&1
%PHP% %ROOT%\module\lingq_sync\lessons_push.php --batch "input\html\deutsch-vorbereitung\horen\4.*" --apply --sleep 2.0 >> "%LOG%" 2>&1

:: Step 2: Sync lingq_lessons.csv (cap nhat lesson_id + audio_url moi)
%PHP% %ROOT%\module\lingq_sync\lessons_sync.php >> "%LOG%" 2>&1

:: Step 3: Patch lingq_id + audio.url vao JSON (nhe, khong regenerate toan bo)
%PYTHON% %ROOT%\module\scan_extract\patch_lingq_audio.py --apply >> "%LOG%" 2>&1

:: Step 4: Generate JSON cho bai moi scrape (chi bai chua co JSON, khong --force)
%PYTHON% %ROOT%\module\scan_extract\horen_to_lesson_json.py --apply --series 1 >> "%LOG%" 2>&1
%PYTHON% %ROOT%\module\scan_extract\horen_to_lesson_json.py --apply --series 2 >> "%LOG%" 2>&1
%PYTHON% %ROOT%\module\scan_extract\horen_to_lesson_json.py --apply --series 3 >> "%LOG%" 2>&1
%PYTHON% %ROOT%\module\scan_extract\horen_to_lesson_json.py --apply --series 4 >> "%LOG%" 2>&1

echo [%date% %time%] === HOREN LESSON GEN END === >> "%LOG%" 2>&1
endlocal
