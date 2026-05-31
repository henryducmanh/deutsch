# Deutsch Web — Lesson Vocab Pins (persist "Dang on" list)

> **Task ID:** `deutsch_web_lesson_pins`
> **Lock:** Tao `.ai-locks/lesson_pins.lock` dau task, xoa cuoi.
> **Paste 1 dong cho Claude Code:**
> `Doc docs/ai/tasks/DEUTSCH_WEB_LESSON_PINS_PROMPT.md va lam theo. Tao lock .ai-locks/lesson_pins.lock. Bao "edit xong, cho review Cursor".`
>
> **QUAN TRONG - File lon:**
> drill.js hien tai 1025 dong. Chi dung **Edit** (khong Write).
> Sau moi Edit lon: xac nhan `wc -l` + `tail -5` + `node --check`.
> Neu file bi cut: lay lai tu `git show HEAD:module/deutsch_web/public/assets/drill.js > /tmp/drill.js` roi apply lai.

---

## 1. End-user

Henry (student) mo bai 4.21 → mo Vokabeln panel → tab "Dang on" → thay tu "Bildung" tu
section "Trong kho" bang nut "+" → tu xuat hien trong "Dang on". Mo bai 4.24 → lam tuong
tu voi tu "Bildung". Refresh hoac mo lai bat ky bai nao → tu "Bildung" van con trong "Dang
on" cua tung bai (persist sau reload). Xoa tu khoi "Dang on" → bien mat khoi bai do nhung
van con trong "Trong kho" (global) + con trong bai khac neu da pin.

---

## 2. Man cuoi cung (definition of done)

### Files tao moi
```
module/deutsch_web/migrations/005_lesson_vocab_pins.sql
```

### Files sua
```
module/deutsch_web/api/vocab.php       (them 3 ham api_vocab_pins_*)
module/deutsch_web/public/index.php   (them 3 route /api/vocab/pins)
module/deutsch_web/public/assets/drill.js  (4 cho chinh xac - xem muc 3)
```

### Behavior sau khi xong
- Click "+" tren tu o "Trong kho" → POST /api/vocab/pins → persist → refresh van con
- Click "x" tren tu pinned trong "Dang on" → DELETE /api/vocab/pins → bien mat
- Click "x" tren tu queued (curated=0, tu "Tu la") → giu nguyen: session-only remove
- Mo bai bat ky sau reload → pinned words load cung voi queued words → hien trong "Dang on"

---

## 3. Data that & schema

### Migration 005_lesson_vocab_pins.sql (idempotent MySQL 5.7)

