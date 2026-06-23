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

// Produktbezeichnung unter Vergleichsbildern (Locale: PIM_LOCALE)
define('PIM_ARTICLE_NAME_ATTR', getenv('PIM_ARTICLE_NAME_ATTR') ?: 'artikelname');

// Ausstattungs-Vergleich: Assoziationstypen für Spalte „Verbindung“ (leer = alle)
define('PIM_ASSOC_VERBINDUNG_TYPES', getenv('PIM_ASSOC_VERBINDUNG_TYPES') ?: 'included_components');

// Technik-Vergleich: auszublendende Attribut-Codes (kommagetrennt)
define('PIM_COMPARE_EXCLUDE_ATTRS', getenv('PIM_COMPARE_EXCLUDE_ATTRS') ?: 'filename_picture_front,offer_id');

// Serie über Kategorie: Produkt-Identifier = Kategorie-Code
define('PIM_BUILD_YEAR_ATTR', getenv('PIM_BUILD_YEAR_ATTR') ?: 'build_year');
define('PIM_BUILT_UNTIL_ATTR', getenv('PIM_BUILT_UNTIL_ATTR') ?: 'was_built_until');
define('PIM_SERIES_FAMILY', getenv('PIM_SERIES_FAMILY') ?: 'series');
// Übergeordnete Kategorie für neu angelegte Serien (z. B. bending_tools, shearing_machines)
define('PIM_SERIES_CATEGORY_PARENT', getenv('PIM_SERIES_CATEGORY_PARENT') ?: 'master');
define('PIM_SERIES_NAME_ATTR', getenv('PIM_SERIES_NAME_ATTR') ?: 'series_name');
define('PIM_SERIES_PRODUCT_NAME_ATTR', getenv('PIM_SERIES_PRODUCT_NAME_ATTR') ?: 'product_name');

// --- Abkantwerkzeug-Attribute (kommagetrennte Fallback-Kette) ---
define('PIM_BENDING_HEIGHT_ATTRS', getenv('PIM_BENDING_HEIGHT_ATTRS') ?: 'tool_height');
define('PIM_BENDING_RADIUS_ATTRS', getenv('PIM_BENDING_RADIUS_ATTRS') ?: 'bendingtool_die_1v_shoulder_radius,bendingtool_die_2v_shoulder_radius');
define('PIM_BENDING_SERIES_ATTRS', getenv('PIM_BENDING_SERIES_ATTRS') ?: 'series_name');
define('PIM_BENDING_LENGTH_ATTRS', getenv('PIM_BENDING_LENGTH_ATTRS') ?: 'tool_length');
define('PIM_BENDING_SAP_ATTRS', getenv('PIM_BENDING_SAP_ATTRS') ?: 'sap_nummer');

// Werkzeugfinder: kommagetrennte Familien-Codes (leer = DB/Fallback bendingtool_*)
define('PIM_BENDING_FAMILIES', getenv('PIM_BENDING_FAMILIES') ?: '');
define('PIM_BENDING_SIZE_ATTRS', getenv('PIM_BENDING_SIZE_ATTRS') ?: 'bendingtool_die_1v_size,bendingtool_die_4v_size');
define('PIM_BENDING_ANGLE_ATTRS', getenv('PIM_BENDING_ANGLE_ATTRS') ?: 'bendingtool_die_1v_angle,bendingtool_die_4v_angle');
// Serie-Filter (z. B. afh) — leer = alle Serien
define('PIM_BENDING_SERIES_FILTER', getenv('PIM_BENDING_SERIES_FILTER') ?: 'afh');
