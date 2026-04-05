# Hệ trạng thái và mức độ nhớ

Repo này dùng hai lớp để phản ánh trí nhớ:

## 1. status
Trạng thái học hiện tại.

- `new` : mới gặp, chưa học kỹ
- `learning` : đang học
- `learned` : đã học, tương đối nhớ
- `review` : cần ôn lại
- `weak` : hay quên, cần ưu tiên
- `mastered` : rất chắc

## 2. memory_level
Mức độ nhớ từ `0` đến `5`.

- `0` : chưa biết
- `1` : mới thấy
- `2` : hiểu sơ
- `3` : nhớ nhưng chưa chắc
- `4` : khá chắc
- `5` : rất chắc

## Gợi ý kết hợp
- `new` + `0` hoặc `1`
- `learning` + `1` đến `3`
- `learned` + `3` hoặc `4`
- `review` + bất kỳ mức nào khi đến kỳ ôn
- `weak` + `1` đến `3`
- `mastered` + `5`

## Gợi ý lịch ôn đơn giản
Khi một mục vừa được đánh dấu học xong:
- lần đầu: `next_review = +2 ngày`
- lần hai: `+7 ngày`
- lần ba: `+21 ngày`
- lần bốn: `+60 ngày`

Nếu quên:
- giảm `memory_level`
- đổi `status = weak`
- đặt `next_review = ngày mai`

## Ví dụ cập nhật
- Hôm nay học từ mới:
  - `status: new -> learning`
  - `memory_level: 0 -> 2`
- Sau khi nhớ tốt:
  - `status: learning -> learned`
  - `memory_level: 2 -> 4`
- Sau một thời gian quên:
  - `status: learned -> weak`
  - `memory_level: 4 -> 2`

## Mục tiêu
Hệ này không nhằm tuyệt đối chính xác như Anki, mà để tạo một kho tri thức có thể dùng lâu dài, dễ đọc và dễ cập nhật.
