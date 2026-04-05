# Trích xuất từ HTML theo rule repo `deutsch`

Nguồn:
- HTML người dùng tải lên
- Rule và schema trong repo `henryducmanh/deutsch`

## 1. Mục đã có trong repo

### Grammar
- `weil + Nebensatz`

## 2. Mục mới nên thêm vào `vocab_master.csv`

```csv
id,term_type,lemma,normalized,article,pos,meaning_vi,meaning_de,example,status,memory_level,first_seen,last_seen,learned_at,next_review,source,tags,notes
VOC0005,word,zugänglich,zuganglich,,Adjektiv,có thể tiếp cận; có thể vào được,erreichbar oder betretbar,Die Haltestelle ist momentan nicht zugänglich.,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Verkehr|Hören,
VOC0006,word,umleiten,umleiten,,Verb,chuyển hướng; đổi tuyến,einen Verkehr oder Weg anders führen,Die U-Bahn-Linien werden wegen Bauarbeiten umgeleitet.,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Verkehr|Hören,
VOC0007,word,umsteigen,umsteigen,,Verb,đổi chuyến; chuyển tuyến,das Verkehrsmittel wechseln,In Lippen Straße müssen die Fahrgäste umsteigen.,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Verkehr|Hören,
VOC0008,collocation,fast windstill,fast windstill,,Adjektivphrase,gần như lặng gió,fast ohne Wind,Heute bleibt es sonnig und fast windstill.,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Wetter|Hören,
VOC0009,word,Wartungsarbeiten,wartungsarbeiten,die,Nomen,công việc bảo trì,technische Arbeiten zur Erhaltung oder Reparatur,Wegen der Wartungsarbeiten gibt es morgen kein Wasser.,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Wohnen|Lesen,
VOC0010,word,Wasserleitung,wasserleitung,die,Nomen,đường ống nước,Rohrleitung für Wasser,Die Wasserleitungen werden am Montag gewartet.,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Wohnen|Lesen,
VOC0011,word,erreichbar,erreichbar,,Adjektiv,có thể liên lạc được; có mặt để tiếp cận,kontaktierbar oder verfügbar,Zwischen 8 und 16 Uhr sollte jemand in der Wohnung erreichbar sein.,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Wohnen|Lesen,
VOC0012,word,hinterlegen,hinterlegen,,Verb,gửi lại; để lại; ký gửi,etwas bei jemandem zur Aufbewahrung lassen,Sie können den Wohnungsschlüssel bei einem Nachbarn hinterlegen.,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Wohnen|Lesen,
VOC0013,word,Kellerabteil,kellerabteil,das,Nomen,ngăn/khoang hầm riêng,abgetrennter Bereich im Keller,Persönliche Gegenstände gehören nur ins eigene Kellerabteil.,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Wohnen|Lesen,
VOC0014,word,Kellergang,kellergang,der,Nomen,hành lang tầng hầm,Flur im Keller,Auf dem Kellergang dürfen keine Sachen stehen.,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Wohnen|Lesen,
VOC0015,word,Fluchtweg,fluchtweg,der,Nomen,lối thoát hiểm,Weg zum Verlassen eines Gebäudes im Notfall,Die Fluchtwege müssen frei bleiben.,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Wohnen|Lesen,
VOC0016,word,zusammenfalten,zusammenfalten,,Verb,gấp gọn; gập lại,falten und kleiner machen,Leere Kartons soll man vorher zusammenfalten.,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Haushalt|Lesen,
VOC0017,word,Sperrmüllservice,sperrmullservice,der,Nomen,dịch vụ thu gom rác cồng kềnh,Dienst zur Abholung großer Abfälle,Für alte Möbel rufen Sie bitte den Sperrmüllservice an.,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Wohnen|Lesen,
VOC0018,word,Abholtermin,abholtermin,der,Nomen,lịch hẹn lấy/thu gom,vereinbarter Termin zur Abholung,Vereinbaren Sie einen Abholtermin mit der Gemeinde.,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Behörde|Lesen,
VOC0019,word,Stellenanzeige,stellenanzeige,die,Nomen,tin tuyển dụng; thông báo tuyển việc,Anzeige für eine freie Arbeitsstelle,Ich habe Ihre Stellenanzeige gelesen.,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Arbeit|Schreiben,
VOC0020,word,Verstärkung,verstarkung,die,Nomen,nhân sự bổ sung; người hỗ trợ thêm,zusätzliche Hilfe für ein Team,Sie suchen eine freundliche Verstärkung für Ihr Team.,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Arbeit|Schreiben,
VOC0021,collocation,sich um eine Stelle bewerben,sich um eine stelle bewerben,,Verbphrase,ứng tuyển vào một vị trí,sich offiziell für eine Arbeitsstelle melden,Ich möchte mich um diese Stelle bewerben.,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Arbeit|Schreiben,collocation học nguyên cụm
```

