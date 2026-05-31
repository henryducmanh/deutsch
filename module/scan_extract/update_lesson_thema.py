#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# update_lesson_thema.py -- Fix broken JSON + update thema theo chu de DTZ B1
#
# 307/343 file JSON bi cut tai "_meta". Fix = cat truoc _meta, dong JSON lai.
# Sau do map thema theo tu khoa DTZ B1.
#
# Cach dung:
#   python update_lesson_thema.py --dry-run          # preview
#   python update_lesson_thema.py --apply            # ap dung that
#   python update_lesson_thema.py --dry-run --id 4.7

import json, sys, os, glob
from collections import Counter

SCRIPT_DIR  = os.path.dirname(os.path.abspath(__file__))
LESSONS_DIR = os.path.normpath(os.path.join(SCRIPT_DIR, '..', 'deutsch_web', 'lessons'))

DTZ_THEMES = [
    ("Arbeit und Beruf", [
        "arbeit", "beruf", "job", "stelle", "kollegen", "buero", "homeoffice",
        "chef", "gehalt", "kuendigung", "bewerbung", "firma", "mitarbeiter",
        "arbeitszeit", "teilzeit", "vollzeit", "schicht",
        "betrieb", "abteilung", "vorstellungsgespraeach", "lebenslauf",
        "arbeitslos", "beschaeftigung", "lohn", "arbeitgeber", "arbeitnehmer",
        "work-life-balance", "ueberstunden",
        # ASCII-safe: also match original German
        u"büro", u"kündigung", u"arbeitgeber", u"überstunden",
        u"beschäftigung", u"berufsausbildung",
    ]),
    ("Gesundheit", [
        u"gesundheit", u"arzt", u"ärztin", u"krank", u"krankenhaus", u"klinik",
        u"medikament", u"apotheke", u"schmerz", u"untersuchung", u"behandlung",
        u"patient", u"symptom", u"fieber", u"allergie", u"operation",
        u"notaufnahme", u"krankenkasse", u"pflege", u"therapie",
        u"gesund", u"fit", u"diät", u"abnehmen", u"ernährung",
        u"gesunde ernährung", u"stress", u"burnout", u"entspannung",
        u"wohlbefinden", u"psychisch", u"mental", u"vorsorge", u"impfung",
    ]),
    ("Wetter und Alltag", [
        u"wetter", u"regen", u"sonne", u"schnee", u"wind", u"temperatur",
        u"grad", u"wettervorhersage", u"wetterbericht", u"bewölkt", u"wolken",
        u"sturm", u"gewitter", u"nebel", u"frost", u"hitze", u"kälte",
        u"wetterlag", u"klima",
    ]),
    ("Wohnen", [
        u"wohnung", u"haus", u"miete", u"zimmer", u"bad", u"balkon",
        u"umzug", u"vermieter", u"mieter", u"nachbar", u"renovierung", u"möbel",
        u"einrichtung", u"immobilie", u"wohngemeinschaft", u"wg",
        u"keller", u"aufzug", u"hausverwaltung", u"nebenkosten", u"kaution",
        u"reparatur", u"heizung", u"strom", u"einzug", u"auszug",
    ]),
    ("Familie und Soziales", [
        u"familie", u"kind", u"kinder", u"eltern", u"mutter", u"vater",
        u"geschwister", u"hochzeit", u"erziehung", u"scheidung", u"beziehung",
        u"haushalt", u"sozial", u"gemeinschaft", u"nachbarschaft",
        u"pflegeheim", u"großeltern", u"baby", u"schwanger",
        u"kinderbetreuung", u"kindergarten",
    ]),
    ("Reise und Verkehr", [
        u"bahn", u"bus", u"auto", u"zug", u"flugzeug", u"fahrkarte", u"ticket",
        u"bahnhof", u"flughafen", u"reise", u"urlaub", u"hotel", u"unterkunft",
        u"gepäck", u"fahren", u"straße", u"stau", u"parkplatz", u"parken",
        u"fahrrad", u"taxi", u"verbindung", u"abfahrt", u"ankunft",
        u"verspätung", u"koffer", u"tour", u"reservierung", u"nahverkehr",
        u"öpnv", u"u-bahn", u"s-bahn", u"ice", u"buslinie", u"haltestelle",
        u"fahrt", u"reiseplan", u"tourismus", u"mietwagen",
    ]),
    ("Bildung und Schule", [
        u"schule", u"lernen", u"studium", u"kurs", u"ausbildung",
        u"weiterbildung", u"prüfung", u"lehrer", u"klasse", u"unterricht",
        u"uni", u"hochschule", u"student", u"abitur", u"zeugnis", u"note",
        u"hausaufgaben", u"bildung", u"qualifikation", u"zertifikat",
        u"sprachkurs", u"schüler", u"lehrplan", u"seminar", u"nachhilfe",
        u"digitalisierung in der bildung",
    ]),
    ("Umwelt und Natur", [
        u"umwelt", u"natur", u"recycling", u"müll", u"energie", u"nachhaltig",
        u"klimawandel", u"co2", u"ökologisch", u"umweltschutz", u"solar",
        u"windkraft", u"elektroauto", u"plastik", u"abfall", u"kompost",
        u"biologisch", u"bio", u"regional", u"saisonal", u"tierschutz",
        u"wald", u"ökologie", u"emissionen", u"erneuerbare",
    ]),
    ("Technologie und Medien", [
        u"computer", u"internet", u"handy", u"smartphone", u"digital", u"app",
        u"technologie", u"online", u"soziale medien", u"medien", u"fernsehen",
        u"radio", u"podcast", u"website", u"email", u"datenschutz",
        u"privatsphäre", u"künstliche intelligenz", u"ki", u"roboter",
        u"automatisierung", u"software", u"hardware", u"gaming", u"streaming",
        u"netzwerk", u"digitale technologien", u"moderne technologien",
        u"technologische", u"innovation", u"plattform",
    ]),
    ("Einkaufen und Konsum", [
        u"einkaufen", u"supermarkt", u"preis", u"kaufen", u"markt",
        u"geschäft", u"laden", u"angebot", u"konsum", u"ware", u"produkt",
        u"rechnung", u"rückgabe", u"umtausch", u"rabatt", u"sonderangebot",
        u"online-shop", u"bestellung", u"lieferung", u"kassenbon",
        u"kundenhotline", u"kundenservice", u"reklamation", u"garantie",
    ]),
    ("Essen und Trinken", [
        u"essen", u"kochen", u"restaurant", u"mahlzeit", u"lebensmittel",
        u"speise", u"trinken", u"getränk", u"café", u"frühstück",
        u"mittagessen", u"abendessen", u"vegetarisch", u"vegan", u"zutaten",
        u"kochrezept", u"grill", u"backen", u"ernährungsgewohnheiten",
    ]),
    ("Behörde und Ämter", [
        u"amt", u"behörde", u"antrag", u"formular", u"pass", u"visum",
        u"ausweis", u"anmelden", u"abmelden", u"ummelden", u"stadtamt",
        u"bürgeramt", u"aufenthaltstitel", u"aufenthalt", u"ausländerbehörde",
        u"meldeamt", u"personalausweis", u"genehmigung", u"bescheinigung",
        u"unterlagen", u"behördlich", u"zuständig",
    ]),
    ("Freizeit und Hobby", [
        u"freizeit", u"hobby", u"kino", u"musik", u"theater", u"festival",
        u"ausflug", u"verein", u"spielen", u"tanzen", u"wandern", u"schwimmen",
        u"lesen", u"basteln", u"malen", u"fotografieren", u"garten",
        u"konzert", u"museum", u"ausstellung", u"kultur", u"veranstaltung",
        u"feierabend", u"wochenende", u"sport", u"fitness", u"yoga",
        u"entspannen", u"freizeit gestalten",
    ]),
    ("Gesellschaft und Integration", [
        u"gesellschaft", u"integration", u"politik", u"bürger", u"migration",
        u"demokratie", u"wahl", u"partei", u"recht", u"pflicht", u"gesetz",
        u"gleichstellung", u"diskriminierung", u"vielfalt", u"toleranz",
        u"zusammenleben", u"kulturell", u"heimat", u"lebensstil",
        u"kulturelle vielfalt", u"zusammenhalt",
    ]),
]


