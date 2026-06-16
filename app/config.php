<?php
// --- AKENEO PIM API ---
// PIM_API_BASE_URL enthält bereits den vollen REST-Pfad, z.B. https://192.168.5.4/api/rest/v1
define('API_BASE_URL',  getenv('PIM_API_BASE_URL')  ?: 'https://dein-pim.de/api/rest/v1');

// Token-URL aus der API-Basis ableiten: /api/rest/v1 → /api/oauth/v1/token
define('TOKEN_URL', preg_replace('#/api/rest/v1$#', '', API_BASE_URL) . '/api/oauth/v1/token');

define('API_USERNAME',  getenv('PIM_API_USERNAME')  ?: '');
define('API_PASSWORD',  getenv('PIM_API_PASSWORD')  ?: '');
define('CLIENT_ID',     getenv('PIM_CLIENT_ID')     ?: '');
define('CLIENT_SECRET', getenv('PIM_CLIENT_SECRET') ?: '');
define('TLS_INSECURE',  filter_var(getenv('PIM_TLS_INSECURE') ?: 'true', FILTER_VALIDATE_BOOLEAN));

// --- ZENTRALE MARIADB (externes Docker-Netzwerk amada-db-network) ---
define('DB_HOST', getenv('DB_HOST') ?: 'amada-db-mariadb11');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: '');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASSWORD') ?: '');

// --- PIM PRODUKTBILDER ---
define('PIM_MEDIA_BASE_URL', getenv('PIM_MEDIA_BASE_URL') ?: 'https://pim.amada.de');
define('PIM_MEDIA_CACHE',    getenv('PIM_MEDIA_CACHE')    ?: 'thumbnail_small');
define('PIM_IMAGE_ATTRS',    getenv('PIM_IMAGE_ATTRS')    ?: 'picture,filename_picture_perspective');

// --- PIM KANAL & LOCALE (wie amada-exponate) ---
define('PIM_LOCALE',  getenv('PIM_LOCALE')  ?: 'de_DE');
define('PIM_CHANNEL', getenv('PIM_CHANNEL') ?: 'ecommerce');

// --- Abkantwerkzeug-Attribute (kommagetrennte Fallback-Kette) ---
define('PIM_BENDING_HEIGHT_ATTRS', getenv('PIM_BENDING_HEIGHT_ATTRS') ?: 'bendingtool_tool_height,bendingtool_die_height,bendingtool_height');
define('PIM_BENDING_RADIUS_ATTRS', getenv('PIM_BENDING_RADIUS_ATTRS') ?: 'bendingtool_die_radius,bendingtool_radius,bendingtool_die_1v_radius');
define('PIM_BENDING_SERIES_ATTRS', getenv('PIM_BENDING_SERIES_ATTRS') ?: 'series,bendingtool_series');
define('PIM_BENDING_LENGTH_ATTRS', getenv('PIM_BENDING_LENGTH_ATTRS') ?: 'bendingtool_tool_length,bendingtool_length,bendingtool_die_length');
