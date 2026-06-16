<?php
include_once('config.php');

/**
 * Erstellt einen Stream-Kontext für HTTP-Requests an die Akeneo API.
 * Respektiert PIM_TLS_INSECURE für selbstsignierte Zertifikate.
 */
function makeApiContext(string $method, array $headers, string $body = ''): mixed {
    $opts = [
        'http' => [
            'method'        => $method,
            'header'        => $headers,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer'      => !TLS_INSECURE,
            'verify_peer_name' => !TLS_INSECURE,
        ],
    ];
    if ($body !== '') {
        $opts['http']['content'] = $body;
    }
    return stream_context_create($opts);
}

/**
 * Holt das Access Token vom Akeneo PIM (OAuth2 Password Grant).
 */
function getAccessToken(): string {
    $body = http_build_query([
        'grant_type'    => 'password',
        'username'      => API_USERNAME,
        'password'      => API_PASSWORD,
        'client_id'     => CLIENT_ID,
        'client_secret' => CLIENT_SECRET,
    ]);

    $context = makeApiContext('POST', ["Content-Type: application/x-www-form-urlencoded"], $body);
    $result  = @file_get_contents(TOKEN_URL, false, $context);

    if ($result === false) {
        die('Fehler: Die Token-URL konnte nicht erreicht werden (' . TOKEN_URL . '). Prüfe die Umgebungsvariablen.');
    }

    $response = json_decode($result, true);

    if ($response === null || !isset($response['access_token'])) {
        echo "<h3>API-Verbindungsfehler beim Token-Abruf</h3>";
        echo "<strong>URL:</strong> " . htmlspecialchars(TOKEN_URL) . "<br>";
        echo "<strong>Antwort von Akeneo:</strong> <pre>" . htmlspecialchars($result) . "</pre>";
        die();
    }

    return $response['access_token'];
}

/**
 * Führt einen authentifizierten GET-Request gegen die Akeneo API aus.
 */
function apiGet(string $url, string $accessToken): ?array {
    $context = makeApiContext('GET', [
        "Authorization: Bearer $accessToken",
        "Content-Type: application/json",
    ]);
    $result = @file_get_contents($url, false, $context);
    return $result !== false ? json_decode($result, true) : null;
}

/**
 * Extrahiert die erste Bild-URL aus den Produktwerten.
 * Gibt null zurück wenn kein Bild vorhanden.
 */
function extractProductImageUrl(array $product): ?string {
    $attrs = array_map('trim', explode(',', PIM_IMAGE_ATTRS));

    foreach ($attrs as $attr) {
        $filePath = $product['values'][$attr][0]['data'] ?? null;
        if ($filePath && is_string($filePath) && $filePath !== '') {
            return rtrim(PIM_MEDIA_BASE_URL, '/') . '/media/cache/' . PIM_MEDIA_CACHE . '/' . ltrim($filePath, '/');
        }
    }

    return null;
}

/**
 * Zahl ohne überflüssige Nachkomma-Nullen: "7480.0000" → "7480", "0.5000" → "0.5"
 */
function formatAmount(string $amount): string {
    if (!is_numeric($amount)) return $amount;
    return rtrim(rtrim(number_format((float)$amount, 4, '.', ''), '0'), '.');
}

/**
 * Akeneo-Einheitencode → lesbare Abkürzung.
 */
function unitAbbr(string $unit): string {
    static $map = [
        'MILLIMETER'            => 'mm',   'CENTIMETER'        => 'cm',
        'DECIMETER'             => 'dm',   'METER'             => 'm',
        'KILOMETER'             => 'km',   'INCH'              => 'in',
        'FOOT'                  => 'ft',
        'GRAM'                  => 'g',    'KILOGRAM'          => 'kg',
        'TON'                   => 't',    'POUND'             => 'lb',
        'WATT'                  => 'W',    'KILOWATT'          => 'kW',
        'MEGAWATT'              => 'MW',   'KILOVOLT_AMPERE'   => 'kVA',
        'JOULE'                 => 'J',    'KILOWATT_HOUR'     => 'kWh',
        'BAR'                   => 'bar',  'PASCAL'            => 'Pa',
        'KILOPASCAL'            => 'kPa',  'PSI'               => 'psi',
        'LITER'                 => 'l',    'MILLILITER'        => 'ml',
        'CUBIC_METER'           => 'm³',   'LITER_PER_MINUTE'  => 'l/min',
        'CUBIC_METER_PER_HOUR'  => 'm³/h',
        'METER_PER_SECOND'      => 'm/s',  'METER_PER_MINUTE'  => 'm/min',
        'MILLIMETER_PER_MINUTE' => 'mm/min','MILLIMETER_PER_SECOND' => 'mm/s',
        'KILOMETER_PER_HOUR'    => 'km/h',
        'HERTZ'                 => 'Hz',   'KILOHERTZ'         => 'kHz',
        'MEGAHERTZ'             => 'MHz',
        'CELSIUS'               => '°C',   'FAHRENHEIT'        => '°F',
        'KELVIN'                => 'K',
        'VOLT'                  => 'V',    'KILOVOLT'          => 'kV',
        'AMPERE'                => 'A',    'MILLIAMPERE'       => 'mA',
        'DEGREE'                => '°',
        'DECIBEL'               => 'dB',   'PERCENT'           => '%',
        'SQUARE_MILLIMETER'     => 'mm²',  'SQUARE_METER'      => 'm²',
    ];
    return $map[strtoupper($unit)] ?? strtolower($unit);
}

