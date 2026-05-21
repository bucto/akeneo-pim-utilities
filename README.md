# Akeneo PIM Utilities 🚀

Ein internes Full-Stack-Toolpaket zur Optimierung, Validierung und zum Vergleich von Produktdaten aus dem Akeneo PIM. Die Anwendung basiert auf einem PHP-Frontend/Backend, einer MariaDB-Datenbank und wird vollständig über Docker orchestriert.

## 📦 System-Architektur

Das Projekt ist in drei Microservices unterteilt, die über ein internes Docker-Netzwerk miteinander kommunizieren:
1. **Web Frontend (`web_frontend`):** Die PHP-Applikation (Apache), die die Benutzeroberfläche bereitstellt und Logiken ausführt.
2. **Datenbank (`internal_db`):** Eine MariaDB-Datenbank zur internen Speicherung von Dumps, Logs und Konfigurationen.
3. **phpMyAdmin (`phpmyadmin`):** Ein visuelles Datenbank-Dashboard zur einfachen Verwaltung der SQL-Tabellen.

---

## 📂 Repository-Struktur

```text
akeneo-pim-utilities/
│
├── docker-compose.yml     # Hauptkonfiguration für Docker / Portainer Stacks
├── README.md              # Projektdokumentation
│
└── app/                   # Das PHP-Anwendungsverzeichnis (Frontend & Logik)
    ├── Dockerfile         # Bauanleitung für das PHP-Apache-Image
    ├── config.php         # Dynamische Konfigurationsdatei (liest Umgebungsvariablen)
    ├── index.php          # Startseite der Anwendung
    └── ...                # Weitere PHP- & CSS-Dateien (z.B. Vergleicher, Masken)
	
	Variable,Beschreibung,Beispielwert / Fallback
PIM_API_URL,Die vollständige URL zu eurer Akeneo-Instanz,https://ihr-pim-system.com
PIM_CLIENT_ID,Akeneo API Client-ID (aus den PIM-Einstellungen),3_xxxxxx...
PIM_CLIENT_SECRET,Akeneo API Client-Secret,secret_xxxxxx...
PIM_USERNAME,API-Benutzername im Akeneo,api_integration_user
PIM_PASSWORD,API-Passwort des Akeneo-Benutzers,super-safe-pim-password


Variable,Beschreibung,Beispielwert / Fallback
PIM_API_URL,Die vollständige URL zu eurer Akeneo-Instanz,https://ihr-pim-system.com
PIM_CLIENT_ID,Akeneo API Client-ID (aus den PIM-Einstellungen),3_xxxxxx...
PIM_CLIENT_SECRET,Akeneo API Client-Secret,secret_xxxxxx...
PIM_USERNAME,API-Benutzername im Akeneo,api_integration_user
PIM_PASSWORD,API-Passwort des Akeneo-Benutzers,super-safe-pim-password



🚀 Inbetriebnahme / Deployment
Methode A: Lokale Entwicklung (PC)
Klone das Repository auf deinen Computer.

Erstelle im Hauptverzeichnis eine Datei namens .env (wird von Git ignoriert) und fülle sie mit den echten Zugangsdaten:

Code-Snippet
PIM_API_URL=[https://mein-pim.de](https://mein-pim.de)
PIM_CLIENT_ID=deine_id
PIM_CLIENT_SECRET=dein_secret
PIM_USERNAME=admin
PIM_PASSWORD=geheim
DB_ROOT_PASSWORD=db_root_safe
DB_PASSWORD=db_user_safe
Starte das Projekt über das Terminal im Hauptverzeichnis:

Bash
docker compose up --build -d
Die Services sind nun wie folgt erreichbar:

PHP-Anwendung: http://localhost:8085

phpMyAdmin: http://localhost:8086

Methode B: Server-Deployment via Portainer (Empfohlen)
Erstelle in Portainer einen neuen Stack.

Wähle Repository als Build-Methode und gib die URL dieses GitHub-Repos an.

Aktiviere Automatic Updates, damit sich der Container bei jedem Git-Push automatisch aktualisiert.

Scroll nach unten zu Environment variables und füge alle oben aufgelisteten Variablen mit den echten Produktionsdaten hinzu.

Klicke auf Deploy the stack. Portainer baut das PHP-Image im Hintergrund eigenständig auf.

🛠️ Wichtige Entwicklungshinweise
Datenbank-Host: Innerhalb des PHP-Codes darf für die DB-Verbindung nicht localhost genutzt werden. Der Hostname lautet immer internal_db (entspricht dem Docker-Servicenamen).

Daten-Persistenz: Die SQL-Daten werden in einem Docker-Volume (db_data) gespeichert. Das bedeutet, deine importierten Daten bleiben auch dann erhalten, wenn die Container gestoppt oder aktualisiert werden.