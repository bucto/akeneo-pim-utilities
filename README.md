# AMADA PIM Utilities

Weboberfläche zum **Vergleichen von AMADA-Produkten** und zum **Suchen von Abkantwerkzeugen** — Daten kommen live aus dem Akeneo PIM.

**Repository:** [github.com/bucto/akeneo-pim-utilities](https://github.com/bucto/akeneo-pim-utilities)

---

## Inhalt

- [Funktionen](#funktionen)
- [Architektur](#architektur)
- [Voraussetzungen](#voraussetzungen)
- [Schnellstart (Docker)](#schnellstart-docker)
- [Konfiguration](#konfiguration)
- [Datenbank einrichten](#datenbank-einrichten)
- [Deployment (Portainer)](#deployment-portainer)
- [Projektstruktur](#projektstruktur)
- [Sicherheit](#sicherheit)
- [Hinweise zur PIM-Datenstruktur](#hinweise-zur-pim-datenstruktur)

---

## Funktionen

### Startseite

Zwei Module:

| Modul | Beschreibung |
|---|---|
| **Produkt-Vergleich** | Produkte einer Familie auswählen und nebeneinander vergleichen |
| **Werkzeugfinder** | Abkant-Werkzeugmodelle filtern und Varianten (Längen) anzeigen |

### Produkt-Vergleich

Fünf Reiter (Tabs), Zuordnung der PIM-Familien konfigurierbar:

| Tab | Typische Inhalte |
|---|---|
| Maschinen | Laser-, Stanz- und Abkantmaschinen |
| Automation | Automatisierung, Beladesysteme |
| Zubehör | Optionales Zubehör |
| Stanzwerkzeuge | Werkzeuge für Stanzmaschinen |
| Abkantwerkzeuge | Werkzeuge für Abkantpressen |

Pro Familie werden Produkte mit Bild, Artikelnummer und Status (aktiv/deaktiviert) gelistet. Mehrere Artikel können verglichen werden:

- **Technische Daten** — Attribute mit Einheiten (`6500 mm`, `80 kg`, …), deutsche Labels für Select-Werte
- **Ausstattung & Verbindungen** — Assoziationen zwischen Produkten

> **Mehrstufige Produktmodelle:** Viele technische Werte liegen in Akeneo auf übergeordneten Modell-Ebenen (z. B. Allgemein → Presskraft → Länge). Der Technik-Vergleich lädt diese Werte automatisch aus der gesamten Parent-Kette mit.

### Werkzeugfinder

- Zeigt **Produktmodelle** (nicht jede Längenvariante einzeln)
- Filter: Suche, V-Öffnung, Winkel, Werkzeughöhe, Radius, Status, Serie
- **Klick auf ein Modell** → alle bestellbaren Artikel mit Längen
- 30-Minuten-Cache für schnelle Filterung

Familien werden aus der Datenbank (Tab „Abkantwerkzeuge“) oder per Fallback über Code-Präfix `bendingtool_*` geladen.

### Admin (`ADMIN_ENABLED=true`)

Seite `pim_family_settings.php`: PIM-Familien den Tabs zuweisen oder von der Anzeige ausschließen. Für normale Nutzer unsichtbar.

---

## Architektur

| Komponente | Technologie |
|---|---|
| Frontend / Backend | PHP 8.2, Apache |
| Container | Docker, `docker-compose.yml` |
| Konfiguration | Umgebungsvariablen (`.env`, nicht im Git) |
| Metadaten | MariaDB (`pim_family_config`) |
| Produktdaten | Akeneo REST API (OAuth2 Password Grant) |
| Deployment | Portainer Stack (Git-Anbindung) |

```
Browser → PHP/Apache (Container) → Akeneo PIM API
                  ↓
            MariaDB (Familien-Zuordnung)
```

---

## Voraussetzungen

- Docker & Docker Compose
- Erreichbares **Akeneo PIM** mit REST-API-Zugang (OAuth-Client)
- **MariaDB/MySQL** mit Tabelle `pim_family_config` (siehe unten)
- Externes Docker-Netzwerk zur DB (Standard: `amada-db-network`)
- Für Produktbilder: erreichbare **PIM-Medien-URL** (`PIM_MEDIA_BASE_URL`)

---

## Schnellstart (Docker)

```bash
git clone https://github.com/bucto/akeneo-pim-utilities.git
cd akeneo-pim-utilities
cp .env.example .env
# .env mit echten Werten befüllen (siehe Konfiguration)
docker compose up -d --build
```

App erreichbar unter: `http://localhost:8085` (Port über `APP_PORT` änderbar)

---

## Konfiguration

Vorlage: [`.env.example`](.env.example). **Niemals** die echte `.env` committen — sie steht in [`.gitignore`](.gitignore).

### Docker & Netzwerk

| Variable | Beschreibung | Beispiel |
|---|---|---|
| `APP_PORT` | Host-Port für die Weboberfläche | `8085` |
| `DB_NETWORK` | Externes Docker-Netzwerk zur DB | `amada-db-network` |

### MariaDB

| Variable | Beschreibung |
|---|---|
| `DB_HOST` | Hostname des DB-Containers/Servers |
| `DB_PORT` | Port (Standard `3306`) |
| `DB_NAME` | Datenbankname |
| `DB_USER` | Benutzer |
| `DB_PASSWORD` | Passwort |

### Akeneo PIM API

| Variable | Beschreibung |
|---|---|
| `PIM_API_BASE_URL` | Vollständiger REST-Pfad, z. B. `https://pim.example.de/api/rest/v1` |
| `PIM_API_USERNAME` | PIM-Benutzername |
| `PIM_API_PASSWORD` | PIM-Passwort |
| `PIM_CLIENT_ID` | OAuth-Client-ID |
| `PIM_CLIENT_SECRET` | OAuth-Client-Secret |
| `PIM_TLS_INSECURE` | `true` bei selbstsigniertem PIM-Zertifikat |
| `PIM_LOCALE` | Locale für Produktwerte (Standard `de_DE`) |
| `PIM_CHANNEL` | Akeneo-Kanal/Scope (Standard `ecommerce`) |

### Produktbilder

| Variable | Beschreibung |
|---|---|
| `PIM_MEDIA_BASE_URL` | Basis-URL für Medien (z. B. `https://pim.amada.de`) |
| `PIM_MEDIA_CACHE` | Thumbnail-Profil (`thumbnail_small`, …) |
| `PIM_IMAGE_ATTRS` | Kommagetrennte Bild-Attribut-Codes |

### Werkzeugfinder (Attribut-Codes)

Falls die Codes im PIM abweichen, per kommagetrennter Fallback-Kette anpassen:

| Variable | Standard-Fallbacks |
|---|---|
| `PIM_BENDING_HEIGHT_ATTRS` | `bendingtool_tool_height`, `bendingtool_die_height`, … |
| `PIM_BENDING_RADIUS_ATTRS` | `bendingtool_die_radius`, `bendingtool_radius`, … |
| `PIM_BENDING_SERIES_ATTRS` | `series`, `bendingtool_series` |
| `PIM_BENDING_LENGTH_ATTRS` | `bendingtool_tool_length`, `bendingtool_length`, … |

### Admin & Footer

| Variable | Beschreibung |
|---|---|
| `ADMIN_ENABLED` | `true` = Admin-Link und Einstellungsseite sichtbar |
| `APP_AUTHOR` | Name im Footer |
| `APP_REPO` | Repository-URL im Footer |
| `APP_GIT_BRANCH` | Branch für Revisions-Anzeige (Standard `main`) |
| `APP_REVISION` | Optionales manuelles Override der Revisionsnummer |
| `GITHUB_TOKEN` | Optional; bei Rate-Limits oder privatem Fork |

Die Revisionsnummer im Footer wird automatisch ermittelt (GitHub-API, Quellcode-Hash oder Build-Metadaten).

---

## Datenbank einrichten

Die App funktioniert ohne DB im Fallback-Modus (alle Familien sichtbar), für die Tab-Zuordnung wird die Tabelle benötigt.

**Neuinstallation:**

```sql
-- database/init/01_pim_family_config.sql
```

**Bestehende Installation (Migration):**

```sql
-- database/init/02_add_tool_categories.sql
```

SQL in phpMyAdmin oder per CLI ausführen. Danach im Admin die Familien den Tabs zuweisen.

---

## Deployment (Portainer)

1. **Stack anlegen** → Repository-URL: `https://github.com/bucto/akeneo-pim-utilities`
2. **Compose-Pfad:** `docker-compose.yml`
3. **Umgebungsvariablen** aus `.env.example` übernehmen und mit echten Werten füllen
4. Externes Netzwerk `amada-db-network` (oder `DB_NETWORK`) muss existieren
5. **Deploy the stack** — bei Updates: **Pull and redeploy** mit **Rebuild**

> Nach Code-Änderungen reicht ein Container-Neustart oft nicht — Stack **neu bauen**, damit PHP-Dateien und die Revisionsnummer aktualisiert werden.

### Automatische Updates

Portainer „Automatic updates“ kann bei Git-Push neu deployen. Secrets bleiben in den Stack-Umgebungsvariablen in Portainer — nicht im Git-Repository.

---

## Projektstruktur

```
akeneo-pim-utilities/
├── app/
│   ├── index.php                  # Startseite
│   ├── produkt_vergleich.php      # Produktliste & Tab-Navigation
│   ├── Vergleich_TechnischeDaten.php
│   ├── Vergleich_Austattung.php
│   ├── bendingtool_finder.php     # Werkzeugfinder
│   ├── bendingtool_finder_api.php # Varianten-JSON (AJAX)
│   ├── pim_family_settings.php    # Admin: Familien-Zuordnung
│   ├── api_helper.php             # Akeneo API, Wert-Extraktion
│   ├── db_helper.php              # MariaDB / pim_family_config
│   ├── common.php                 # Layout, Footer, Navigation
│   ├── config.php                 # Umgebungsvariablen
│   └── Dockerfile
├── database/init/                 # SQL-Schema & Migrationen
├── docker-compose.yml
├── .env.example                   # Vorlage (ohne Secrets)
└── README.md
```

---

## Sicherheit

Dieses Repository ist **öffentlich**. Enthalten sind **keine** Passwörter, Tokens oder Client-Secrets.

| ✅ Im Git | ❌ Nicht ins Git |
|---|---|
| `.env.example` (leere Platzhalter) | `.env` mit echten Werten |
| Code & SQL-Schema | PIM-/DB-Zugangsdaten |
| Docker-Konfiguration | `GITHUB_TOKEN` mit Schreibrechten |

**Empfehlungen:**

- Secrets nur in Portainer / Server-`.env` pflegen
- `ADMIN_ENABLED=false` in Produktion, nur bei Bedarf aktivieren
- OAuth-Client im PIM mit minimal nötigen Rechten
- `PIM_TLS_INSECURE=true` nur für interne PIM-Instanzen mit selbstsigniertem Zertifikat

---

## Hinweise zur PIM-Datenstruktur

### Einfache Produkte

Attribute liegen direkt am Produkt — Vergleich und Anzeige funktionieren ohne Besonderheiten.

### Mehrstufige Varianten (Product Models)

Akeneo-Hierarchie, z. B. bei Polyurethan-Pads oder Abkantwerkzeugen:

1. **Ebene 1** — Allgemeine Daten für alle Varianten  
2. **Ebene 2** — Variantenachse (z. B. Presskraft)  
3. **Ebene 3** — Bestellbare Artikel (z. B. Länge)

Der Technik-Vergleich und der Werkzeugfinder berücksichtigen diese Struktur (Parent-Kette / Modell- vs. Varianten-Ebene).

---

## Autor

Thomas Bücken — [github.com/bucto/akeneo-pim-utilities](https://github.com/bucto/akeneo-pim-utilities)