/**
 * Extrahiert einen einzelnen Attributwert aus einem Produkt als [display, raw].
 * Erkennt Maßattribute (amount/unit), Multi-Select-Arrays und einfache Werte.
 */
function extractAttrValue(array $product, string $attrCode): array {
    $entry = $product['values'][$attrCode][0] ?? null;
    if (!$entry) return ['display' => '–', 'raw' => null];

    $rawData = $entry['data'];

    // Maßattribut: {"amount": "6.0000", "unit": "MILLIMETER"}
    if (is_array($rawData) && array_key_exists('amount', $rawData)) {
        $raw  = (float)$rawData['amount'];
        $disp = formatAmount((string)$rawData['amount']) . ' ' . unitAbbr($rawData['unit']);
        return ['display' => $disp, 'raw' => $raw];
    }

    // Multi-Select: array von Strings
    if (is_array($rawData)) {
        return ['display' => implode(', ', $rawData), 'raw' => implode(',', $rawData)];
    }

    // Zahl mit separatem Unit-Feld (älteres Akeneo-Format)
    if (is_numeric($rawData)) {
        $raw  = (float)$rawData;
        $disp = formatAmount((string)$rawData);
        $unit = $entry['unit'] ?? '';
        if ($unit) $disp .= ' ' . unitAbbr($unit);
        return ['display' => $disp, 'raw' => $raw];
    }

    return ['display' => (string)$rawData, 'raw' => $rawData];
}

/**
 * Holt alle Produktfamilien aus Akeneo.
 */
function getAkeneoFamilies(): array {
    $accessToken = getAccessToken();
    $allFamilies = [];
    $page        = 1;
    $limit       = 100;

    while (true) {
        $response = apiGet(API_BASE_URL . "/families?limit={$limit}&page={$page}", $accessToken);

        if ($response === null || isset($response['code'])) break;

        $items = $response['_embedded']['items'] ?? [];
        if (empty($items)) break;

        $allFamilies = array_merge($allFamilies, $items);

        if (!isset($response['_links']['next']) || count($items) < $limit) break;

        $page++;
    }

    usort($allFamilies, function($a, $b) {
        $labelA = $a['labels']['de_DE'] ?? $a['code'];
        $labelB = $b['labels']['de_DE'] ?? $b['code'];
        return strcasecmp($labelA, $labelB);
    });

    return $allFamilies;
}

/**
 * Holt Produkte aus MEHREREN Familien in einem einzigen paginierten API-Call.
 * Lädt optional nur die angegebenen Attribute (drastisch kürzere Antworten).
 *
 * @param  array  $familyCodes    Familie-Codes
 * @param  array  $onlyAttrs      Wenn nicht leer: nur diese Attribut-Codes zurückgeben
 * @return array  Alle Produkte (flach, mit _imageUrl)
 */
function getAkeneoProductsByFamilies(array $familyCodes, array $onlyAttrs = []): array {
    if (empty($familyCodes)) return [];

    $accessToken  = getAccessToken();
    $allProducts  = [];
    $page         = 1;
    $limit        = 100;
    $searchParams = ['family' => [['operator' => 'IN', 'value' => $familyCodes]]];
    $searchQuery  = urlencode(json_encode($searchParams));
    $attrsParam   = empty($onlyAttrs) ? '' : ('&attributes=' . urlencode(implode(',', $onlyAttrs)));

    while (true) {
        $url      = API_BASE_URL . "/products?search={$searchQuery}&page={$page}&limit={$limit}{$attrsParam}";
        $response = apiGet($url, $accessToken);

        if ($response === null || isset($response['code'])) break;

        $items = $response['_embedded']['items'] ?? [];
        if (empty($items)) break;

        foreach ($items as &$product) {
            $product['_imageUrl'] = extractProductImageUrl($product);
        }
        unset($product);

        $allProducts = array_merge($allProducts, $items);

        if (!isset($response['_links']['next']) || count($items) < $limit) break;
        $page++;
    }

    return $allProducts;
}

/**
 * Holt Produkte basierend auf einer Produktfamilie, trennt sie nach Status
 * und ergänzt jeweils die erste Bild-URL.
 */
function getAkeneoProductsByFamily(string $familyCode): array {
    $accessToken = getAccessToken();
    $allProducts = [];
    $page        = 1;
    $limit       = 100;

    while (true) {
        $searchParams = ['family' => [['operator' => 'IN', 'value' => [$familyCode]]]];
        $url          = API_BASE_URL . "/products?search=" . urlencode(json_encode($searchParams))
                        . "&page=$page&limit=$limit";

        $response = apiGet($url, $accessToken);

        if ($response === null || isset($response['code'])) break;
        if (empty($response['_embedded']['items'])) break;

        $allProducts = array_merge($allProducts, $response['_embedded']['items']);

        if (!isset($response['_links']['next']) || count($response['_embedded']['items']) < $limit) break;

        $page++;
    }

    $activeProducts   = [];
    $disabledProducts = [];

    foreach ($allProducts as $product) {
        $product['_imageUrl'] = extractProductImageUrl($product);

        if (isset($product['enabled']) && $product['enabled'] === false) {
            $disabledProducts[] = $product;
        } else {
            $activeProducts[] = $product;
        }
    }

    usort($activeProducts,   fn($a, $b) => strcasecmp($a['identifier'], $b['identifier']));
    usort($disabledProducts, fn($a, $b) => strcasecmp($a['identifier'], $b['identifier']));

    return ['active' => $activeProducts, 'disabled' => $disabledProducts];
}