## 3. Mục mới nên thêm vào `grammar_master.csv`

```csv
id,topic,pattern,usage,level,example,compare_with,status,memory_level,first_seen,last_seen,learned_at,next_review,source,tags,notes
GR0004,Höflichkeitsform im Schreiben,Ihre + Nomen,Dùng đại từ sở hữu lịch sự trong thư trang trọng,B1,Mit großem Interesse habe ich Ihre Stellenanzeige gelesen.,ihre,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Schreiben|Brief,
GR0005,Konjunktiv II für höflichen Wunsch,möchte + Infinitiv,Dùng để diễn đạt mong muốn lịch sự trong đơn thư / giao tiếp,B1,Ich möchte mich um diese Stelle bewerben.,wollte / soll,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Schreiben|Modalverb,
GR0006,Konditionalsatz,wenn + Nebensatz,Dùng để nêu điều kiện,B1,Wenn es terminlich passt, stehe ich Ihnen ab dem 1. August zur Verfügung.,obwohl / damit,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Schreiben|Nebensatz,
GR0007,Verb mit Präposition,sich freuen auf + Akkusativ,Dùng khi mong chờ một việc trong tương lai,B1,Ich freue mich auf die Möglichkeit eines persönlichen Gesprächs.,sich freuen über,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Schreiben|Präposition,
GR0008,Modalverb im höflichen Satz,können + Infinitiv,Dùng để diễn đạt khả năng / cách liên hệ một cách lịch sự,B1,Sie können mich telefonisch erreichen.,dürfen / sollen,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Schreiben|Modalverb,
```

## 4. Mục mới nên thêm vào `phrase_master.csv`

```csv
id,phrase_type,pattern,meaning_vi,use_case,example,status,memory_level,first_seen,last_seen,learned_at,next_review,source,tags,notes
PH0004,polite_expression,Mit großem Interesse habe ich Ihre Stellenanzeige gelesen.,Tôi đã đọc tin tuyển dụng của quý công ty với sự quan tâm lớn.,Bewerbung / formeller Brief,Mit großem Interesse habe ich Ihre Stellenanzeige im Portal gelesen.,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Schreiben|Arbeit,
PH0005,exam_phrase,Ich möchte mich um diese Stelle bewerben.,Tôi muốn ứng tuyển vào vị trí này.,Bewerbung / Schreiben,Ich möchte mich um diese Stelle als Kellnerin bewerben.,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Schreiben|Arbeit,
PH0006,polite_expression,Sie können mich telefonisch erreichen.,Quý vị có thể liên hệ với tôi qua điện thoại.,Bewerbung / formeller Brief,Sie können mich unter dieser Nummer telefonisch erreichen.,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Schreiben|Kontakt,
PH0007,polite_expression,Gerne sende ich Ihnen auch meine vollständigen Bewerbungsunterlagen zu.,Tôi cũng sẵn sàng gửi quý vị đầy đủ hồ sơ ứng tuyển.,Bewerbung / formeller Brief,Gerne sende ich Ihnen meine vollständigen Bewerbungsunterlagen zu.,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Schreiben|Arbeit,
PH0008,polite_expression,Ich freue mich auf die Möglichkeit eines persönlichen Gesprächs.,Tôi mong có cơ hội tham gia một buổi trao đổi/phỏng vấn trực tiếp.,Bewerbung / formeller Brief,Ich freue mich auf die Möglichkeit eines persönlichen Gesprächs.,new,0,2026-04-05,,,,dtz_html_2026_04_05_01,DTZ|B1|Schreiben|Arbeit,
```

## 5. Mục đã có nhưng nên bổ sung

- `GR0001 | weil-Satz`  
  HTML có ví dụ thực tế phù hợp để bổ sung vào trường `example`:  
  `..., weil mein Partner dort lebt.`

## 6. Ghi chú lọc theo rule

- Không lấy các câu giải thích đáp án dài dòng vào kho master.
- Ưu tiên mục có giá trị tái sử dụng cao cho DTZ B1:
  - từ vựng đời sống / nhà ở / giao thông / việc làm
  - cấu trúc ngữ pháp lặp lại rõ ràng
  - mẫu câu viết thư / Bewerbung
- Không thêm các đáp án lựa chọn a/b/c như một mục kiến thức riêng.