```sql
CREATE TABLE IF NOT EXISTS lesson_vocab_pins (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  user_id   INT         NOT NULL,
  lesson_id VARCHAR(16) NOT NULL,
  vocab_id  INT         NOT NULL,   -- vocab.id (PK, khong phai vocab_id CSV)
  pinned_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pin (user_id, lesson_id, vocab_id),
  INDEX idx_user_lesson (user_id, lesson_id),
  CONSTRAINT fk_pin_vocab FOREIGN KEY (vocab_id) REFERENCES vocab(id) ON DELETE CASCADE,
  CONSTRAINT fk_pin_user  FOREIGN KEY (user_id)  REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### API examples

GET /api/vocab/pins?lesson_id=4.21
Response: {"pins":[{"db_id":111,"w":"Bildung","art":"Substantiv","artikel":"die",
           "bedeutung":"giao duc, su dao tao","lv":1},...]}

POST /api/vocab/pins   body: {"lesson_id":"4.21","vocab_db_id":111}
Response: {"ok":true,"pin_id":7}

DELETE /api/vocab/pins   body: {"lesson_id":"4.21","vocab_db_id":111}
Response: {"ok":true}

### drill.js - su thay doi du lieu

`globalKnownData` hien tai: `{wort_key: {w, art, bedeutung}}`
Sau sua:               `{wort_key: {w, art, bedeutung, db_id}}` (db_id = vocab.id)

`vocabData` item pinned: `{w, art, m, lv, db_id, pinned: true}`
(khac voi queued: `{w, art, m, lv, vocab_id, queued: true}`)

---

## 4. Acceptance tests (theo thu tu)

**Test 1 - Migration idempotent**
```
php scripts/migrate.php     # lan 1
php scripts/migrate.php     # lan 2, khong loi
SHOW TABLES LIKE 'lesson_vocab_pins'  → 1 row
```

**Test 2 - GET pins rong**
```
GET /api/vocab/pins?lesson_id=4.21 (session henry)
→ {"pins":[]}
```

**Test 3 - "+" button goi dung API**
```
Mo /lesson/4.21 → panel "Dang on" → section "Trong kho" → click "+" o tu "Bildung"
→ Network: POST /api/vocab/pins body={lesson_id:"4.21",vocab_db_id:111} → 200 {"ok":true}
→ Tu xuat hien trong "Dang on" voi lv badge
→ Nut "+" thanh "checkmark" disabled
```

**Test 4 - Persist sau refresh**
```
Refresh /lesson/4.21 → panel "Dang on" → "Bildung" van co (load tu DB)
```

**Test 5 - Pin sang bai khac doc lap**
```
Mo /lesson/4.24 → "+" tu "Bildung" → pin
Refresh /lesson/4.24 → "Bildung" co
Refresh /lesson/4.21 → "Bildung" van con (doc lap)
```

**Test 6 - "x" xoa pin**
```
O /lesson/4.21, click "x" tren "Bildung" (pinned)
→ Network: DELETE /api/vocab/pins body={lesson_id:"4.21",vocab_db_id:111} → 200 {"ok":true}
→ "Bildung" bien mat khoi "Dang on" cua 4.21
→ Mo /lesson/4.24 → "Bildung" van con (khong bi anh huong)
→ Section "Trong kho" cua 4.21: "Bildung" hien lai voi nut "+" (vi da xoa pin)
```

**Test 7 - Queued word (curated=0) van hoat dong nhu cu**
```
Tab "Tu la" → gõ tu moi → "+ Queue" → tu xuất hien trong "Dang on"
Refresh → tu van con (curated=0, load qua /api/vocab/queued)
Click "x" → bien mat khoi "Dang on" SESSION ONLY (khong goi DELETE /api/vocab/pins)
```

---

## 5. Huong dan cu the cho tung file

### A. api/vocab.php - them 3 ham cuoi file

```php
// ── GET /api/vocab/pins?lesson_id=X ──────────────────────────────────────────
function api_vocab_pins_get() {
    auth_require();
    $uid       = auth_active_student_id();
    $lesson_id = trim($_GET['lesson_id'] ?? '');
    if (!$lesson_id) { api_json(400, ['error' => 'missing lesson_id']); }

    $st = db()->prepare('
        SELECT v.id AS db_id, v.wort AS w, v.wortart AS art, v.artikel,
               v.bedeutung, v.niveau, v.level AS lv
        FROM lesson_vocab_pins p
        JOIN vocab v ON v.id = p.vocab_id
        WHERE p.user_id = ? AND p.lesson_id = ?
        ORDER BY v.wort
    ');
    $st->execute([$uid, $lesson_id]);
    api_json(200, ['pins' => $st->fetchAll()]);
}

// ── POST /api/vocab/pins  body: {lesson_id, vocab_db_id} ─────────────────────
function api_vocab_pins_post() {
    auth_require();
    $uid  = auth_active_student_id();
    $body = api_body_json();
    $lesson_id   = trim($body['lesson_id']   ?? '');
    $vocab_db_id = (int)($body['vocab_db_id'] ?? 0);
    if (!$lesson_id || !$vocab_db_id) { api_json(400, ['error' => 'missing params']); }

    try {
        $st = db()->prepare('
            INSERT IGNORE INTO lesson_vocab_pins (user_id, lesson_id, vocab_id)
            VALUES (?, ?, ?)
        ');
        $st->execute([$uid, $lesson_id, $vocab_db_id]);
        $pin_id = db()->lastInsertId() ?: null;
        api_json(200, ['ok' => true, 'pin_id' => $pin_id]);
    } catch (\PDOException $e) {
        api_json(500, ['error' => 'db_error']);
    }
}

// ── DELETE /api/vocab/pins  body: {lesson_id, vocab_db_id} ───────────────────
function api_vocab_pins_delete() {
    auth_require();
    $uid  = auth_active_student_id();
    $body = api_body_json();
    $lesson_id   = trim($body['lesson_id']   ?? '');
    $vocab_db_id = (int)($body['vocab_db_id'] ?? 0);
    if (!$lesson_id || !$vocab_db_id) { api_json(400, ['error' => 'missing params']); }

    $st = db()->prepare('
        DELETE FROM lesson_vocab_pins WHERE user_id=? AND lesson_id=? AND vocab_id=?
    ');
    $st->execute([$uid, $lesson_id, $vocab_db_id]);
    api_json(200, ['ok' => true]);
}
```

Luu y: `api_body_json()` da co trong file (dung boi api_events_ack). `auth_require()` va
`auth_active_student_id()` da co trong auth.php.

### B. public/index.php - them vao route_api()

Them 3 route vao function `route_api()`, sat cac route /api/vocab hien co:

```php
// GET /api/vocab/pins
if ($path === '/api/vocab/pins' && $method === 'GET') {
    require_once $BASE . '/api/vocab.php';
    api_vocab_pins_get(); return;
}
// POST /api/vocab/pins
if ($path === '/api/vocab/pins' && $method === 'POST') {
    require_once $BASE . '/api/vocab.php';
    api_vocab_pins_post(); return;
}
// DELETE /api/vocab/pins
if ($path === '/api/vocab/pins' && $method === 'DELETE') {
    require_once $BASE . '/api/vocab.php';
    api_vocab_pins_delete(); return;
}
```

### C. drill.js - 4 chinh xac

**C1. loadGlobalKnownFromDB() - luu db_id vao globalKnownData**

Tim doan:
```javascript
          if (!globalKnownData[k]) {
            globalKnownData[k] = { w: row.w || k, art: row.art || '', bedeutung: row.bedeutung || '' };
          }
```
Sua thanh:
```javascript
          if (!globalKnownData[k]) {
            globalKnownData[k] = { w: row.w || k, art: row.art || '', bedeutung: row.bedeutung || '', db_id: row.id || null };
          }
```

**C2. loadVocabFromDB() - them p3 fetch pins, merge vao vocabData**

Tim doan:
```javascript
    Promise.all([p1, p2]).then(function (results) {
      var dbRows   = results[0];
      var queued   = results[1];
```
Sua thanh:
```javascript
    // p3: fetch pinned words cho bai nay
    var p3 = LESSON_ID ? fetch('/api/vocab/pins?lesson_id=' + encodeURIComponent(LESSON_ID), {
      credentials: 'same-origin', headers: { 'Accept': 'application/json' }
    }).then(function (r) { return r.ok ? r.json() : { pins: [] }; })
      .then(function (d) { return (d && d.pins) || []; })
      .catch(function () { return []; }) : Promise.resolve([]);

    Promise.all([p1, p2, p3]).then(function (results) {
      var dbRows   = results[0];
      var queued   = results[1];
      var pins     = results[2];
```

Ngay sau phan xu ly `queued.forEach(...)`, them:
```javascript
      // Append pinned words vao vocabData neu chua co (pinned: true de phan biet voi queued)
      pins.forEach(function (pin) {
        var k = (pin.w || '').toLowerCase();
        if (!k || knownKeys[k]) { return; }
        knownKeys[k] = true;
        wordStatus[k] = 'new';
        vocabData.push({
          w: pin.w, art: pin.art || '', m: pin.bedeutung || '—', lv: pin.lv || 1,
          db_id: pin.db_id, pinned: true
        });
      });
```

**C3. wireGlobalSection() - "+" button POST /api/vocab/pins**

Thay toan bo noi dung handler "+" (session-only) bang:
```javascript
    list.querySelectorAll('.vgi-add-btn').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var gk  = btn.dataset.word;
        var gd  = globalKnownData[gk];
        if (!gd) { return; }
        var alreadyIn = vocabData.some(function (v) { return v.w.toLowerCase() === gk; });
        if (alreadyIn) { btn.textContent = 'checkmark'; btn.disabled = true; return; }
        btn.disabled = true; btn.textContent = '...';
        if (gd.db_id && LESSON_ID) {
          // Persist qua API
          fetch('/api/vocab/pins', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ lesson_id: LESSON_ID, vocab_db_id: gd.db_id })
          }).then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
            .then(function () {
              vocabData.push({ w: gd.w, art: gd.art || '', m: gd.bedeutung || 'chua tra',
                               lv: 'new', db_id: gd.db_id, pinned: true });
              wordStatus[gk] = 'new';
              if (vocabOpen) { renderVocab(); }
              if (hlOn) { stripMarks(); marksInjected = false; injectMarks(); }
              btn.textContent = 'checkmark';
            }).catch(function () {
              btn.disabled = false; btn.textContent = '+';
              alert('Khong luu duoc. Kiem tra ket noi.');
            });
        } else {
          // Fallback session-only (khong co db_id)
          vocabData.push({ w: gd.w, art: gd.art || '', m: gd.bedeutung || 'chua tra',
                           lv: 'new', addedFromGlobal: true });
          wordStatus[gk] = 'new';
          if (vocabOpen) { renderVocab(); }
          if (hlOn) { stripMarks(); marksInjected = false; injectMarks(); }
          btn.textContent = 'checkmark';
        }
      });
    });
