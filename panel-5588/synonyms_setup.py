import json, urllib.request

synonyms = {
    # Медицина
    "врач": ["доктор", "медик", "врач", "vrach", "doctor", "médecin"],
    "доктор": ["врач", "медик", "доктор", "vrach", "doctor", "médecin"],
    "медик": ["врач", "доктор", "медик"],
    "стоматолог": ["дантист", "зубной", "стоматолог", "stomatolog", "dentist", "dentiste"],
    "дантист": ["стоматолог", "зубной", "дантист", "dentist", "dentiste"],
    "зубной": ["стоматолог", "дантист", "зубной"],
    "педиатр": ["детский врач", "педиатр", "pediatr", "pediatre", "pédiatre"],
    "гинеколог": ["гинеколог", "ginekolog", "gynécologue"],
    "кардиолог": ["кардиолог", "kardiolog", "cardiologue"],
    "невролог": ["невропатолог", "невролог", "nevrolog", "neurologue"],
    "невропатолог": ["невролог", "невропатолог"],
    "офтальмолог": ["окулист", "офтальмолог", "oftalmolog", "ophtalmologue"],
    "окулист": ["офтальмолог", "окулист"],
    "дерматолог": ["кожный врач", "дерматолог", "dermatolog", "dermatologue"],
    "хирург": ["хирург", "hirurg", "chirurgien"],
    "ортопед": ["ортопед", "ortoped", "orthopédiste"],
    "психолог": ["психотерапевт", "психолог", "psiholog", "psychologue"],
    "психотерапевт": ["психолог", "психотерапевт", "psychothérapeute"],
    "массажист": ["массаж", "массажист", "massazhist", "masseur"],
    "массаж": ["массажист", "массаж", "massage"],
    "косметолог": ["косметолог", "kosmetolog", "esthéticienne"],
    "диетолог": ["диетолог", "dietolog", "diététicien"],
    "логопед": ["логопед", "logoped", "orthophoniste"],
    "остеопат": ["остеопат", "osteopat", "ostéopathe"],

    # Красота
    "парикмахер": ["барбер", "стилист", "парикмахер", "parikmakher", "coiffeur", "hairdresser"],
    "барбер": ["парикмахер", "стилист", "барбер", "barber"],
    "стилист": ["парикмахер", "барбер", "стилист", "stylist"],
    "маникюр": ["ногти", "маникюр", "manikyur", "manucure", "nail"],
    "педикюр": ["педикюр", "pedikyur", "pédicure"],

    # Юридические
    "юрист": ["адвокат", "правовед", "юрист", "yurist", "lawyer", "avocat"],
    "адвокат": ["юрист", "правовед", "адвокат", "advokat", "lawyer", "avocat"],
    "правовед": ["юрист", "адвокат", "правовед"],
    "нотариус": ["нотариальный", "нотариус", "notarius", "notaire"],

    # Образование
    "репетитор": ["учитель", "преподаватель", "репетитор", "repetitor", "tuteur", "tutor"],
    "учитель": ["репетитор", "преподаватель", "учитель", "uchitel"],
    "преподаватель": ["репетитор", "учитель", "преподаватель"],

    # Финансы
    "бухгалтер": ["финансист", "бухгалтер", "buhgalter", "comptable", "accountant"],
    "финансист": ["бухгалтер", "финансист"],

    # Переводы
    "переводчик": ["переводчик", "perevodchik", "translator", "traducteur", "interprète"],

    # Няня / дети
    "няня": ["babysitter", "няня", "nanya", "nounou"],

    # Строительство и ремонт
    "сантехник": ["сантехник", "santehnik", "plumber", "plombier"],
    "электрик": ["электрик", "elektrik", "electrician", "électricien"],
    "строитель": ["строитель", "stroitel", "construction", "maçon"],
    "маляр": ["маляр", "malyar", "peintre", "painter"],
    "плотник": ["плотник", "plotnik", "charpentier", "carpenter"],

    # Недвижимость
    "риелтор": ["агент по недвижимости", "риелтор", "rieltor", "agent immobilier"],

    # Перевозки
    "грузчик": ["переезд", "грузчик", "gruzchik", "déménageur", "mover"],
    "переезд": ["грузчик", "перевозка", "переезд", "demenagement", "déménagement"],
    "перевозка": ["переезд", "грузчик", "перевозка", "transport", "livraison"],
    "такси": ["такси", "taxi", "chauffeur", "водитель"],
    "водитель": ["такси", "шофер", "водитель", "voditель", "chauffeur"],

    # IT
    "программист": ["разработчик", "программист", "programmist", "developer", "développeur"],
    "разработчик": ["программист", "разработчик", "developer"],
    "дизайнер": ["дизайнер", "dizainer", "designer"],
    "фотограф": ["фотограф", "fotograf", "photographe", "photographer"],
    "видеограф": ["видеограф", "videograf", "vidéaste", "videographer"],

    # Бытовые
    "уборка": ["клининг", "уборка", "uborka", "ménage", "nettoyage", "cleaning"],
    "клининг": ["уборка", "клининг", "cleaning", "nettoyage"],
    "сад": ["садовник", "сад", "sad", "jardinier", "gardener"],
    "садовник": ["садовник", "сад", "sadovnik", "jardinier"],

    # Бизнес
    "бизнес": ["бизнес", "biznes", "business", "entreprise"],
    "страхование": ["страховка", "страхование", "strahovanie", "assurance", "insurance"],
    "страховка": ["страхование", "страховка"],
}

data = json.dumps(synonyms, ensure_ascii=False).encode('utf-8')
req = urllib.request.Request(
    'http://127.0.0.1:7700/indexes/services/settings/synonyms',
    data=data,
    method='PUT',
    headers={
        'Content-Type': 'application/json',
        'Authorization': 'Bearer acad64db686c48a6cca1578b0ecdcea3938fa6653377fc00b3868e58beeed554'
    }
)
with urllib.request.urlopen(req) as r:
    print('OK:', r.read().decode())
