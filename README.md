# AMADA PIM-Vergleichstool

## Was macht dieses Tool?

Dieses Tool hilft dabei, **Produkte aus dem AMADA Produktkatalog (PIM) schnell und übersichtlich miteinander zu vergleichen** — direkt im Browser, ohne Excel, ohne manuelle Recherche.

Das PIM (Product Information Management System) ist die zentrale Datenbank, in der alle technischen Daten, Bilder und Eigenschaften der AMADA-Produkte gepflegt werden. Dieses Tool greift auf genau diese Daten zu und stellt sie so dar, dass man mehrere Produkte nebeneinander vergleichen kann.

---

## Was kann ich damit tun?

Die Startseite bietet zwei Hauptbereiche:

### 1. Produkt-Vergleich

Mehrere Produkte einer Familie auswählen und nebeneinander vergleichen — technische Daten oder Ausstattung.

Auf der Vergleichsseite gibt es fünf Reiter (Tabs):

| Tab | Enthält |
|---|---|
| **Maschinen** | Laser-, Stanz- und Abkantmaschinen |
| **Automation** | Automatisierungslösungen und Beladesysteme |
| **Zubehör** | Optionales Zubehör für Maschinen |
| **Stanzwerkzeuge** | Werkzeuge für Stanzmaschinen |
| **Abkantwerkzeuge** | Werkzeuge für Abkantpressen |

In jedem Reiter wählt man zunächst eine **Produktfamilie** (z.B. „Laserschneidmaschinen") aus einem Dropdown-Menü. Danach erscheint die Liste aller Produkte dieser Familie — mit Produktbild, Artikelnummer und Status (aktiv/deaktiviert).

### 2. Werkzeugfinder

Abkant-Werkzeug**modelle** nach V-Öffnung und Winkel filtern — für die schnelle Suche nach dem passenden Werkzeugtyp (ohne alle Längenvarianten).

### 3. Admin (nur mit `ADMIN_ENABLED=true`)

PIM-Familien den Tabs zuweisen. Für normale Nutzer **nicht sichtbar** — nur Admins sehen den Link und können `pim_family_settings.php` aufrufen.

---

## Produkt-Vergleich im Detail

Man wählt beliebig viele Produkte per Checkbox aus und startet dann den Vergleich:

- **Technische Daten vergleichen** — Zeigt alle technischen Eigenschaften (Abmessungen, Gewicht, Leistung, …) nebeneinander in einer Tabelle. Zahlenwerte werden mit der richtigen Einheit angezeigt (z.B. `6500 mm`, `80 kg`, `7 kW`). Option-Werte werden auf Deutsch übersetzt.

- **Ausstattung vergleichen** — Zeigt welche Zusatzgeräte und Komponenten jedem Produkt zugeordnet sind.

Beide Vergleichsansichten zeigen das Produktbild jedes Artikels im Tabellenkopf, sodass man sofort weiß, über welches Gerät man spricht.

---

## Welche Vorteile bringt das Tool?

**Ohne dieses Tool:**
- Man muss im PIM-System mehrere Produktseiten einzeln öffnen und Daten manuell in eine Excel-Tabelle übertragen.
- Das kostet Zeit, ist fehleranfällig und die Daten veralten schnell.
- Nicht jeder hat Zugang zum PIM-System oder kennt sich damit aus.

**Mit diesem Tool:**
- Ein Vergleich von 5 Produkten dauert **weniger als eine Minute**.
- Die Daten kommen immer **direkt aus dem PIM** — also immer aktuell, nie veraltet.
- Die Anzeige ist übersichtlich aufbereitet: deutsche Bezeichnungen, lesbare Einheiten, Produktbilder.
- **Jeder im Unternehmen** kann es nutzen — kein PIM-Wissen nötig.
- Besonders nützlich für: Vertrieb, Produktmanagement, Messeplanung, technische Dokumentation.

---

## Technischer Überblick (für Admins)

| Komponente | Technologie |
|---|---|
| Weboberfläche | PHP 8.2 + Apache (Docker) |
| Datenbank | Zentrale MariaDB über `amada-db-network` |
| PIM-Anbindung | Akeneo REST API (OAuth2) |
| Deployment | Docker / Portainer Stack |

### Umgebungsvariablen (`.env`)

```
APP_PORT=8085

# Zentrale MariaDB
DB_HOST=amada-db-mariadb11
DB_PORT=3306
DB_NETWORK=amada-db-network
DB_NAME=
DB_USER=
DB_PASSWORD=

# Akeneo PIM API
PIM_API_BASE_URL=https://192.168.5.4/api/rest/v1
PIM_API_USERNAME=
PIM_API_PASSWORD=
PIM_CLIENT_ID=
PIM_CLIENT_SECRET=
PIM_TLS_INSECURE=true

# Produktbilder
PIM_MEDIA_BASE_URL=https://pim.amada.de
PIM_MEDIA_CACHE=thumbnail_small
PIM_IMAGE_ATTRS=picture,filename_picture_perspective

# Admin-Bereich (PIM-Familien konfigurieren) – nur true für Admins
ADMIN_ENABLED=false
```

### Deployment via Portainer

1. Neuen Stack anlegen → Repository-URL eintragen
2. Automatic Updates aktivieren (aktualisiert sich bei jedem Git-Push automatisch)
3. Umgebungsvariablen mit echten Werten befüllen
4. „Deploy the stack" klicken

### Datenbank-Migration

Beim ersten Start muss die Tabelle `pim_family_config` einmalig angelegt werden.
Das SQL dazu liegt in `database/init/01_pim_family_config.sql` und kann direkt in phpMyAdmin ausgeführt werden.
