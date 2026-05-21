<?php
// --- ZUGANGSDATEN FÜR DAS AKENEO PIM ---
// getenv() holt den Wert aus der Docker-Umgebung. 
// Wenn dort nichts definiert ist, wird der zweite Wert (Fallback) genutzt.

define('PIM_API_URL',      getenv('PIM_API_URL')      ?: 'https://dein-standard-pim.de');
define('PIM_CLIENT_ID',    getenv('PIM_CLIENT_ID')    ?: 'fallback_id_nur_fuer_lokal');
define('PIM_CLIENT_SECRET',getenv('PIM_CLIENT_SECRET')?: 'fallback_secret_nur_fuer_lokal');
define('PIM_USERNAME',     getenv('PIM_USERNAME')     ?: 'admin');
define('PIM_PASSWORD',     getenv('PIM_PASSWORD')     ?: 'standard_passwort');

// --- INTERNE DATENBANK-VERBINDUNG (Docker MariaDB) ---
define('DB_HOST', getenv('DB_HOST') ?: 'internal_db');
define('DB_NAME', getenv('DB_NAME') ?: 'akeneo_utilities_db');
define('DB_USER', getenv('DB_USER') ?: 'akeneo_user');
define('DB_PASS', getenv('DB_PASSWORD') ?: 'user_passwort_hier');