def extract_text(data):
    parts = []
    parts.append(data.get('title', ''))
    parts.append(data.get('thema', ''))
    parts.append(data.get('instructions', ''))
    for a in (data.get('aussagen') or []):
        q = a.get('question') or a.get('label') or ''
        parts.append(q)
        for o in (a.get('options') or []):
            parts.append(o.get('text', '') if isinstance(o, dict) else str(o))
    for t in (data.get('transcript') or [])[:5]:
        parts.append(t.get('text', '') if isinstance(t, dict) else str(t))
    return ' '.join(parts).lower()


def score_theme(text, keywords):
    score = 0
    for kw in keywords:
        if kw in text:
            score += len(kw.split()) * (1 + text.count(kw))
    return score


def assign_thema(data):
    text = extract_text(data)
    scores = {name: score_theme(text, kws) for name, kws in DTZ_THEMES}
    best = max(scores, key=lambda k: scores[k])
    return (best, scores[best]) if scores[best] > 0 else ("Sonstiges", 0)


def fix_truncated_json(path):
    raw = open(path, encoding='utf-8', errors='replace').read()
    meta_pos = raw.rfind('"_meta"')
    if meta_pos < 0:
        return raw, False
    before = raw[:meta_pos].rstrip().rstrip(',')
    return before + '\n}\n', True