```

Luu y: thay 'checkmark' bang ky tu checkmark Unicode (U+2713): `'✓'`

**C4. vocab-remove-btn handler - DELETE /api/vocab/pins cho pinned word**

Tim doan hien tai xu ly click "x":
```javascript
        var wKey = btn.dataset.word;
        vocabData = vocabData.filter(function (v) { return v.w.toLowerCase() !== wKey; });
        delete wordStatus[wKey];
        renderVocab();
        if (hlOn) { stripMarks(); marksInjected = false; injectMarks(); }
```

Sua thanh:
```javascript
        var wKey = btn.dataset.word;
        var removed = vocabData.filter(function (v) { return v.w.toLowerCase() === wKey; });
        vocabData = vocabData.filter(function (v) { return v.w.toLowerCase() !== wKey; });
        delete wordStatus[wKey];
        renderVocab();
        if (hlOn) { stripMarks(); marksInjected = false; injectMarks(); }
        // Neu la pinned word → DELETE pin tu DB
        var pinnedItem = removed.find ? removed.find(function (v) { return v.pinned && v.db_id; }) : null;
        if (!pinnedItem) { removed.forEach(function (v) { if (v.pinned && v.db_id) { pinnedItem = v; } }); }
        if (pinnedItem && LESSON_ID) {
          fetch('/api/vocab/pins', {
            method: 'DELETE', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ lesson_id: LESSON_ID, vocab_db_id: pinnedItem.db_id })
          }).catch(function () { /* offline, bo qua */ });
        }
