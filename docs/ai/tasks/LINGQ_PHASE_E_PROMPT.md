# LingQ Phase E — sync.php zombie row cleanup

> **Task ID:** `lingq_phase_e_v1`
> Task nhỏ, format `Goal / Files / Main Work / Test ≤ 8 dòng` (theo user prefs).

## Goal

Khi `sync.php` thấy `Removed: N > 0` (lingq_id trong CSV nhưng không có trong API response), **xoá luôn N row đó khỏi `data/lingq_cards.csv`** thay vì giữ lại. Tránh push.php lần sau call 404 vô ích cho entries đã xoá trên server.

## Files

- `module/lingq_sync/sync.php` (edit logic merge CSV vs API response)
- `docs/LINGQ_SYNC.md` (update troubleshoot row "Log nhiều `Removed: N`")

## Main work

1. Trong `sync.php` merge logic: thay vì keep CSV-only rows, **drop** chúng.
2. Vẫn log `Removed: N` cùng danh sách 5 pk đầu tiên (debug).
3. Vẫn backup trước khi write CSV — atomic `.tmp → rename` giữ nguyên.
4. Thêm flag `--keep-zombies` để revert behavior cũ (optional, edge case debug).
5. Update `docs/LINGQ_SYNC.md`: troubleshoot row "Log nhiều `Removed: N`" → "KHÔNG xoá CSV row" sửa thành "CSV row đã tự xoá; check backup `lingq_cards_backup_*` nếu cần recover".

## Test

1. **Baseline:** snapshot hiện có 2729 rows, server có 2495 (234 zombie). Chạy `sync.php` → CSV còn **2495 rows**, log `Removed: 234`.
2. **Idempotent:** chạy lại `sync.php` ngay sau → `Removed: 0`, CSV vẫn 2495 rows.
3. **Push lần sau:** dry-run `push.php` → plan KHÔNG còn 404 entries (số DELETE = chỉ entries thật).
4. **Flag --keep-zombies:** chạy `sync.php --keep-zombies` → CSV giữ 2495 rows từ API + zombie rows nếu có → tổng > 2495 nếu zombie chưa cleanup.

## Cấm

- Đừng touch `data/lingq_target.csv` (Phase D output).
- Đừng đụng push.php / update_local.php — chỉ sync.php + docs.
- Lock `.ai-locks/lingq_phase_e.lock` trước Edit, xoá sau.
- KHÔNG tự git commit/push.

## Handoff dòng paste

```
Đọc docs/ai/tasks/LINGQ_PHASE_E_PROMPT.md và làm. Edit sync.php + docs/LINGQ_SYNC.md. Báo "edit xong, chờ review Cursor".
```