def process_file(path, dry_run=True):
    filename = os.path.basename(path)
    result = {'file': filename, 'fixed_json': False,
              'old_thema': '', 'new_thema': '', 'score': 0, 'changed': False}

    raw = open(path, encoding='utf-8', errors='replace').read()
    try:
        data = json.loads(raw)
        fixed_text = None
    except json.JSONDecodeError:
        fixed_text, was_fixed = fix_truncated_json(path)
        if not was_fixed:
            result['error'] = 'Cannot fix JSON'
            return result
        try:
            data = json.loads(fixed_text)
            result['fixed_json'] = True
        except json.JSONDecodeError as e:
            result['error'] = str(e)
            return result

    result['old_thema'] = data.get('thema', '')
    new_thema, score = assign_thema(data)
    result['new_thema'] = new_thema
    result['score'] = score
    result['changed'] = result['fixed_json'] or (new_thema != result['old_thema'])

    if not dry_run and result['changed']:
        data['thema'] = new_thema
        out = json.dumps(data, ensure_ascii=False, indent=2) + '\n'
        open(path, 'w', encoding='utf-8').write(out)

    if result['changed']:
        tag = '[DRY]' if dry_run else '[OK ]'
        fix = ' +fixJSON' if result['fixed_json'] else ''
        print('{} {:<14} {:<36} -> {} (score={}){}'.format(
            tag, filename,
            result['old_thema'][:35],
            new_thema, score, fix))

    return result


def main():
    dry_run   = '--apply' not in sys.argv
    single_id = None
    if '--id' in sys.argv:
        idx = sys.argv.index('--id')
        single_id = sys.argv[idx + 1] if idx + 1 < len(sys.argv) else None

    mode = 'DRY-RUN' if dry_run else 'APPLY'
    print('=== update_lesson_thema.py -- {} ==='.format(mode))
    print('Lessons dir: {}\n'.format(LESSONS_DIR))

    if single_id:
        path = os.path.join(LESSONS_DIR, single_id + '.json')
        if not os.path.isfile(path):
            print('File not found: ' + path); sys.exit(1)
        files = [path]
    else:
        files = sorted(glob.glob(os.path.join(LESSONS_DIR, '*.json')))

    results, errors = [], []
    for path in files:
        r = process_file(path, dry_run=dry_run)
        results.append(r)
        if 'error' in r:
            errors.append(r)

    total      = len(results)
    fixed_json = sum(1 for r in results if r['fixed_json'])
    thema_chg  = sum(1 for r in results if r.get('new_thema') != r.get('old_thema') and 'error' not in r)
    unchanged  = sum(1 for r in results if not r.get('changed') and 'error' not in r)
    score0     = [r for r in results if r.get('score', 1) == 0 and 'error' not in r]

    print('\n=== Result ({}) ==='.format(mode))
    print('  Total files      : {}'.format(total))
    print('  JSON fixed       : {}'.format(fixed_json))
    print('  Thema updated    : {}'.format(thema_chg))
    print('  Unchanged        : {}'.format(unchanged))
    print('  Score=0 (review) : {}'.format(len(score0)))
    print('  Errors           : {}'.format(len(errors)))

    if score0:
        print('\nFiles needing review (score=0):')
        for r in score0:
            print("  {}: '{}'".format(r['file'], r['old_thema'][:60]))

    if errors:
        print('\nErrors:')
        for r in errors:
            print('  {}: {}'.format(r['file'], r.get('error')))

    dist = Counter(r['new_thema'] for r in results if 'new_thema' in r and 'error' not in r)
    if dist:
        print('\nThema distribution:')
        for t, n in dist.most_common():
            print('  {:3d}  {}'.format(n, t))

    if dry_run:
        print('\n-> Add --apply to write changes.')


if __name__ == '__main__':
    main()