```

---

## 6. Performance / scale

- User co the pin 1 tu vao 100 bai → 1 row / bai trong lesson_vocab_pins. 100 rows / tu.
- Index `idx_user_lesson (user_id, lesson_id)` → query O(1) per bai mo.
- ON DELETE CASCADE tren vocab.id: xoa vocab → xoa tat ca pins cua tu do.
- Khong can batch: so luong pin / bai thuong < 50 tu.
- `INSERT IGNORE` de idempotent (double-click "+" an toan).

---

## 7. Format report

```
=== LESSON PINS DONE ===

Migration: 005_lesson_vocab_pins.sql (idempotent)
  → chay: php scripts/migrate.php (lan 1 + lan 2 khong loi)

Files sua:
  api/vocab.php        (them api_vocab_pins_get/post/delete)
  public/index.php     (them 3 route /api/vocab/pins)
  public/assets/drill.js (C1 db_id, C2 p3 pins, C3 + button, C4 x button)

drill.js: wc -l = [so dong] | node --check = OK | tail -5 = [5 dong cuoi]

Tests:
  1 (migration)    : PASS
  2 (GET rong)     : PASS
  3 (+ goi API)    : PASS
  4 (persist)      : PASS
  5 (multi-lesson) : PASS
  6 (x xoa pin)    : PASS
  7 (queued cu)    : PASS

Lock xoa: .ai-locks/lesson_pins.lock
```

---

## Cam duong

- KHONG sua schema bang vocab / events / tutor_notes
- KHONG dung Write tool cho drill.js (chi Edit)
- KHONG tu chay migrate tren server (bao cao user tu chay)
- KHONG them Composer package
