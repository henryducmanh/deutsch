"""Write batch_02_vocab.csv from curated vocab data."""
import csv
import os

OUT = r"C:\twv_share\app\deutsch\output\horen_extract\batch_02_vocab.csv"

# Each row: (folder, wort, wortart, formen, bedeutung, beispiel, uebersetzung, thema, tags, notes)
ROWS = [
    # 1.50 HeimwerkerParadies — Farben/Lacke, Rabatte, Renovierungsarbeiten
    ("1.50", "das Angebot", "Substantiv", "das Angebot, -e", "ưu đãi/mặt hàng chào bán", "HeimwerkerParadies bietet aktuell Angebote für Farben und Lacke an.", "HeimwerkerParadies hiện đang chào bán ưu đãi cho sơn và sơn bóng.", "Einkauf", "B1;DTZ;Einkauf", ""),
    ("1.50", "der Rabatt", "Substantiv", "der Rabatt, -e", "giảm giá", "Es werden unschlagbare Rabatte auf Farben und Lacke angeboten.", "Có những mức giảm giá không thể đánh bại cho sơn và sơn bóng.", "Einkauf", "B1;DTZ;Einkauf", ""),
    ("1.50", "die Filiale", "Substantiv", "die Filiale, -n", "chi nhánh", "Es wird keine Information über die Eröffnung einer neuen Filiale gegeben.", "Không có thông tin nào về việc khai trương chi nhánh mới.", "Arbeit", "B1;DTZ;Arbeit", ""),
    ("1.50", "die Renovierung", "Substantiv", "die Renovierung, -en", "việc cải tạo/sửa sang", "Kunden finden alles für Renovierungsarbeiten.", "Khách hàng tìm được mọi thứ cho công việc cải tạo.", "Wohnen", "B1;DTZ;Wohnen", ""),
    ("1.50", "hervorheben", "Verb", "hob hervor - hat hervorgehoben", "nhấn mạnh", "In der Mitteilung wird hervorgehoben, dass Rabatte angeboten werden.", "Trong thông báo nhấn mạnh rằng có các đợt giảm giá.", "Allgemein", "B1;DTZ;Allgemein", "trennbar"),
    ("1.50", "verschönern", "Verb", "verschönerte - hat verschönert", "làm đẹp", "Kunden möchten ihre Wände verschönern.", "Khách hàng muốn làm đẹp các bức tường của mình.", "Wohnen", "B1;DTZ;Wohnen", ""),

    # 1.51 ExpressPaket — Verzögerung, Zustellung
    ("1.51", "die Verzögerung", "Substantiv", "die Verzögerung, -en", "sự chậm trễ", "Es kann zu Verzögerungen bei der Zustellung von Paketen kommen.", "Có thể xảy ra chậm trễ trong việc giao bưu phẩm.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.51", "die Zustellung", "Substantiv", "die Zustellung, -en", "việc giao hàng", "Verzögerungen bei der Zustellung von bis zu zwei Tagen.", "Chậm trễ trong việc giao hàng đến hai ngày.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.51", "das Paket", "Substantiv", "das Paket, -e", "bưu phẩm/gói hàng", "Aufgrund der großen Anzahl von Paketen kommt es zu Verzögerungen.", "Do số lượng bưu phẩm lớn nên có sự chậm trễ.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.51", "kostenlos", "Adjektiv", "", "miễn phí", "Kostenloser Versand für alle Kunden wird nicht erwähnt.", "Việc miễn phí vận chuyển cho tất cả khách hàng không được đề cập.", "Einkauf", "B1;DTZ;Einkauf", ""),
    ("1.51", "der Versand", "Substantiv", "der Versand, ?", "việc gửi hàng", "Es wird nicht über Versandkosten gesprochen.", "Không có ai nói về chi phí gửi hàng.", "Einkauf", "B1;DTZ;Einkauf", ""),
    ("1.51", "ausdrücklich", "Adverb", "", "rõ ràng/dứt khoát", "In der Anzeige wird ausdrücklich erwähnt, dass es Verzögerungen gibt.", "Trong thông báo được nói rõ ràng rằng có sự chậm trễ.", "Allgemein", "B1;DTZ;Allgemein", ""),

    # 1.52 Neue Maßnahmen — Regierung, erneuerbare Energien
    ("1.52", "die Maßnahme", "Substantiv", "die Maßnahme, -n", "biện pháp", "Die Regierung hat neue Maßnahmen beschlossen.", "Chính phủ đã quyết định các biện pháp mới.", "Gesellschaft", "B1;DTZ;Gesellschaft", ""),
    ("1.52", "die Regierung", "Substantiv", "die Regierung, -en", "chính phủ", "Die Regierung hat neue Maßnahmen zur Förderung erneuerbarer Energien beschlossen.", "Chính phủ đã quyết định các biện pháp mới để thúc đẩy năng lượng tái tạo.", "Gesellschaft", "B1;DTZ;Gesellschaft", ""),
    ("1.52", "die Förderung", "Substantiv", "die Förderung, -en", "sự hỗ trợ/khuyến khích", "Die Förderung von Elektroautos wird intensiviert.", "Việc khuyến khích xe điện được đẩy mạnh.", "Gesellschaft", "B1;DTZ;Gesellschaft", ""),
    ("1.52", "erneuerbar", "Adjektiv", "", "có thể tái tạo", "Maßnahmen zur Entwicklung erneuerbarer Energiequellen.", "Các biện pháp để phát triển nguồn năng lượng tái tạo.", "Gesellschaft", "B1;DTZ;Gesellschaft", ""),
    ("1.52", "senken", "Verb", "senkte - hat gesenkt", "giảm xuống", "Ziel ist es, die CO₂-Emissionen um 50 Prozent zu senken.", "Mục tiêu là giảm lượng phát thải CO₂ xuống 50 phần trăm.", "Gesellschaft", "B1;DTZ;Gesellschaft", ""),
    ("1.52", "beschließen", "Verb", "beschloss - hat beschlossen", "quyết định", "Die Regierung hat neue Maßnahmen beschlossen.", "Chính phủ đã quyết định các biện pháp mới.", "Behörde", "B1;DTZ;Behörde", ""),

    # 1.53 Wettervorhersage
    ("1.53", "die Wettervorhersage", "Substantiv", "die Wettervorhersage, -n", "dự báo thời tiết", "In der Wettervorhersage wird ein sonniger Tag erwartet.", "Trong dự báo thời tiết dự kiến một ngày nắng.", "Wetter", "B1;DTZ;Wetter", ""),
    ("1.53", "sonnig", "Adjektiv", "", "có nắng/nắng đẹp", "Heute wird sonniges Wetter mit wenig Wind erwartet.", "Hôm nay dự kiến thời tiết nắng với ít gió.", "Wetter", "B1;DTZ;Wetter", ""),
    ("1.53", "windstill", "Adjektiv", "", "lặng gió", "Es wird fast windstill sein.", "Trời sẽ gần như lặng gió.", "Wetter", "B1;DTZ;Wetter", ""),
    ("1.53", "erwarten", "Verb", "erwartete - hat erwartet", "mong đợi/dự kiến", "Die Zuschauer können heute sonniges Wetter erwarten.", "Khán giả có thể dự kiến thời tiết nắng hôm nay.", "Allgemein", "B1;DTZ;Allgemein", ""),
    ("1.53", "der Zuschauer", "Substantiv", "der Zuschauer, -", "khán giả/người xem", "Was können die Zuschauer heute erwarten?", "Hôm nay khán giả có thể mong đợi điều gì?", "Freizeit", "B1;DTZ;Freizeit", ""),

    # 1.54 Wettervorhersage — bedeckt, Regenschauer
    ("1.54", "bedeckt", "Adjektiv", "", "u ám/có mây dày", "Am übernächsten Tag wird es bedeckt sein.", "Ngày kia trời sẽ u ám.", "Wetter", "B1;DTZ;Wetter", ""),
    ("1.54", "der Regenschauer", "Substantiv", "der Regenschauer, -", "trận mưa rào", "Es können mögliche Regenschauer auftreten.", "Có thể xảy ra các trận mưa rào.", "Wetter", "B1;DTZ;Wetter", ""),
    ("1.54", "bewölkt", "Adjektiv", "", "có mây", "Stark bewölkt ohne Regen ist nicht die richtige Antwort.", "U ám nhiều mà không mưa không phải là đáp án đúng.", "Wetter", "B1;DTZ;Wetter", ""),
    ("1.54", "auftreten", "Verb", "trat auf - ist aufgetreten", "xuất hiện/xảy ra", "Mögliche Regenschauer können auftreten.", "Các trận mưa rào có thể xảy ra.", "Allgemein", "B1;DTZ;Allgemein", "trennbar"),
    ("1.54", "voraussichtlich", "Adverb", "", "dự kiến", "Wie wird das Wetter am übernächsten Tag voraussichtlich sein?", "Thời tiết ngày kia dự kiến sẽ như thế nào?", "Allgemein", "B1;DTZ;Allgemein", ""),

    # 1.55 Elektrowerkstatt — Defekt, geschlossen
    ("1.55", "die Werkstatt", "Substantiv", "die Werkstatt, -stätten", "xưởng sửa chữa", "Die Elektrowerkstatt ist vorübergehend geschlossen.", "Xưởng điện tạm thời đóng cửa.", "Arbeit", "B1;DTZ;Arbeit", ""),
    ("1.55", "der Defekt", "Substantiv", "der Defekt, -e", "lỗi/hỏng hóc", "Die Werkstatt ist wegen eines technischen Defekts geschlossen.", "Xưởng đóng cửa vì lỗi kỹ thuật.", "Allgemein", "B1;DTZ;Allgemein", ""),
    ("1.55", "vorübergehend", "Adjektiv", "", "tạm thời", "Die Elektrowerkstatt ist vorübergehend geschlossen.", "Xưởng điện tạm thời đóng cửa.", "Allgemein", "B1;DTZ;Allgemein", ""),
    ("1.55", "der Personalmangel", "Substantiv", "der Personalmangel, ?", "thiếu nhân sự", "Im Text wird kein Wort über Personalmangel gesagt.", "Trong văn bản không có từ nào nói về thiếu nhân sự.", "Arbeit", "B1;DTZ;Arbeit", ""),
    ("1.55", "die Schließung", "Substantiv", "die Schließung, -en", "việc đóng cửa", "Der Grund für die Schließung ist ein technischer Defekt.", "Lý do đóng cửa là một lỗi kỹ thuật.", "Arbeit", "B1;DTZ;Arbeit", ""),

    # 1.56 Werkstatt Schmidt — Bremsbeläge, Öl
    ("1.56", "der Bremsbelag", "Substantiv", "der Bremsbelag, -beläge", "má phanh", "Die Werkstatt hat die Bremsbeläge ausgetauscht.", "Xưởng đã thay má phanh.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.56", "austauschen", "Verb", "tauschte aus - hat ausgetauscht", "thay thế", "Die Werkstatt hat die Bremsbeläge ausgetauscht.", "Xưởng đã thay má phanh.", "Verkehr", "B1;DTZ;Verkehr", "trennbar"),
    ("1.56", "das Fahrzeug", "Substantiv", "das Fahrzeug, -e", "phương tiện/xe", "Was im Fahrzeug ersetzt wurde?", "Cái gì trong xe đã được thay thế?", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.56", "ersetzen", "Verb", "ersetzte - hat ersetzt", "thay thế", "Im Auto wurden die Bremsbeläge ersetzt.", "Trong xe má phanh đã được thay thế.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.56", "reinigen", "Verb", "reinigte - hat gereinigt", "làm sạch", "Der Innenraumfilter wurde nur gereinigt.", "Bộ lọc khoang nội thất chỉ được làm sạch.", "Alltag", "B1;DTZ;Alltag", ""),

    # 1.57 BKK-Versicherung — Kontonummer, Beiträge
    ("1.57", "die Versicherung", "Substantiv", "die Versicherung, -en", "bảo hiểm", "Die BKK-Versicherung kann die Beiträge nicht abbuchen.", "Bảo hiểm BKK không thể khấu trừ tiền đóng.", "Behörde", "B1;DTZ;Behörde", ""),
    ("1.57", "die Kontonummer", "Substantiv", "die Kontonummer, -n", "số tài khoản", "Eine falsche Kontonummer ist das Problem.", "Một số tài khoản sai là vấn đề.", "Behörde", "B1;DTZ;Behörde", ""),
    ("1.57", "der Beitrag", "Substantiv", "der Beitrag, -träge", "khoản đóng góp/phí", "Die BKK kann die Beiträge für die Kfz-Versicherung nicht abbuchen.", "BKK không thể khấu trừ phí cho bảo hiểm xe.", "Behörde", "B1;DTZ;Behörde", ""),
    ("1.57", "abbuchen", "Verb", "buchte ab - hat abgebucht", "khấu trừ tài khoản", "Die BKK kann die Beiträge nicht von seinem Bankkonto abbuchen.", "BKK không thể khấu trừ phí từ tài khoản ngân hàng của ông ấy.", "Behörde", "B1;DTZ;Behörde", "trennbar"),
    ("1.57", "vorliegen", "Verb", "lag vor - hat vorgelegen", "tồn tại/có sẵn", "Eine falsche Kontonummer liegt vor.", "Một số tài khoản sai đang tồn tại.", "Behörde", "B1;DTZ;Behörde", "trennbar"),

    # 1.58 Durchsage — Gleis, Zug
    ("1.58", "die Durchsage", "Substantiv", "die Durchsage, -n", "thông báo qua loa", "An welchem Gleis fährt der Zug nach Dortmund?", "Tàu đi Dortmund chạy ở sân ga nào?", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.58", "das Gleis", "Substantiv", "das Gleis, -e", "sân ga/đường ray", "Der Zug nach Dortmund fährt von Gleis 12.", "Tàu đi Dortmund chạy từ sân ga 12.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.58", "abfahren", "Verb", "fuhr ab - ist abgefahren", "khởi hành", "Der IC 456 nach Dortmund wird heute von Gleis 12 abfahren.", "IC 456 đi Dortmund hôm nay sẽ khởi hành từ sân ga 12.", "Verkehr", "B1;DTZ;Verkehr", "trennbar"),
    ("1.58", "einfahren", "Verb", "fuhr ein - ist eingefahren", "vào ga", "Auf Gleis 10 wird der Regionalexpress 678 einfahren.", "Tại sân ga 10, tàu khu vực 678 sẽ vào ga.", "Verkehr", "B1;DTZ;Verkehr", "trennbar"),
    ("1.58", "die Änderung", "Substantiv", "die Änderung, -en", "thay đổi", "Das ist eine Änderung gegenüber der gewohnten Abfahrt.", "Đó là một sự thay đổi so với giờ khởi hành thường lệ.", "Allgemein", "B1;DTZ;Allgemein", ""),

    # 1.59 Weg zu einem Freund — Schule, Busbahnhof
    ("1.59", "die Wohnung", "Substantiv", "die Wohnung, -en", "căn hộ/nhà ở", "Seine Wohnung befindet sich gegenüber der großen Schule.", "Căn hộ của ông ấy nằm đối diện với trường lớn.", "Wohnen", "B1;DTZ;Wohnen", ""),
    ("1.59", "gegenüber", "Präposition", "", "đối diện", "Die Wohnung ist direkt gegenüber von der Schule.", "Căn hộ nằm ngay đối diện trường học.", "Wohnen", "B1;DTZ;Wohnen", "đi với Dativ"),
    ("1.59", "der Busbahnhof", "Substantiv", "der Busbahnhof, -höfe", "bến xe buýt", "Karl wohnt nicht neben dem Busbahnhof.", "Karl không sống bên cạnh bến xe buýt.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.59", "sich befinden", "Verb", "befand sich - hat sich befunden", "nằm/ở vị trí", "Seine Wohnung befindet sich gegenüber der Schule.", "Căn hộ của ông ấy nằm đối diện trường học.", "Wohnen", "B1;DTZ;Wohnen", "reflexive"),
    ("1.59", "erwähnen", "Verb", "erwähnte - hat erwähnt", "đề cập đến", "Im Text wird kein Park erwähnt.", "Trong văn bản không có công viên nào được đề cập.", "Allgemein", "B1;DTZ;Allgemein", ""),

    # 1.60 Flughafen — weiterfahren, Bus
    ("1.60", "der Fahrgast", "Substantiv", "der Fahrgast, -gäste", "hành khách", "Was sollen die Fahrgäste tun?", "Hành khách phải làm gì?", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.60", "weiterfahren", "Verb", "fuhr weiter - ist weitergefahren", "đi tiếp", "Die Fahrgäste sollen mit einem Bus weiterfahren.", "Hành khách phải đi tiếp bằng xe buýt.", "Verkehr", "B1;DTZ;Verkehr", "trennbar"),
    ("1.60", "die Fortsetzung", "Substantiv", "die Fortsetzung, -en", "sự tiếp tục", "Die Fortsetzung der Fahrt mit diesem Zug ist nicht möglich.", "Việc tiếp tục hành trình với chuyến tàu này là không thể.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.60", "verlassen", "Verb", "verließ - hat verlassen", "rời khỏi", "Die Fahrgäste sollen den Zug verlassen.", "Hành khách phải rời khỏi tàu.", "Allgemein", "B1;DTZ;Allgemein", ""),
    ("1.60", "bereitstehen", "Verb", "stand bereit - hat bereitgestanden", "sẵn sàng/chờ sẵn", "Sie sollen die bereitstehenden Busse nutzen.", "Họ phải dùng các xe buýt đang chờ sẵn.", "Verkehr", "B1;DTZ;Verkehr", "trennbar"),

    # 1.61 Aufruf — Arztpraxis, Apotheke
    ("1.61", "die Arztpraxis", "Substantiv", "die Arztpraxis, -praxen", "phòng khám bác sĩ", "Der Anruf kommt von einer Arztpraxis.", "Cuộc gọi đến từ một phòng khám bác sĩ.", "Gesundheit", "B1;DTZ;Gesundheit", ""),
    ("1.61", "die Apotheke", "Substantiv", "die Apotheke, -n", "hiệu thuốc", "Im Text wird keine Apotheke erwähnt.", "Trong văn bản không có hiệu thuốc nào được đề cập.", "Gesundheit", "B1;DTZ;Gesundheit", ""),
    ("1.61", "die Versichertenkarte", "Substantiv", "die Versichertenkarte, -n", "thẻ bảo hiểm y tế", "Die Versichertenkarte wird im Kontext der Arztpraxis erwähnt.", "Thẻ bảo hiểm y tế được đề cập trong ngữ cảnh phòng khám.", "Gesundheit", "B1;DTZ;Gesundheit", ""),
    ("1.61", "anrufen", "Verb", "rief an - hat angerufen", "gọi điện", "Klara Schulz ruft von der Praxis Dr. Krantz an.", "Klara Schulz gọi điện từ phòng khám của bác sĩ Krantz.", "Alltag", "B1;DTZ;Alltag", "trennbar"),
    ("1.61", "sich vorstellen", "Verb", "stellte sich vor - hat sich vorgestellt", "tự giới thiệu", "Klara Schulz stellt sich im Text vor.", "Klara Schulz tự giới thiệu trong văn bản.", "Alltag", "B1;DTZ;Alltag", "reflexive, trennbar"),

    # 1.62 Kaiser Platz — umsteigen, Haltestelle
    ("1.62", "umsteigen", "Verb", "stieg um - ist umgestiegen", "đổi tàu/xe", "An der Haltestelle Lippen Straße müssen Sie umsteigen.", "Tại điểm dừng Lippen Straße bạn phải đổi xe.", "Verkehr", "B1;DTZ;Verkehr", "trennbar"),
    ("1.62", "die Haltestelle", "Substantiv", "die Haltestelle, -n", "điểm dừng/trạm", "An der Haltestelle Lippen Strasse umsteigen.", "Đổi xe tại điểm dừng Lippen Strasse.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.62", "die Bauarbeit", "Substantiv", "die Bauarbeit, -en", "công việc xây dựng", "Aufgrund von Bauarbeiten ist die Haltestelle nicht zugänglich.", "Do công việc xây dựng nên điểm dừng không vào được.", "Verkehr", "B1;DTZ;Verkehr", "thường dùng số nhiều"),
    ("1.62", "umleiten", "Verb", "leitete um - hat umgeleitet", "chuyển hướng", "Die U-Bahn-Linien U1 und U2 sind umgeleitet.", "Các tuyến tàu điện ngầm U1 và U2 đã được chuyển hướng.", "Verkehr", "B1;DTZ;Verkehr", "trennbar"),
    ("1.62", "zugänglich", "Adjektiv", "", "có thể tiếp cận được", "Die U-Bahn-Haltestelle ist momentan nicht zugänglich.", "Điểm dừng tàu điện ngầm hiện không thể tiếp cận được.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.62", "gelangen", "Verb", "gelangte - ist gelangt", "đến được", "Was ist der erforderliche Schritt, um zum Kaiser Platz zu gelangen?", "Bước cần thiết để đến được Kaiser Platz là gì?", "Verkehr", "B1;DTZ;Verkehr", ""),

    # 1.63 Der Junge und der Reiher — Film, Uhrzeit
    ("1.63", "die Vorführung", "Substantiv", "die Vorführung, -en", "buổi chiếu/buổi biểu diễn", "Das ist die Uhrzeit für die Vorführung dieses Films.", "Đó là giờ chiếu của bộ phim này.", "Freizeit", "B1;DTZ;Freizeit", ""),
    ("1.63", "zeigen", "Verb", "zeigte - hat gezeigt", "chiếu/cho xem", "Der Film wird um 16 Uhr gezeigt.", "Phim được chiếu lúc 16 giờ.", "Freizeit", "B1;DTZ;Freizeit", ""),
    ("1.63", "der Film", "Substantiv", "der Film, -e", "phim", "Um wie viel Uhr gibt es den Film?", "Phim chiếu lúc mấy giờ?", "Freizeit", "B1;DTZ;Freizeit", ""),
    ("1.63", "genau", "Adjektiv", "", "chính xác", "Dies ist die genaue Uhrzeit für die Vorführung.", "Đây là giờ chiếu chính xác.", "Allgemein", "B1;DTZ;Allgemein", ""),

    # 1.64 Möbelhaus Müller — Sommerschlussverkauf, Herbst
    ("1.64", "der Sommerschlussverkauf", "Substantiv", "der Sommerschlussverkauf, -käufe", "đợt bán xả hàng cuối hè", "Der Sommerschlussverkauf läuft bis zum Ende des Herbstes.", "Đợt xả hàng cuối hè kéo dài đến hết mùa thu.", "Einkauf", "B1;DTZ;Einkauf", ""),
    ("1.64", "der Herbst", "Substantiv", "der Herbst, -e", "mùa thu", "Der Verkauf endet zum Ende des Herbstes.", "Đợt bán hàng kết thúc vào cuối mùa thu.", "Wetter", "B1;DTZ;Wetter", ""),
    ("1.64", "gültig", "Adjektiv", "", "có hiệu lực", "Die Angebote sind bis zu diesem Zeitpunkt gültig.", "Các ưu đãi có hiệu lực đến thời điểm này.", "Einkauf", "B1;DTZ;Einkauf", ""),
    ("1.64", "laufen", "Verb", "lief - ist gelaufen", "đang diễn ra/chạy", "Der Sommerschlussverkauf läuft noch bis zum Ende des Herbstes.", "Đợt bán xả hàng cuối hè vẫn đang diễn ra đến cuối mùa thu.", "Allgemein", "B1;DTZ;Allgemein", ""),
    ("1.64", "das Weihnachten", "Substantiv", "das Weihnachten, -", "lễ Giáng sinh", "Kunden können die perfekten Weihnachtsgeschenke finden.", "Khách hàng có thể tìm được những món quà Giáng sinh hoàn hảo.", "Freizeit", "B1;DTZ;Freizeit", ""),

    # 1.65 Firma Komfi-Elektrik — Gasherd, anschließen
    ("1.65", "der Gasherd", "Substantiv", "der Gasherd, -e", "bếp ga", "Die Firma kann den Gasherd nicht anschließen.", "Công ty không thể lắp bếp ga.", "Wohnen", "B1;DTZ;Wohnen", ""),
    ("1.65", "anschließen", "Verb", "schloss an - hat angeschlossen", "kết nối/lắp đặt", "Sie können den Gasherd heute nicht anschließen.", "Hôm nay họ không thể lắp bếp ga.", "Wohnen", "B1;DTZ;Wohnen", "trennbar"),
    ("1.65", "der Zeitraum", "Substantiv", "der Zeitraum, -räume", "khoảng thời gian", "Sie bieten einen neuen Zeitraum zwischen 17 und 19 Uhr an.", "Họ đề xuất một khoảng thời gian mới từ 17 đến 19 giờ.", "Allgemein", "B1;DTZ;Allgemein", ""),
    ("1.65", "vorbeikommen", "Verb", "kam vorbei - ist vorbeigekommen", "ghé qua", "Er soll nicht einfach vorbeikommen.", "Ông ấy không nên chỉ ghé qua.", "Alltag", "B1;DTZ;Alltag", "trennbar"),
    ("1.65", "bestätigen", "Verb", "bestätigte - hat bestätigt", "xác nhận", "Herr Barak soll anrufen und bestätigen, ob die Zeit passt.", "Ông Barak nên gọi điện và xác nhận xem giờ có phù hợp không.", "Alltag", "B1;DTZ;Alltag", ""),

    # 1.66 City-Immobilien — Wohnung, Balkon
    ("1.66", "der Balkon", "Substantiv", "der Balkon, -e", "ban công", "Die angebotene Wohnung hat keinen Balkon.", "Căn hộ được chào không có ban công.", "Wohnen", "B1;DTZ;Wohnen", ""),
    ("1.66", "verfügbar", "Adjektiv", "", "có sẵn", "Eine 3-Zimmer-Wohnung ist für Familie Müller verfügbar.", "Một căn hộ 3 phòng có sẵn cho gia đình Müller.", "Wohnen", "B1;DTZ;Wohnen", ""),
    ("1.66", "anbieten", "Verb", "bot an - hat angeboten", "chào mời/đề nghị", "Diese Wohnung wird nicht angeboten.", "Căn hộ này không được chào mời.", "Einkauf", "B1;DTZ;Einkauf", "trennbar"),
    ("1.66", "die Alternative", "Substantiv", "die Alternative, -n", "giải pháp thay thế", "Diese Wohnung ist eine Alternative.", "Căn hộ này là một giải pháp thay thế.", "Allgemein", "B1;DTZ;Allgemein", ""),
    ("1.66", "entsprechen", "Verb", "entsprach - hat entsprochen", "phù hợp với", "Diese Option entspricht nicht dem Angebot.", "Lựa chọn này không phù hợp với ưu đãi.", "Allgemein", "B1;DTZ;Allgemein", "đi với Dativ"),

    # 1.67 Volkshochschule — Sprachenberatung, anmelden
    ("1.67", "die Sprachenberatung", "Substantiv", "die Sprachenberatung, -en", "tư vấn ngôn ngữ", "Die Sprachenberatung ist montags und mittwochs geöffnet.", "Phòng tư vấn ngôn ngữ mở cửa thứ Hai và thứ Tư.", "Bildung", "B1;DTZ;Bildung", ""),
    ("1.67", "sich anmelden", "Verb", "meldete sich an - hat sich angemeldet", "đăng ký", "Sie möchten sich für einen Deutschkurs anmelden.", "Bạn muốn đăng ký một khóa học tiếng Đức.", "Bildung", "B1;DTZ;Bildung", "reflexive, trennbar"),
    ("1.67", "der Deutschkurs", "Substantiv", "der Deutschkurs, -e", "khóa học tiếng Đức", "Sie möchten sich für einen Deutschkurs anmelden.", "Bạn muốn đăng ký một khóa học tiếng Đức.", "Bildung", "B1;DTZ;Bildung", ""),
    ("1.67", "passend", "Adjektiv", "", "phù hợp", "Sie sollen zur Beratung kommen, um den passenden Kurs zu finden.", "Bạn nên đến tư vấn để tìm khóa học phù hợp.", "Allgemein", "B1;DTZ;Allgemein", ""),
    ("1.67", "vorab", "Adverb", "", "trước/trước khi", "Sie sollen vorab zur Beratung kommen.", "Bạn nên đến tư vấn trước.", "Allgemein", "B1;DTZ;Allgemein", ""),

    # 1.68 Arzt benötigen — Notdienst, Notfall
    ("1.68", "der Notfall", "Substantiv", "der Notfall, -fälle", "trường hợp khẩn cấp", "In Notfällen sollen Sie den Ärztlichen Notdienst kontaktieren.", "Trong trường hợp khẩn cấp bạn nên liên hệ dịch vụ cấp cứu y tế.", "Gesundheit", "B1;DTZ;Gesundheit", ""),
    ("1.68", "der Notdienst", "Substantiv", "der Notdienst, -e", "dịch vụ cấp cứu", "Kontaktieren Sie den Ärztlichen Notdienst unter 12929.", "Hãy liên hệ dịch vụ cấp cứu y tế theo số 12929.", "Gesundheit", "B1;DTZ;Gesundheit", ""),
    ("1.68", "kontaktieren", "Verb", "kontaktierte - hat kontaktiert", "liên hệ", "Sie sollen den Notdienst kontaktieren.", "Bạn nên liên hệ dịch vụ cấp cứu.", "Alltag", "B1;DTZ;Alltag", ""),
    ("1.68", "dringend", "Adjektiv", "", "khẩn cấp/gấp", "Wenn Sie dringend einen Arzt benötigen.", "Khi bạn cần bác sĩ gấp.", "Gesundheit", "B1;DTZ;Gesundheit", ""),
    ("1.68", "erreichbar", "Adjektiv", "", "có thể liên lạc được", "Die Praxis ist erst ab dem 26. Juli wieder erreichbar.", "Phòng khám chỉ có thể liên lạc lại từ ngày 26 tháng Bảy.", "Alltag", "B1;DTZ;Alltag", ""),

    # 1.69 Hauptbahnhof 2 — S-Bahn, U-Bahn, Verspätung
    ("1.69", "die Verspätung", "Substantiv", "die Verspätung, -en", "sự chậm trễ", "Es kommt zu 20 Minuten Verspätung bei allen S-Bahnen.", "Có chậm trễ 20 phút đối với tất cả các tàu S-Bahn.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.69", "zuverlässig", "Adjektiv", "", "đáng tin cậy", "Die U-Bahn ist die zuverlässigste Option.", "Tàu điện ngầm là lựa chọn đáng tin cậy nhất.", "Allgemein", "B1;DTZ;Allgemein", ""),
    ("1.69", "der Fahrplan", "Substantiv", "der Fahrplan, -pläne", "lịch trình chạy", "Die U-Bahn-Linien verkehren nach Fahrplan.", "Các tuyến tàu điện ngầm chạy đúng lịch trình.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.69", "verkehren", "Verb", "verkehrte - hat verkehrt", "chạy/lưu thông", "Die U-Bahn-Linien U1, U2 und U3 verkehren nach Fahrplan.", "Các tuyến U1, U2 và U3 chạy theo lịch trình.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.69", "erreichen", "Verb", "erreichte - hat erreicht", "đạt được/đến được", "Die U-Bahn ist die schnellste Option, um den Hauptbahnhof zu erreichen.", "Tàu điện ngầm là lựa chọn nhanh nhất để đến ga chính.", "Verkehr", "B1;DTZ;Verkehr", ""),

    # 1.70 Orthopäde — Vertretung, dringend
    ("1.70", "die Vertretung", "Substantiv", "die Vertretung, -en", "người thay thế/đại diện", "Bei dringenden Angelegenheiten wird eine Vertretung genannt.", "Trong trường hợp gấp một người thay thế được nêu ra.", "Arbeit", "B1;DTZ;Arbeit", ""),
    ("1.70", "die Angelegenheit", "Substantiv", "die Angelegenheit, -en", "vấn đề/việc cần giải quyết", "Bei dringenden Angelegenheiten anrufen.", "Hãy gọi điện trong các vấn đề gấp.", "Allgemein", "B1;DTZ;Allgemein", ""),
    ("1.70", "geschlossen", "Adjektiv", "", "đóng cửa", "Die Praxis von Dr. Krantz ist bis zum 1. März geschlossen.", "Phòng khám của bác sĩ Krantz đóng cửa đến ngày 1 tháng Ba.", "Alltag", "B1;DTZ;Alltag", ""),
    ("1.70", "klären", "Verb", "klärte - hat geklärt", "làm rõ/giải quyết", "Sie sollten anrufen, um dringende Angelegenheiten zu klären.", "Bạn nên gọi điện để giải quyết các vấn đề gấp.", "Alltag", "B1;DTZ;Alltag", ""),
    ("1.70", "öffnen", "Verb", "öffnete - hat geöffnet", "mở cửa", "Die Praxis öffnet erst am 3. März wieder.", "Phòng khám chỉ mở cửa lại vào ngày 3 tháng Ba.", "Alltag", "B1;DTZ;Alltag", ""),

    # 1.71 Bürgeramt — Öffnungszeiten
    ("1.71", "das Bürgeramt", "Substantiv", "das Bürgeramt, -ämter", "văn phòng hành chính công", "Das Bürgeramt ist samstags von 9 bis 14 Uhr geöffnet.", "Văn phòng hành chính công mở cửa thứ Bảy từ 9 đến 14 giờ.", "Behörde", "B1;DTZ;Behörde", ""),
    ("1.71", "der Anrufbeantworter", "Substantiv", "der Anrufbeantworter, -", "máy trả lời tự động", "Es geht um den Anrufbeantworter des Bürgeramts Dortmund.", "Đây là về máy trả lời tự động của văn phòng hành chính Dortmund.", "Behörde", "B1;DTZ;Behörde", ""),
    ("1.71", "täglich", "Adverb", "", "hàng ngày", "Das Bürgeramt hat nicht täglich bis 18 Uhr geöffnet.", "Văn phòng hành chính không mở cửa đến 18 giờ hàng ngày.", "Allgemein", "B1;DTZ;Allgemein", ""),
    ("1.71", "geöffnet", "Adjektiv", "", "đang mở cửa", "Es ist samstags von 9 bis 14 Uhr geöffnet.", "Nó mở cửa thứ Bảy từ 9 đến 14 giờ.", "Alltag", "B1;DTZ;Alltag", ""),
    ("1.71", "die Geduld", "Substantiv", "die Geduld, ?", "sự kiên nhẫn", "Vielen Dank für Ihre Geduld.", "Cảm ơn sự kiên nhẫn của bạn.", "Allgemein", "B1;DTZ;Allgemein", ""),

    # 1.72 Karten an der Kasse — Vorstellung, abholen
    ("1.72", "die Vorstellung", "Substantiv", "die Vorstellung, -en", "buổi biểu diễn/buổi chiếu", "Die Vorstellung beginnt um 20:30 Uhr.", "Buổi chiếu bắt đầu lúc 20:30.", "Freizeit", "B1;DTZ;Freizeit", ""),
    ("1.72", "abholen", "Verb", "holte ab - hat abgeholt", "đến lấy/đón", "Max soll die Karten an der Kasse abholen.", "Max nên đến quầy vé để lấy vé.", "Alltag", "B1;DTZ;Alltag", "trennbar"),
    ("1.72", "die Kasse", "Substantiv", "die Kasse, -n", "quầy thu ngân/quầy vé", "Die Karten sollen an der Kasse abgeholt werden.", "Vé phải được lấy ở quầy vé.", "Einkauf", "B1;DTZ;Einkauf", ""),
    ("1.72", "die Nachricht", "Substantiv", "die Nachricht, -en", "tin nhắn/thông báo", "In der Nachricht bittet Alex Max, früher dort zu sein.", "Trong tin nhắn Alex nhờ Max đến đó sớm hơn.", "Alltag", "B1;DTZ;Alltag", ""),
    ("1.72", "bitten", "Verb", "bat - hat gebeten", "nhờ/đề nghị", "Alex bittet Max, eine halbe Stunde vor Beginn am Kino zu sein.", "Alex nhờ Max có mặt tại rạp nửa giờ trước khi bắt đầu.", "Alltag", "B1;DTZ;Alltag", ""),

    # 1.73 Elternsprechtag — Brief, warten
    ("1.73", "der Elternsprechtag", "Substantiv", "der Elternsprechtag, -e", "ngày họp phụ huynh", "Der Elternsprechtag wurde am Freitag abgesagt.", "Buổi họp phụ huynh đã bị hủy vào thứ Sáu.", "Bildung", "B1;DTZ;Bildung", ""),
    ("1.73", "der Brief", "Substantiv", "der Brief, -e", "lá thư", "Sie soll auf einen Brief mit dem neuen Termin warten.", "Bà ấy nên đợi một lá thư có lịch hẹn mới.", "Alltag", "B1;DTZ;Alltag", ""),
    ("1.73", "absagen", "Verb", "sagte ab - hat abgesagt", "hủy bỏ", "Der Elternsprechtag wurde am Freitag abgesagt.", "Buổi họp phụ huynh đã bị hủy vào thứ Sáu.", "Alltag", "B1;DTZ;Alltag", "trennbar"),
    ("1.73", "der Termin", "Substantiv", "der Termin, -e", "lịch hẹn", "Sie wird einen neuen Termin schriftlich bekommen.", "Bà ấy sẽ nhận được lịch hẹn mới bằng văn bản.", "Alltag", "B1;DTZ;Alltag", ""),
    ("1.73", "schriftlich", "Adjektiv", "", "bằng văn bản", "Sie wird einen neuen Termin schriftlich bekommen.", "Bà ấy sẽ nhận được lịch hẹn mới bằng văn bản.", "Behörde", "B1;DTZ;Behörde", ""),

    # 1.74 Busbahnhof — Gleis 7, S-Bahn
    ("1.74", "die Ansage", "Substantiv", "die Ansage, -n", "lời thông báo", "In der Ansage wird gesagt, dass die S4 von Gleis 7 abfährt.", "Trong thông báo nói rằng tàu S4 khởi hành từ sân ga 7.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.74", "die Linie", "Substantiv", "die Linie, -n", "tuyến", "Die Linie S4 zum Busbahnhof fährt heute von Gleis 7.", "Tuyến S4 đến bến xe buýt hôm nay chạy từ sân ga 7.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.74", "angeben", "Verb", "gab an - hat angegeben", "nêu/cho biết", "Es wird angegeben, dass die S4 heute von Gleis 7 abfährt.", "Được cho biết rằng tàu S4 hôm nay khởi hành từ sân ga 7.", "Allgemein", "B1;DTZ;Allgemein", "trennbar"),
    ("1.74", "falsch", "Adjektiv", "", "sai", "Von Gleis 5 ist falsch, weil die S4 heute von Gleis 7 abfährt.", "Từ sân ga 5 là sai vì tàu S4 hôm nay đi từ sân ga 7.", "Allgemein", "B1;DTZ;Allgemein", ""),

    # 1.75 Spezialitäten aus Spanien — Sonderaktion, Obst
    ("1.75", "das Obst", "Substantiv", "das Obst, ?", "trái cây", "Welche Artikel sind heute bei einer Sonderaktion erhältlich? Obst.", "Mặt hàng nào hôm nay có ưu đãi đặc biệt? Trái cây.", "Einkauf", "B1;DTZ;Einkauf", "không chia số nhiều"),
    ("1.75", "die Sonderaktion", "Substantiv", "die Sonderaktion, -en", "đợt khuyến mãi đặc biệt", "Welche Artikel sind heute bei einer Sonderaktion erhältlich?", "Mặt hàng nào hôm nay có trong đợt khuyến mãi đặc biệt?", "Einkauf", "B1;DTZ;Einkauf", ""),
    ("1.75", "erhältlich", "Adjektiv", "", "có sẵn để mua", "Welche Artikel sind heute bei einer Sonderaktion erhältlich?", "Mặt hàng nào hôm nay có sẵn để mua với khuyến mãi đặc biệt?", "Einkauf", "B1;DTZ;Einkauf", ""),
    ("1.75", "der Sonderpreis", "Substantiv", "der Sonderpreis, -e", "giá ưu đãi", "Spanische Erdbeeren und Orangen sind zu einem Sonderpreis im Angebot.", "Dâu tây và cam Tây Ban Nha có giá ưu đãi.", "Einkauf", "B1;DTZ;Einkauf", ""),
    ("1.75", "der Artikel", "Substantiv", "der Artikel, -", "mặt hàng/sản phẩm", "Welche Artikel sind heute bei einer Sonderaktion erhältlich?", "Mặt hàng nào hôm nay có trong đợt khuyến mãi?", "Einkauf", "B1;DTZ;Einkauf", ""),

    # 1.76 Kundenkarte — bezahlen, Barzahlung
    ("1.76", "die Kundenkarte", "Substantiv", "die Kundenkarte, -n", "thẻ khách hàng", "Welche Möglichkeiten bietet die Kundenkarte?", "Thẻ khách hàng mang lại những khả năng nào?", "Einkauf", "B1;DTZ;Einkauf", ""),
    ("1.76", "die Barzahlung", "Substantiv", "die Barzahlung, -en", "thanh toán bằng tiền mặt", "Sie können ohne Barzahlung einkaufen.", "Bạn có thể mua sắm mà không cần thanh toán tiền mặt.", "Einkauf", "B1;DTZ;Einkauf", ""),
    ("1.76", "bezahlen", "Verb", "bezahlte - hat bezahlt", "thanh toán", "Sie können später bezahlen.", "Bạn có thể thanh toán sau.", "Einkauf", "B1;DTZ;Einkauf", ""),
    ("1.76", "monatlich", "Adjektiv", "", "hàng tháng", "Die Einkäufe werden monatlich verrechnet.", "Các giao dịch mua được thanh toán hàng tháng.", "Allgemein", "B1;DTZ;Allgemein", ""),
    ("1.76", "verrechnen", "Verb", "verrechnete - hat verrechnet", "thanh toán bù trừ/quyết toán", "Die Einkäufe werden monatlich verrechnet.", "Các giao dịch mua được quyết toán hàng tháng.", "Behörde", "B1;DTZ;Behörde", ""),

    # 1.77 Agentur für Arbeit — Sekretariat, Fortbildung
    ("1.77", "die Agentur", "Substantiv", "die Agentur, -en", "cơ quan/đại lý", "Agentur für Arbeit ist eine Behörde.", "Cơ quan việc làm là một cơ quan hành chính.", "Behörde", "B1;DTZ;Behörde", ""),
    ("1.77", "das Sekretariat", "Substantiv", "das Sekretariat, -e", "văn phòng thư ký", "Frau Müller soll im Sekretariat anrufen.", "Bà Müller nên gọi điện đến văn phòng thư ký.", "Arbeit", "B1;DTZ;Arbeit", ""),
    ("1.77", "die Fortbildung", "Substantiv", "die Fortbildung, -en", "khóa đào tạo nâng cao", "Frau Müller wird nicht gebeten, eine Fortbildung zu machen.", "Bà Müller không được yêu cầu tham gia khóa đào tạo nâng cao.", "Bildung", "B1;DTZ;Bildung", ""),
    ("1.77", "die Schulung", "Substantiv", "die Schulung, -en", "khóa huấn luyện", "Herr Radomir selbst ist auf einer Schulung.", "Bản thân ông Radomir đang ở một khóa huấn luyện.", "Bildung", "B1;DTZ;Bildung", ""),

    # 1.78 Hauptbahnhof — halten, umsteigen
    ("1.78", "halten", "Verb", "hielt - hat gehalten", "dừng lại", "Die Züge halten heute nicht am Hauptbahnhof.", "Các tàu hôm nay không dừng ở ga chính.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.78", "der Passagier", "Substantiv", "der Passagier, -e", "hành khách", "Passagiere sollen mit der U-Bahn fahren.", "Hành khách nên đi tàu điện ngầm.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.78", "die Möglichkeit", "Substantiv", "die Möglichkeit, -en", "khả năng/cách", "Es gibt eine Möglichkeit, mit der U-Bahn und dem Bus zum Hauptbahnhof zu gelangen.", "Có một cách để đến ga chính bằng tàu điện ngầm và xe buýt.", "Allgemein", "B1;DTZ;Allgemein", ""),
    ("1.78", "wechseln", "Verb", "wechselte - hat gewechselt", "đổi/chuyển", "Sie sollen in den Bus 8 umsteigen.", "Bạn nên chuyển sang xe buýt số 8.", "Verkehr", "B1;DTZ;Verkehr", "ở đây dùng cụm umsteigen — đồng nghĩa wechseln"),

    # 1.79 Versichertenkarte — Zahnarzt, vergessen
    ("1.79", "der Zahnarzt", "Substantiv", "der Zahnarzt, -ärzte", "nha sĩ", "Herr Didi soll mit der Versichertenkarte zum Zahnarzt gehen.", "Ông Didi nên đến nha sĩ với thẻ bảo hiểm y tế.", "Gesundheit", "B1;DTZ;Gesundheit", ""),
    ("1.79", "vergessen", "Verb", "vergaß - hat vergessen", "quên", "Er hat die Versichertenkarte beim letzten Termin vergessen.", "Anh ấy đã quên thẻ bảo hiểm y tế ở lần hẹn trước.", "Alltag", "B1;DTZ;Alltag", ""),
    ("1.79", "die Behandlung", "Substantiv", "die Behandlung, -en", "việc điều trị", "Es geht nicht um eine sofortige Behandlung.", "Vấn đề không phải là điều trị ngay lập tức.", "Gesundheit", "B1;DTZ;Gesundheit", ""),
    ("1.79", "ausmachen", "Verb", "machte aus - hat ausgemacht", "sắp xếp/hẹn", "Herr Didi muss keinen neuen Termin ausmachen.", "Ông Didi không cần sắp xếp lịch hẹn mới.", "Alltag", "B1;DTZ;Alltag", "trennbar"),
    ("1.79", "sofort", "Adverb", "", "ngay lập tức", "Es geht nicht um eine sofortige Behandlung.", "Vấn đề không phải là điều trị ngay lập tức.", "Allgemein", "B1;DTZ;Allgemein", ""),

    # 1.80 Ellenbogenoperation — Hausarzt, Blutwerte
    ("1.80", "der Hausarzt", "Substantiv", "der Hausarzt, -ärzte", "bác sĩ gia đình", "Der Patient soll zum Hausarzt gehen.", "Bệnh nhân nên đến bác sĩ gia đình.", "Gesundheit", "B1;DTZ;Gesundheit", ""),
    ("1.80", "die Überweisung", "Substantiv", "die Überweisung, -en", "giấy giới thiệu/chuyển khoản", "Eine Überweisung wird nicht benötigt.", "Một giấy giới thiệu không cần thiết.", "Gesundheit", "B1;DTZ;Gesundheit", ""),
    ("1.80", "die Blutwerte", "Substantiv", "die Blutwerte (Pl.)", "các chỉ số máu", "Er soll die Blutwerte für die Anästhesie holen.", "Ông ấy nên lấy các chỉ số máu cho việc gây mê.", "Gesundheit", "B1;DTZ;Gesundheit", "thường ở số nhiều"),
    ("1.80", "aufsuchen", "Verb", "suchte auf - hat aufgesucht", "đến tìm/đến gặp", "Herr Aslan soll möglichst bald seinen Hausarzt aufsuchen.", "Ông Aslan nên đến gặp bác sĩ gia đình càng sớm càng tốt.", "Gesundheit", "B1;DTZ;Gesundheit", "trennbar"),
    ("1.80", "der Patient", "Substantiv", "der Patient, -en", "bệnh nhân", "Was soll der Patient tun?", "Bệnh nhân nên làm gì?", "Gesundheit", "B1;DTZ;Gesundheit", "n-Deklination"),

    # 1.81 Nächste Stunde — Kurs, Vertretung
    ("1.81", "ausfallen", "Verb", "fiel aus - ist ausgefallen", "bị hủy/không diễn ra", "Der Kurs am Mittwoch fällt leider aus.", "Tiếc là khóa học thứ Tư không diễn ra.", "Bildung", "B1;DTZ;Bildung", "trennbar"),
    ("1.81", "krank", "Adjektiv", "", "ốm/bệnh", "Herr Müller ist krank.", "Ông Müller bị ốm.", "Gesundheit", "B1;DTZ;Gesundheit", ""),
    ("1.81", "stattfinden", "Verb", "fand statt - hat stattgefunden", "diễn ra", "Der Kurs findet am Donnerstag statt.", "Khóa học diễn ra vào thứ Năm.", "Allgemein", "B1;DTZ;Allgemein", "trennbar"),

    # 1.82 Schulz & Sohn — Büroleiterin, verschieben
    ("1.82", "die Büroleiterin", "Substantiv", "die Büroleiterin, -nen", "nữ trưởng văn phòng", "Frau Glück, die Büroleiterin, soll angerufen werden.", "Bà Glück, trưởng văn phòng, cần được gọi điện.", "Arbeit", "B1;DTZ;Arbeit", ""),
    ("1.82", "verschieben", "Verb", "verschob - hat verschoben", "dời lịch", "Der Termin wurde verschoben.", "Lịch hẹn đã bị dời.", "Alltag", "B1;DTZ;Alltag", ""),
    ("1.82", "gebeten werden", "Redemittel", "", "được nhờ/đề nghị", "Frau Müller wird gebeten, Frau Glück anzurufen.", "Bà Müller được nhờ gọi điện cho bà Glück.", "Alltag", "B1;DTZ;Alltag", "Passiv của bitten"),
    ("1.82", "angegeben", "Adjektiv", "", "đã được nêu/cho sẵn", "Sie soll Frau Glück unter der angegebenen Telefonnummer anrufen.", "Bà ấy nên gọi cho bà Glück theo số điện thoại đã cho.", "Alltag", "B1;DTZ;Alltag", "Partizip II của angeben"),

    # 1.83 Fahrgäste zum Flughafen
    ("1.83", "der Flughafen", "Substantiv", "der Flughafen, -häfen", "sân bay", "Sie planen, zum Flughafen zu gelangen.", "Bạn dự định đến sân bay.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.83", "deutlich", "Adjektiv", "", "rõ ràng", "Die Ansage macht deutlich, dass die S8 von Gleis 12 abfährt.", "Thông báo nêu rõ rằng tàu S8 khởi hành từ sân ga 12.", "Allgemein", "B1;DTZ;Allgemein", ""),
    ("1.83", "benutzen", "Verb", "benutzte - hat benutzt", "sử dụng", "Welches Gleis müssen Sie benutzen?", "Bạn phải sử dụng sân ga nào?", "Allgemein", "B1;DTZ;Allgemein", ""),

    # 1.84 Internet NOVA — Hotline, Taste drücken
    ("1.84", "die Taste", "Substantiv", "die Taste, -n", "phím bấm", "Sie sollen die Taste 3 drücken.", "Bạn nên nhấn phím số 3.", "Technologie", "B1;DTZ;Technologie", ""),
    ("1.84", "drücken", "Verb", "drückte - hat gedrückt", "nhấn/bấm", "Die Taste 3 drücken.", "Nhấn phím số 3.", "Technologie", "B1;DTZ;Technologie", ""),
    ("1.84", "die Internetverbindung", "Substantiv", "die Internetverbindung, -en", "kết nối internet", "Ihre Internetverbindung ist nicht aktiv.", "Kết nối internet của bạn không hoạt động.", "Technologie", "B1;DTZ;Technologie", ""),
    ("1.84", "die Rechnung", "Substantiv", "die Rechnung, -en", "hóa đơn", "Die Taste 2 ist für Fragen zur Rechnung gedacht.", "Phím số 2 dành cho các câu hỏi về hóa đơn.", "Behörde", "B1;DTZ;Behörde", ""),
    ("1.84", "drahtlos", "Adjektiv", "", "không dây", "Die Einrichtung einer drahtlosen Internetverbindung.", "Việc thiết lập kết nối internet không dây.", "Technologie", "B1;DTZ;Technologie", ""),

    # 1.85 Autowerkstatt Müller — Fahrzeug abholen
    ("1.85", "reparieren", "Verb", "reparierte - hat repariert", "sửa chữa", "Das Fahrzeug ist vollständig repariert.", "Chiếc xe đã được sửa chữa hoàn toàn.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.85", "das Ersatzteil", "Substantiv", "das Ersatzteil, -e", "phụ tùng thay thế", "Die Werkstatt hat sich um die Ersatzteile gekümmert.", "Xưởng đã lo về phụ tùng thay thế.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.85", "vollständig", "Adjektiv", "", "hoàn toàn", "Das Fahrzeug ist vollständig repariert.", "Chiếc xe đã được sửa chữa hoàn toàn.", "Allgemein", "B1;DTZ;Allgemein", ""),
    ("1.85", "sich kümmern", "Verb", "kümmerte sich - hat sich gekümmert", "lo liệu/chăm sóc", "Die Werkstatt hat sich um die Ersatzteile gekümmert.", "Xưởng đã lo liệu về phụ tùng thay thế.", "Alltag", "B1;DTZ;Alltag", "reflexive, đi với um + Akk"),

    # 1.86 DTZ Prüfung
    ("1.86", "schriftlich", "Adjektiv", "", "viết/bằng giấy bút", "Die schriftliche DTZ-Prüfung beginnt am Montag um 9 Uhr.", "Kỳ thi DTZ viết bắt đầu thứ Hai lúc 9 giờ.", "Bildung", "B1;DTZ;Bildung", "lưu ý: trùng từ ở 1.73 nhưng nghĩa khác cảnh huống"),
    ("1.86", "die Prüfung", "Substantiv", "die Prüfung, -en", "kỳ thi", "Wann beginnt die schriftliche DTZ-Prüfung?", "Khi nào kỳ thi DTZ viết bắt đầu?", "Bildung", "B1;DTZ;Bildung", ""),
    ("1.86", "die Ankunftszeit", "Substantiv", "die Ankunftszeit, -en", "giờ đến", "8:30 Uhr ist die Ankunftszeit, nicht der Prüfungsbeginn.", "8:30 là giờ đến, không phải giờ bắt đầu thi.", "Allgemein", "B1;DTZ;Allgemein", ""),
    ("1.86", "beginnen", "Verb", "begann - hat begonnen", "bắt đầu", "Die schriftliche DTZ-Prüfung beginnt am Montag.", "Kỳ thi DTZ viết bắt đầu vào thứ Hai.", "Allgemein", "B1;DTZ;Allgemein", ""),

    # 1.87 Autowerkstatt — Reparatur, verzögern
    ("1.87", "die Reparatur", "Substantiv", "die Reparatur, -en", "việc sửa chữa", "Warum hat sich die Reparatur verzögert?", "Tại sao việc sửa chữa bị chậm trễ?", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.87", "sich verzögern", "Verb", "verzögerte sich - hat sich verzögert", "bị chậm trễ", "Die Reparatur hat sich verzögert.", "Việc sửa chữa đã bị chậm trễ.", "Allgemein", "B1;DTZ;Allgemein", "reflexive"),
    ("1.87", "die Lieferung", "Substantiv", "die Lieferung, -en", "việc giao hàng", "Wegen einer verspäteten Lieferung ist nicht der Grund.", "Vì giao hàng chậm không phải là lý do.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.87", "verspätet", "Adjektiv", "", "muộn/trễ", "Wegen einer verspäteten Lieferung ist nicht korrekt.", "Vì giao hàng muộn là không chính xác.", "Allgemein", "B1;DTZ;Allgemein", ""),
    ("1.87", "die Notwendigkeit", "Substantiv", "die Notwendigkeit, -en", "sự cần thiết", "Wegen der Notwendigkeit mehrerer Ersatzteile.", "Vì sự cần thiết của nhiều phụ tùng thay thế.", "Allgemein", "B1;DTZ;Allgemein", ""),

    # 1.88 DTZ Prüfungsbüro — Vertretung, krank
    ("1.88", "abnehmen", "Verb", "nahm ab - hat abgenommen", "nhận/tổ chức (kỳ thi)", "Eine Vertretung wird die Prüfung abnehmen.", "Một người thay thế sẽ tổ chức kỳ thi.", "Bildung", "B1;DTZ;Bildung", "trennbar"),
    ("1.88", "regulär", "Adjektiv", "", "thường lệ/chính thức", "Herr Schneider ist der reguläre Prüfer.", "Ông Schneider là giám khảo chính thức.", "Allgemein", "B1;DTZ;Allgemein", ""),
    ("1.88", "der Prüfer", "Substantiv", "der Prüfer, -", "giám khảo", "Herr Schneider, der reguläre Prüfer, ist krank.", "Ông Schneider, giám khảo chính thức, bị ốm.", "Bildung", "B1;DTZ;Bildung", ""),
    ("1.88", "durchführen", "Verb", "führte durch - hat durchgeführt", "thực hiện/tổ chức", "Eine Vertretung wird die Prüfung durchführen.", "Một người thay thế sẽ tổ chức kỳ thi.", "Arbeit", "B1;DTZ;Arbeit", "trennbar"),

    # 1.89 Online-Banking — Wartungsarbeiten
    ("1.89", "der Zugriff", "Substantiv", "der Zugriff, -e", "quyền truy cập", "Wann ist der Online-Banking-Zugriff nicht verfügbar?", "Khi nào quyền truy cập ngân hàng trực tuyến không khả dụng?", "Technologie", "B1;DTZ;Technologie", ""),
    ("1.89", "die Wartungsarbeit", "Substantiv", "die Wartungsarbeit, -en", "công việc bảo trì", "Wegen Wartungsarbeiten ist der Zugriff nicht verfügbar.", "Do công việc bảo trì nên không thể truy cập.", "Technologie", "B1;DTZ;Technologie", "thường dùng số nhiều"),
    ("1.89", "der Vormittag", "Substantiv", "der Vormittag, -e", "buổi sáng", "Diese Zeit fällt auf den Vormittag.", "Khoảng thời gian này rơi vào buổi sáng.", "Allgemein", "B1;DTZ;Allgemein", ""),
    ("1.89", "stattfinden", "Verb", "fand statt - hat stattgefunden", "diễn ra", "Die Wartungsarbeiten finden am Vormittag statt.", "Công việc bảo trì diễn ra vào buổi sáng.", "Allgemein", "B1;DTZ;Allgemein", "trennbar - lặp ý từ 1.81 nhưng giữ context"),

    # 1.90 Tierarztpraxis — Krankenhauseinlieferung
    ("1.90", "die Tierarztpraxis", "Substantiv", "die Tierarztpraxis, -praxen", "phòng khám thú y", "Die Tierarztpraxis Müller sagt Termine ab.", "Phòng khám thú y Müller hủy các lịch hẹn.", "Gesundheit", "B1;DTZ;Gesundheit", ""),
    ("1.90", "die Krankenhauseinlieferung", "Substantiv", "die Krankenhauseinlieferung, -en", "việc nhập viện", "Termine werden wegen einer Krankenhauseinlieferung abgesagt.", "Các lịch hẹn bị hủy do việc nhập viện.", "Gesundheit", "B1;DTZ;Gesundheit", ""),
    ("1.90", "unerwartet", "Adjektiv", "", "bất ngờ", "Die Hauptärztin wurde unerwartet ins Krankenhaus eingeliefert.", "Bác sĩ chính đã bất ngờ phải nhập viện.", "Gesundheit", "B1;DTZ;Gesundheit", ""),
    ("1.90", "einliefern", "Verb", "lieferte ein - hat eingeliefert", "đưa vào (bệnh viện)", "Die Hauptärztin wurde ins Krankenhaus eingeliefert.", "Bác sĩ chính đã được đưa vào bệnh viện.", "Gesundheit", "B1;DTZ;Gesundheit", "trennbar"),
    ("1.90", "die Schwangerschaft", "Substantiv", "die Schwangerschaft, -en", "thai kỳ", "Eine Schwangerschaft ist nicht der Grund für die Absage.", "Thai kỳ không phải là lý do hủy.", "Familie", "B1;DTZ;Familie", ""),

    # 1.91 Fahrradgeschäft — Überlastung, Reparatur
    ("1.91", "die Überlastung", "Substantiv", "die Überlastung, -en", "sự quá tải", "Sie nehmen keine neuen Reparaturen wegen einer Überlastung an.", "Họ không nhận sửa chữa mới do quá tải.", "Arbeit", "B1;DTZ;Arbeit", ""),
    ("1.91", "annehmen", "Verb", "nahm an - hat angenommen", "tiếp nhận/chấp nhận", "Das Fahrradgeschäft nimmt keine neuen Reparaturen an.", "Cửa hàng xe đạp không nhận sửa chữa mới.", "Arbeit", "B1;DTZ;Arbeit", "trennbar"),
    ("1.91", "der Umzug", "Substantiv", "der Umzug, -züge", "việc chuyển nhà", "Wegen eines Umzugs ist nicht der Grund.", "Vì chuyển nhà không phải là lý do.", "Wohnen", "B1;DTZ;Wohnen", ""),
    ("1.91", "plötzlich", "Adverb", "", "đột ngột/bất ngờ", "Es gibt plötzlich sehr viele Reparaturaufträge.", "Đột nhiên có rất nhiều đơn sửa chữa.", "Allgemein", "B1;DTZ;Allgemein", ""),
    ("1.91", "der Auftrag", "Substantiv", "der Auftrag, -träge", "đơn đặt hàng/nhiệm vụ", "Es gibt sehr viele Reparaturaufträge.", "Có rất nhiều đơn sửa chữa.", "Arbeit", "B1;DTZ;Arbeit", ""),

    # 1.92 Reisebüro — Gleisbauarbeiten, annullieren
    ("1.92", "annullieren", "Verb", "annullierte - hat annulliert", "hủy bỏ", "Der Zug wurde aufgrund von Gleisbauarbeiten annulliert.", "Chuyến tàu đã bị hủy do công việc xây dựng đường ray.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.92", "die Gleisbauarbeit", "Substantiv", "die Gleisbauarbeit, -en", "công việc xây dựng đường ray", "Wegen Gleisbauarbeiten wurde der Zug annulliert.", "Do công việc xây dựng đường ray chuyến tàu đã bị hủy.", "Verkehr", "B1;DTZ;Verkehr", "thường dùng số nhiều"),
    ("1.92", "die Störung", "Substantiv", "die Störung, -en", "sự cố/gián đoạn", "Keine Störung wird als Grund angegeben.", "Không có sự cố nào được nêu làm lý do.", "Technologie", "B1;DTZ;Technologie", ""),
    ("1.92", "der Unfall", "Substantiv", "der Unfall, -fälle", "tai nạn", "Kein Unfall wird erwähnt.", "Không có tai nạn nào được đề cập.", "Verkehr", "B1;DTZ;Verkehr", ""),

    # 1.93 Veranstaltungszentrum — Theateraufführung, Verletzung
    ("1.93", "die Theateraufführung", "Substantiv", "die Theateraufführung, -en", "buổi diễn kịch", "Die Theateraufführung wird abgesagt.", "Buổi diễn kịch bị hủy.", "Freizeit", "B1;DTZ;Freizeit", ""),
    ("1.93", "die Verletzung", "Substantiv", "die Verletzung, -en", "vết thương/chấn thương", "Wegen einer Verletzung wird die Aufführung abgesagt.", "Do chấn thương nên buổi diễn bị hủy.", "Gesundheit", "B1;DTZ;Gesundheit", ""),
    ("1.93", "sich verletzen", "Verb", "verletzte sich - hat sich verletzt", "tự làm bị thương", "Die Hauptdarstellerin hat sich verletzt.", "Nữ diễn viên chính đã bị thương.", "Gesundheit", "B1;DTZ;Gesundheit", "reflexive"),
    ("1.93", "der Streik", "Substantiv", "der Streik, -s", "cuộc đình công", "Der Streik ist nicht der Grund für die Absage.", "Cuộc đình công không phải là lý do hủy.", "Arbeit", "B1;DTZ;Arbeit", ""),
    ("1.93", "der Feiertag", "Substantiv", "der Feiertag, -e", "ngày lễ", "Der Feiertag ist nicht der Grund.", "Ngày lễ không phải là lý do.", "Freizeit", "B1;DTZ;Freizeit", ""),

    # 1.94 Fitnessstudio — Renovierung
    ("1.94", "das Fitnessstudio", "Substantiv", "das Fitnessstudio, -s", "phòng tập gym", "Das Fitnessstudio ist geschlossen.", "Phòng tập gym đã đóng cửa.", "Freizeit", "B1;DTZ;Freizeit", ""),
    ("1.94", "schließen", "Verb", "schloss - hat geschlossen", "đóng cửa", "Wegen Renovierungsarbeiten müssen wir das Studio schließen.", "Do công việc cải tạo nên chúng tôi phải đóng phòng tập.", "Alltag", "B1;DTZ;Alltag", ""),
    ("1.94", "die Krankheit", "Substantiv", "die Krankheit, -en", "bệnh tật", "Die Krankheit wird nur erwähnt.", "Bệnh tật chỉ được đề cập.", "Gesundheit", "B1;DTZ;Gesundheit", ""),
    ("1.94", "das Jubiläum", "Substantiv", "das Jubiläum, Jubiläen", "lễ kỷ niệm", "Eine kleine Party zum Jubiläum.", "Một bữa tiệc nhỏ nhân ngày kỷ niệm.", "Freizeit", "B1;DTZ;Freizeit", ""),

    # 1.95 Buchhandlung — Wasserschaden
    ("1.95", "die Buchhandlung", "Substantiv", "die Buchhandlung, -en", "hiệu sách", "Die Buchhandlung am Markt ist geschlossen.", "Hiệu sách ở chợ đóng cửa.", "Einkauf", "B1;DTZ;Einkauf", ""),
    ("1.95", "der Wasserschaden", "Substantiv", "der Wasserschaden, -schäden", "hư hại do nước", "Die Buchhandlung ist wegen eines Wasserschadens geschlossen.", "Hiệu sách đóng cửa do hư hại bởi nước.", "Wohnen", "B1;DTZ;Wohnen", ""),
    ("1.95", "die Stromunterbrechung", "Substantiv", "die Stromunterbrechung, -en", "sự cắt điện", "Eine Stromunterbrechung ist nicht der Grund.", "Việc cắt điện không phải là lý do.", "Technologie", "B1;DTZ;Technologie", ""),
    ("1.95", "das Feuer", "Substantiv", "das Feuer, -", "lửa/hỏa hoạn", "Wegen eines Feuers ist nicht korrekt.", "Vì hỏa hoạn là không đúng.", "Allgemein", "B1;DTZ;Allgemein", ""),

    # 1.96 Büro Frau Müller — Anruf, Termin vereinbaren
    ("1.96", "vereinbaren", "Verb", "vereinbarte - hat vereinbart", "thỏa thuận/hẹn", "Das Büro wird sich telefonisch melden, um einen neuen Termin zu vereinbaren.", "Văn phòng sẽ liên lạc qua điện thoại để hẹn một lịch mới.", "Arbeit", "B1;DTZ;Arbeit", ""),
    ("1.96", "sich melden", "Verb", "meldete sich - hat sich gemeldet", "liên lạc/báo lại", "Das Büro wird sich telefonisch melden.", "Văn phòng sẽ liên lạc qua điện thoại.", "Alltag", "B1;DTZ;Alltag", "reflexive"),
    ("1.96", "telefonisch", "Adverb", "", "qua điện thoại", "Das Büro wird sich telefonisch melden.", "Văn phòng sẽ liên lạc qua điện thoại.", "Alltag", "B1;DTZ;Alltag", ""),
    ("1.96", "erhalten", "Verb", "erhielt - hat erhalten", "nhận được", "Sie sollen einen Brief erhalten.", "Bạn sẽ nhận được một lá thư.", "Allgemein", "B1;DTZ;Allgemein", ""),

    # 1.97 Realschule — Brief, Elternsprechtag (duplicates of 1.73 already covered: Elternsprechtag, Brief, schriftlich, Termin)
    ("1.97", "die Realschule", "Substantiv", "die Realschule, -n", "trường trung học thực hành", "Frau Glück bekommt eine Nachricht von der Realschule.", "Bà Glück nhận được thông báo từ trường trung học thực hành.", "Bildung", "B1;DTZ;Bildung", ""),
    ("1.97", "informieren", "Verb", "informierte - hat informiert", "thông báo", "Frau Glück wird informiert, dass ein neuer Termin mitgeteilt wird.", "Bà Glück được thông báo rằng sẽ có một lịch hẹn mới.", "Allgemein", "B1;DTZ;Allgemein", ""),
    ("1.97", "mitteilen", "Verb", "teilte mit - hat mitgeteilt", "thông báo/truyền đạt", "Ein neuer Termin wird schriftlich mitgeteilt.", "Một lịch hẹn mới sẽ được thông báo bằng văn bản.", "Behörde", "B1;DTZ;Behörde", "trennbar"),

    # 1.98 Verkehrshinweis — Autobahn, Ausfahrt, Sperrung
    ("1.98", "die Ausfahrt", "Substantiv", "die Ausfahrt, -en", "lối ra (đường cao tốc)", "Welche Autobahnausfahrt ist gesperrt?", "Lối ra nào trên đường cao tốc bị chặn?", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.98", "die Autobahn", "Substantiv", "die Autobahn, -en", "đường cao tốc", "Die Autobahnausfahrt 15 ist gesperrt.", "Lối ra số 15 của đường cao tốc bị chặn.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.98", "gesperrt", "Adjektiv", "", "bị chặn/đóng", "Die Autobahnausfahrt 15 ist gesperrt.", "Lối ra số 15 đường cao tốc bị chặn.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.98", "die Zufahrt", "Substantiv", "die Zufahrt, -en", "đường vào", "Die Zufahrt zur Ausfahrt 20 kann beeinträchtigt sein.", "Đường vào lối ra số 20 có thể bị ảnh hưởng.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.98", "empfehlen", "Verb", "empfahl - hat empfohlen", "khuyến nghị/khuyên", "Es wird empfohlen, alternative Routen zu wählen.", "Khuyến nghị chọn các tuyến đường thay thế.", "Allgemein", "B1;DTZ;Allgemein", ""),
    ("1.98", "die Route", "Substantiv", "die Route, -n", "tuyến đường", "Es wird empfohlen, alternative Routen zu wählen.", "Khuyến nghị chọn các tuyến đường thay thế.", "Verkehr", "B1;DTZ;Verkehr", ""),
    ("1.98", "beeinträchtigen", "Verb", "beeinträchtigte - hat beeinträchtigt", "làm ảnh hưởng/cản trở", "Die Zufahrt kann durch Straßensperrungen beeinträchtigt sein.", "Đường vào có thể bị ảnh hưởng do các điểm chặn đường.", "Allgemein", "B1;DTZ;Allgemein", ""),
]

# Dedupe within batch by (wort, wortart)
seen = set()
deduped = []
for r in ROWS:
    key = (r[1].lower(), r[2])
    if key in seen:
        continue
    seen.add(key)
    deduped.append(r)

with open(OUT, "w", encoding="utf-8", newline="") as f:
    w = csv.writer(f, quoting=csv.QUOTE_MINIMAL)
    for r in deduped:
        w.writerow(r)

folders_set = set(r[0] for r in deduped)
print(f"Wrote {len(deduped)} rows across {len(folders_set)} folders to {OUT}")
print(f"Duplicates removed: {len(ROWS) - len(deduped)}")
