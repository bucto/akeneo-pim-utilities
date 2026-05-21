<?php
// --- ZUGANGSDATEN AUS DEINER BESTEHENDEN API (via Docker) ---
define('API_BASE_URL',  getenv('API_BASE_URL')  ?: 'https://dein-standard-pim.de');
define('TOKEN_URL',     getenv('TOKEN_URL')     ?: 'https://dein-standard-pim.de/api/oauth/v1/token');
define('API_USERNAME',  getenv('API_USERNAME')  ?: 'admin');
define('API_PASSWORD',  getenv('API_PASSWORD')  ?: 'standard_passwort');
define('CLIENT_ID',     getenv('CLIENT_ID')     ?: 'fallback_id');
define('CLIENT_SECRET', getenv('CLIENT_SECRET') ?: 'fallback_secret');

// --- INTERNE DATENBANK-VERBINDUNG (Docker MariaDB) ---
define('DB_HOST', getenv('DB_HOST')     ?: 'internal_db');
define('DB_NAME', getenv('DB_NAME')     ?: 'akeneo_utilities_db');
define('DB_USER', getenv('DB_USER')     ?: 'akeneo_user');
define('DB_PASS', getenv('DB_PASSWORD') ?: 'user_passwort_hier');