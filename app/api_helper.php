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
    if ($result === false) {
        $GLOBALS['_akeneo_last_api_error'] = 'HTTP-Anfrage fehlgeschlagen: ' . $url;
        return null;
    }

    $response = json_decode($result, true);
    if (is_array($response) && isset($response['code'])) {
        $GLOBALS['_akeneo_last_api_error'] = trim(
            ($response['message'] ?? 'API-Fehler') . ' [' . ($response['code'] ?? '') . ']'
        );
    }

    return $response;
}

/** Letzte API-Fehlermeldung (für Admin-Diagnose). */
function getLastApiError(): ?string {
    return $GLOBALS['_akeneo_last_api_error'] ?? null;
}

/** scope + locale für Produkt-/Modell-Abfragen. */
function pimContextQuery(): string {
    return '&scope=' . urlencode(PIM_CHANNEL) . '&locale=' . urlencode(PIM_LOCALE);
}

/**
 * Wählt den passenden Wert-Eintrag für Kanal/Locale (Priorität wie amada-exponate).
 */
function pickValueEntry(array $entries): ?array {
    if (empty($entries)) {
        return null;
    }

    $locale = PIM_LOCALE;
    $scope  = PIM_CHANNEL;
    $checks = [
        fn($e) => ($e['scope'] ?? null) === $scope && ($e['locale'] ?? null) === $locale,
        fn($e) => ($e['scope'] ?? null) === $scope && ($e['locale'] ?? null) === null,
        fn($e) => ($e['scope'] ?? null) === null && ($e['locale'] ?? null) === $locale,
        fn($e) => ($e['scope'] ?? null) === null && ($e['locale'] ?? null) === null,
    ];

    foreach ($checks as $match) {
        foreach ($entries as $entry) {
            if ($match($entry)) {
                return $entry;
            }
        }
    }

    return $entries[0];
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
/**
 * Probiert mehrere Attribut-Codes der Reihe nach (Fallback-Kette aus config/env).
 */
function extractAttrValueFirst(array $entity, string $envConstant): array {
    $codes = array_values(array_filter(array_map('trim', explode(',', $envConstant))));
    foreach ($codes as $code) {
        $val = extractAttrValue($entity, $code);
        if ($val['raw'] !== null && $val['raw'] !== '') {
            return $val;
        }
    }
    return ['display' => '–', 'raw' => null];
}

function extractAttrValue(array $product, string $attrCode): array {
    $entry = pickValueEntry($product['values'][$attrCode] ?? []);
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
 * Schulterradius 1V und/oder 2V zu einer Anzeige zusammenfassen.
 */
function extractBendingShoulderRadius(array $entity): array {
    $r1 = extractAttrValue($entity, 'bendingtool_die_1v_shoulder_radius');
    $r2 = extractAttrValue($entity, 'bendingtool_die_2v_shoulder_radius');

    if ($r1['raw'] !== null && $r2['raw'] !== null) {
        return [
            'display' => $r1['display'] . ' / ' . $r2['display'],
            'raw'     => $r1['raw'] . ',' . $r2['raw'],
        ];
    }

    if ($r1['raw'] !== null) {
        return $r1;
    }

    if ($r2['raw'] !== null) {
        return $r2;
    }

    return extractAttrValueFirst($entity, PIM_BENDING_RADIUS_ATTRS);
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
        $url      = API_BASE_URL . "/products?search={$searchQuery}&page={$page}&limit={$limit}"
                  . pimContextQuery() . $attrsParam;
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
 * Holt Produktmodelle aus MEHREREN Familien in einem paginierten API-Call.
 * Optional nur Root-Modelle (parent leer) und nur bestimmte Attribute.
 *
 * @param  array  $familyCodes
 * @param  array  $onlyAttrs
 * @param  bool   $rootOnly  Nur Modelle ohne Parent (keine Unter-Modelle)
 * @return array
 */
function getAkeneoProductModelsByFamilies(
    array $familyCodes,
    array $onlyAttrs = [],
    bool $rootOnly = false,
    array $attributeSearch = []
): array {
    if (empty($familyCodes)) return [];

    $merged = fetchProductModelsForFamilies($familyCodes, $onlyAttrs, $rootOnly, $attributeSearch);
    if (!empty($merged)) {
        return $merged;
    }

    foreach ($familyCodes as $familyCode) {
        $chunk = fetchProductModelsForFamilies([$familyCode], $onlyAttrs, $rootOnly, $attributeSearch);
        $merged = array_merge($merged, $chunk);
    }

    return $merged;
}

/**
 * Interne Abfrage: Produktmodelle einer oder mehrerer Familien.
 */
function fetchProductModelsForFamilies(
    array $familyCodes,
    array $onlyAttrs,
    bool $rootOnly,
    array $attributeSearch = []
): array {
    if (empty($familyCodes)) return [];

    $accessToken = getAccessToken();
    $allModels   = [];
    $page        = 1;
    $limit       = 100;

    $searchParams = ['family' => [['operator' => 'IN', 'value' => array_values($familyCodes)]]];
    if ($rootOnly) {
        $searchParams['parent'] = [['operator' => 'EMPTY']];
    }

    foreach ($attributeSearch as $attrCode => $criteria) {
        $searchParams[$attrCode] = [[
            'operator' => $criteria['operator'] ?? 'CONTAINS',
            'value'    => $criteria['value'] ?? '',
            'locale'   => $criteria['locale'] ?? PIM_LOCALE,
            'scope'    => $criteria['scope'] ?? PIM_CHANNEL,
        ]];
    }

    $searchQuery = urlencode(json_encode($searchParams));
    $attrsParam  = empty($onlyAttrs) ? '' : ('&attributes=' . urlencode(implode(',', $onlyAttrs)));

    while (true) {
        $url      = API_BASE_URL . "/product-models?search={$searchQuery}&page={$page}&limit={$limit}"
                  . pimContextQuery() . $attrsParam;
        $response = apiGet($url, $accessToken);

        if ($response === null || isset($response['code'])) break;

        $items = $response['_embedded']['items'] ?? [];
        if (empty($items)) break;

        foreach ($items as &$model) {
            $model['_imageUrl'] = extractProductImageUrl($model);
        }
        unset($model);

        $allModels = array_merge($allModels, $items);

        if (!isset($response['_links']['next']) || count($items) < $limit) break;
        $page++;
    }

    return $allModels;
}

/**
 * Einzelnes Produktmodell per Code laden.
 */
function getAkeneoProductModel(string $code, array $onlyAttrs = []): ?array {
    static $cache = [];

    $cacheKey = $code . '|' . implode(',', $onlyAttrs);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $accessToken = getAccessToken();
    $attrsParam  = empty($onlyAttrs) ? '' : ('&attributes=' . urlencode(implode(',', $onlyAttrs)));
    $model       = apiGet(
        API_BASE_URL . '/product-models/' . urlencode($code) . '?' . ltrim(pimContextQuery(), '&') . $attrsParam,
        $accessToken
    );

    if (!$model || !isset($model['code'])) {
        $cache[$cacheKey] = null;
        return null;
    }

    $model['_imageUrl'] = extractProductImageUrl($model);
    $cache[$cacheKey] = $model;
    return $model;
}

/**
 * Einzelnes Produkt per SKU/Identifier laden (Kanal + Locale).
 */
function getAkeneoProduct(string $identifier, array $onlyAttrs = []): ?array {
    static $cache = [];

    $cacheKey = $identifier . '|' . implode(',', $onlyAttrs);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $accessToken = getAccessToken();
    $attrsParam  = empty($onlyAttrs) ? '' : ('&attributes=' . urlencode(implode(',', $onlyAttrs)));
    $product     = apiGet(
        API_BASE_URL . '/products/' . urlencode($identifier) . '?' . ltrim(pimContextQuery(), '&') . $attrsParam,
        $accessToken
    );

    if (!$product || !isset($product['identifier'])) {
        $cache[$cacheKey] = null;
        return null;
    }

    $product['_imageUrl'] = extractProductImageUrl($product);
    $cache[$cacheKey] = $product;
    return $product;
}

/**
 * Produktmodell-Kette von der Wurzel bis zum direkten Parent (max. 10 Ebenen).
 */
function getProductModelAncestorChain(string $modelCode, array $onlyAttrs = []): array {
    static $chainCache = [];

    $cacheKey = $modelCode . '|' . implode(',', $onlyAttrs);
    if (isset($chainCache[$cacheKey])) {
        return $chainCache[$cacheKey];
    }

    $chain   = [];
    $current = $modelCode;
    $guard   = 0;

    while ($current && $guard < 10) {
        $model = getAkeneoProductModel($current, $onlyAttrs);
        if (!$model) {
            break;
        }
        array_unshift($chain, $model);
        $current = $model['parent'] ?? null;
        $guard++;
    }

    $chainCache[$cacheKey] = $chain;
    return $chain;
}

/**
 * Produktmodell inkl. geerbter Werte aus übergeordneten Modell-Ebenen.
 */
function enrichProductModelWithAncestors(array $model, array $onlyAttrs = []): array {
    $entities = [];
    $parentCode = $model['parent'] ?? null;
    if ($parentCode) {
        $entities = getProductModelAncestorChain($parentCode, $onlyAttrs);
    }
    $entities[] = $model;
    $model['values'] = mergeEntityValues($entities);

    if (empty($model['_imageUrl'])) {
        foreach (array_reverse($entities) as $entity) {
            $imageUrl = $entity['_imageUrl'] ?? extractProductImageUrl($entity);
            if ($imageUrl) {
                $model['_imageUrl'] = $imageUrl;
                break;
            }
        }
    }

    return $model;
}

/**
 * Werte mehrerer Entitäten zusammenführen — spätere Ebenen überschreiben frühere.
 */
function mergeEntityValues(array $entities): array {
    $merged = [];

    foreach ($entities as $entity) {
        foreach ($entity['values'] ?? [] as $attrCode => $entries) {
            if (!empty($entries)) {
                $merged[$attrCode] = $entries;
            }
        }
    }

    return $merged;
}

/**
 * Produkt inkl. geerbter Werte aus allen übergeordneten Produktmodell-Ebenen.
 * Wichtig für mehrstufige Varianten-Hierarchien (z.B. Allgemein → Presskraft → Länge).
 */
function getAkeneoProductWithInheritedValues(string $identifier, array $onlyAttrs = []): ?array {
    $product = getAkeneoProduct($identifier, $onlyAttrs);
    if (!$product) {
        return null;
    }

    $entities = [];
    $parentCode = $product['parent'] ?? null;
    if ($parentCode) {
        $entities = getProductModelAncestorChain($parentCode, $onlyAttrs);
    }
    $entities[] = $product;

    $product['values'] = mergeEntityValues($entities);

    if (!$product['_imageUrl']) {
        foreach (array_reverse($entities) as $entity) {
            $imageUrl = $entity['_imageUrl'] ?? extractProductImageUrl($entity);
            if ($imageUrl) {
                $product['_imageUrl'] = $imageUrl;
                break;
            }
        }
    }

    return $product;
}

/**
 * Blatt-Modelle: Einträge die selbst kein Parent anderer Modelle sind.
 * Bei 2-stufiger Varianten-Hierarchie sind das die eigentlichen Werkzeugmodelle.
 */
function filterLeafProductModels(array $models): array {
    if (count($models) <= 1) {
        return $models;
    }

    $usedAsParent = [];
    foreach ($models as $model) {
        $parent = $model['parent'] ?? null;
        if ($parent) {
            $usedAsParent[$parent] = true;
        }
    }

    $leaves = array_values(array_filter(
        $models,
        fn($m) => !isset($usedAsParent[$m['code']])
    ));

    return !empty($leaves) ? $leaves : $models;
}

/**
 * Deutsche Produktbezeichnung aus values extrahieren.
 */
function extractProductName(array $entity, string $locale = 'de_DE'): ?string {
    $entries = $entity['values']['product_name'] ?? [];
    if (empty($entries)) {
        return null;
    }

    $entry = pickValueEntry($entries);
    if ($entry && is_string($entry['data'] ?? null)) {
        return $entry['data'];
    }

    foreach ($entries as $val) {
        if (($val['locale'] ?? null) === $locale && is_string($val['data'] ?? null)) {
            return $val['data'];
        }
    }

    return null;
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

/**
 * Holt alle Varianten-Produkte zu einem Produktmodell (parent = Modellcode).
 */
function getAkeneoProductsByParent(string $parentCode, array $onlyAttrs = []): array {
    if ($parentCode === '') {
        return [];
    }

    $accessToken  = getAccessToken();
    $allProducts  = [];
    $page         = 1;
    $limit        = 100;
    $searchParams = ['parent' => [['operator' => '=', 'value' => $parentCode]]];
    $searchQuery  = urlencode(json_encode($searchParams));
    $attrsParam   = empty($onlyAttrs) ? '' : ('&attributes=' . urlencode(implode(',', $onlyAttrs)));

    while (true) {
        $url      = API_BASE_URL . "/products?search={$searchQuery}&page={$page}&limit={$limit}"
                  . pimContextQuery() . $attrsParam;
        $response = apiGet($url, $accessToken);

        if ($response === null || isset($response['code'])) {
            break;
        }

        $items = $response['_embedded']['items'] ?? [];
        if (empty($items)) {
            break;
        }

        $allProducts = array_merge($allProducts, $items);

        if (!isset($response['_links']['next']) || count($items) < $limit) {
            break;
        }
        $page++;
    }

    usort($allProducts, function ($a, $b) {
        $lenA = extractAttrValueFirst($a, PIM_BENDING_LENGTH_ATTRS)['raw'];
        $lenB = extractAttrValueFirst($b, PIM_BENDING_LENGTH_ATTRS)['raw'];
        if ($lenA !== null && $lenB !== null && is_numeric($lenA) && is_numeric($lenB)) {
            return (float)$lenA <=> (float)$lenB;
        }
        return strcasecmp($a['identifier'] ?? '', $b['identifier'] ?? '');
    });

    return $allProducts;
}
