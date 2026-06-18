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
function unitAbbr(?string $unit): string {
    if ($unit === null || $unit === '') {
        return '';
    }
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

/** Label aus Akeneo labels-Array (Locale-Fallback). */
function pimLabel(array $labels, string $fallback = ''): string {
    if (isset($labels[PIM_LOCALE]) && $labels[PIM_LOCALE] !== '') {
        return $labels[PIM_LOCALE];
    }
    if (isset($labels['de_DE']) && $labels['de_DE'] !== '') {
        return $labels['de_DE'];
    }
    $first = reset($labels);
    if (is_string($first) && $first !== '') {
        return $first;
    }
    return $fallback;
}

/**
 * Option-Labels eines Simple-/Multi-Select-Attributs (code => Anzeigename).
 */
function getAkeneoAttributeOptionLabels(string $attrCode): array {
    static $cache = [];

    if ($attrCode === '') {
        return [];
    }
    if (isset($cache[$attrCode])) {
        return $cache[$attrCode];
    }

    $labels      = [];
    $accessToken = getAccessToken();
    $page        = 1;
    $limit       = 100;

    while (true) {
        $response = apiGet(
            API_BASE_URL . '/attributes/' . urlencode($attrCode) . "/options?limit={$limit}&page={$page}",
            $accessToken
        );

        if ($response === null || isset($response['code'])) {
            break;
        }

        foreach ($response['_embedded']['items'] ?? [] as $option) {
            $labels[$option['code']] = pimLabel($option['labels'] ?? [], $option['code']);
        }

        $items = $response['_embedded']['items'] ?? [];
        if (!isset($response['_links']['next']) || count($items) < $limit) {
            break;
        }
        $page++;
    }

    $cache[$attrCode] = $labels;
    return $labels;
}

/**
 * Option-Code in Anzeigename auflösen (mehrere Attribute probieren).
 */
function resolveOptionCodeLabel(string $optionCode, ?array $attrCodes = null): ?string {
    if ($optionCode === '') {
        return null;
    }

    $attrCodes = $attrCodes ?? array_values(array_unique(array_filter([
        PIM_ARTICLE_NAME_ATTR,
        'product_name',
    ])));

    static $optionCache = [];
    foreach ($attrCodes as $attrCode) {
        if (!isset($optionCache[$attrCode])) {
            $optionCache[$attrCode] = getAkeneoAttributeOptionLabels($attrCode);
        }
        $label = $optionCache[$attrCode][$optionCode] ?? null;
        if ($label !== null && $label !== '' && $label !== $optionCode) {
            return $label;
        }
    }

    return null;
}

function looksLikeProductNameOptionCode(string $value): bool {
    return str_starts_with($value, 'product_name_');
}

/**
 * Ersetzt Option-Codes in [display, raw] durch übersetzte Labels (raw bleibt der Code).
 */
function applyOptionLabels(array $value, array $optionLabels): array {
    if ($value['raw'] === null || $value['raw'] === '' || empty($optionLabels)) {
        return $value;
    }

    $raw = $value['raw'];

    if (is_string($raw) && str_contains($raw, ',')) {
        $parts    = array_map('trim', explode(',', $raw));
        $displays = array_map(fn($p) => $optionLabels[$p] ?? $p, $parts);
        return ['display' => implode(', ', $displays), 'raw' => $raw];
    }

    if (is_string($raw) && isset($optionLabels[$raw])) {
        return ['display' => $optionLabels[$raw], 'raw' => $raw];
    }

    return $value;
}

/**
 * Extrahiert Attributwert mit Option-Label-Auflösung (Fallback-Kette).
 */
function extractAttrValueFirstWithOptions(array $entity, string $envConstant, array $optionLabels = []): array {
    $val = extractAttrValueFirst($entity, $envConstant);
    return applyOptionLabels($val, $optionLabels);
}

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
        $disp = formatAmount((string)$rawData['amount']);
        $unit = $rawData['unit'] ?? $entry['unit'] ?? null;
        if ($unit !== null && $unit !== '') {
            $disp .= ' ' . unitAbbr((string)$unit);
        }
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
        $labelA = pimLabel($a['labels'] ?? [], $a['code']);
        $labelB = pimLabel($b['labels'] ?? [], $b['code']);
        return strcasecmp($labelA, $labelB);
    });

    return $allFamilies;
}

/** Familien-Code => Anzeigename (alle Akeneo-Familien). */
function getAkeneoFamilyLabelMap(): array {
    $map = [];
    foreach (getAkeneoFamilies() as $family) {
        $map[$family['code']] = pimLabel($family['labels'] ?? [], $family['code']);
    }
    return $map;
}

/**
 * Assoziationstyp-Code => Anzeigename (Locale-Fallback).
 */
function getAkeneoAssociationTypeLabels(): array {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $labels      = [];
    $accessToken = getAccessToken();
    $page        = 1;
    $limit       = 100;

    while (true) {
        $response = apiGet(API_BASE_URL . "/association-types?limit={$limit}&page={$page}", $accessToken);
        if ($response === null || isset($response['code'])) {
            break;
        }

        foreach ($response['_embedded']['items'] ?? [] as $item) {
            $labels[$item['code']] = pimLabel($item['labels'] ?? [], $item['code']);
        }

        $items = $response['_embedded']['items'] ?? [];
        if (!isset($response['_links']['next']) || count($items) < $limit) {
            break;
        }
        $page++;
    }

    $cache = $labels;
    return $labels;
}

/**
 * Verknüpfte Produkte und Produktmodelle einer Assoziation sammeln.
 *
 * @return array<int, array{kind: string, code: string}>
 */
function collectAssociationItems(array $assocData): array {
    $items = [];

    foreach ($assocData['products'] ?? [] as $code) {
        if ($code !== '') {
            $items[] = ['kind' => 'product', 'code' => (string)$code];
        }
    }

    foreach ($assocData['product_models'] ?? [] as $code) {
        if ($code !== '') {
            $items[] = ['kind' => 'product_model', 'code' => (string)$code];
        }
    }

    return $items;
}

/**
 * Artikelnamen für Produkt-Identifiers (Batch).
 *
 * @return array<string, string> identifier => Anzeigename
 */
function getArticleNamesForProducts(array $identifiers): array {
    if (empty($identifiers)) {
        return [];
    }

    $names       = [];
    $accessToken = getAccessToken();

    foreach (array_chunk(array_unique($identifiers), 100) as $chunk) {
        $search = json_encode(['identifier' => [['operator' => 'IN', 'value' => $chunk]]]);
        $resp   = apiGet(API_BASE_URL . '/products?search=' . urlencode($search) . '&limit=100', $accessToken);

        foreach ($resp['_embedded']['items'] ?? [] as $product) {
            $ident = $product['identifier'];
            $names[$ident] = extractArticleName($product) ?? $ident;
        }
    }

    foreach (array_unique($identifiers) as $ident) {
        if (!isset($names[$ident])) {
            $names[$ident] = $ident;
        }
    }

    return $names;
}

/**
 * Artikelnamen für Produktmodell-Codes.
 *
 * @return array<string, string> modelCode => Anzeigename
 */
function getArticleNamesForProductModels(array $modelCodes): array {
    $names = [];

    foreach (array_unique($modelCodes) as $code) {
        if ($code === '') {
            continue;
        }
        $model = getAkeneoProductModel($code);
        if ($model) {
            $model = enrichProductModelWithAncestors($model);
        }
        $names[$code] = extractArticleName($model ?? []) ?? extractProductName($model ?? []) ?? $code;
    }

    return $names;
}

/**
 * Baujahr-Angaben aus dem Serien-Produkt der zugeordneten PIM-Kategorie.
 * Kategorie-Code = Identifier des Serie-Produkts (Familie series).
 *
 * @return array{buildYear: array, builtUntil: array}
 */
function getSeriesBuildInfoForProduct(array $product): array {
    $empty = [
        'buildYear'  => ['display' => '–', 'raw' => null],
        'builtUntil' => ['display' => '–', 'raw' => null],
    ];

    $categories = $product['categories'] ?? [];
    if (empty($categories)) {
        return $empty;
    }

    static $cache = [];
    $seriesAttrs = [PIM_BUILD_YEAR_ATTR, PIM_BUILT_UNTIL_ATTR];

    foreach ($categories as $categoryCode) {
        if ($categoryCode === '') {
            continue;
        }

        if (!array_key_exists($categoryCode, $cache)) {
            $seriesProduct = getAkeneoProduct($categoryCode, $seriesAttrs);
            if (!$seriesProduct) {
                $cache[$categoryCode] = $empty;
            } else {
                $buildYear  = extractAttrValue($seriesProduct, PIM_BUILD_YEAR_ATTR);
                $builtUntil = extractAttrValue($seriesProduct, PIM_BUILT_UNTIL_ATTR);
                if ($buildYear['raw'] === null && $builtUntil['raw'] === null) {
                    $cache[$categoryCode] = $empty;
                } else {
                    $cache[$categoryCode] = [
                        'buildYear'  => $buildYear,
                        'builtUntil' => $builtUntil,
                    ];
                }
            }
        }

        $info = $cache[$categoryCode];
        if (($info['buildYear']['raw'] ?? null) !== null || ($info['builtUntil']['raw'] ?? null) !== null) {
            return $info;
        }
    }

    return $empty;
}

/** Baujahr (raw) eines Produkts über die Serien-Kategorie. */
function getProductBuildYearRaw(array $product): ?float {
    $raw = getSeriesBuildInfoForProduct($product)['buildYear']['raw'] ?? null;
    return ($raw !== null && $raw !== '') ? (float)$raw : null;
}

function compareBuildYearDesc(?float $yearA, ?float $yearB, string $fallbackA = '', string $fallbackB = ''): int {
    if ($yearA !== null && $yearB !== null) {
        return $yearB <=> $yearA;
    }
    if ($yearA !== null) {
        return -1;
    }
    if ($yearB !== null) {
        return 1;
    }
    return strcasecmp($fallbackA, $fallbackB);
}

/** Produkte nach Baujahr absteigend (ohne Baujahr ans Ende). */
function sortProductsByBuildYearDesc(array $products): array {
    usort($products, fn($a, $b) => compareBuildYearDesc(
        getProductBuildYearRaw($a),
        getProductBuildYearRaw($b),
        $a['identifier'] ?? '',
        $b['identifier'] ?? ''
    ));
    return $products;
}

/** SKU-Liste für Vergleichstabellen nach Baujahr absteigend ordnen. */
function sortSkusByBuildYearDesc(array $skus, array $seriesBuildInfo): array {
    usort($skus, function ($a, $b) use ($seriesBuildInfo) {
        $rawA = $seriesBuildInfo[$a]['buildYear']['raw'] ?? null;
        $rawB = $seriesBuildInfo[$b]['buildYear']['raw'] ?? null;
        $yearA = ($rawA !== null && $rawA !== '') ? (float)$rawA : null;
        $yearB = ($rawB !== null && $rawB !== '') ? (float)$rawB : null;
        return compareBuildYearDesc($yearA, $yearB, $a, $b);
    });
    return $skus;
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
 * Lokalisierten Textwert eines Attributs extrahieren.
 */
function extractLocalizedAttr(array $entity, string $attrCode, ?string $locale = null): ?string {
    $locale  = $locale ?? PIM_LOCALE;
    $entries = $entity['values'][$attrCode] ?? [];
    if (empty($entries)) {
        return null;
    }

    foreach ($entries as $val) {
        if (($val['locale'] ?? null) === $locale && is_string($val['data'] ?? null) && $val['data'] !== '') {
            return $val['data'];
        }
    }

    $entry = pickValueEntry($entries);
    if ($entry && is_string($entry['data'] ?? null) && $entry['data'] !== '') {
        return $entry['data'];
    }

    return null;
}

/**
 * Lokalisierten Attributwert inkl. Option-Label (z. B. Select artikelname) zurückgeben.
 */
function resolveLocalizedAttrDisplay(array $entity, string $attrCode, ?string $locale = null): ?string {
    $raw = extractLocalizedAttr($entity, $attrCode, $locale);
    if ($raw === null || $raw === '') {
        return null;
    }

    if (!looksLikeProductNameOptionCode($raw)) {
        return $raw;
    }

    return resolveOptionCodeLabel($raw, [$attrCode, 'product_name']) ?? $raw;
}

/**
 * Artikelname (de) — Fallback auf product_name.
 */
function extractArticleName(array $entity, ?string $locale = null): ?string {
    $locale     = $locale ?? PIM_LOCALE;
    $fallbackId = $entity['identifier'] ?? $entity['code'] ?? null;

    $articleRaw = extractLocalizedAttr($entity, PIM_ARTICLE_NAME_ATTR, $locale);
    if ($articleRaw !== null && !looksLikeProductNameOptionCode($articleRaw)) {
        return $articleRaw;
    }

    if ($articleRaw !== null) {
        $label = resolveOptionCodeLabel($articleRaw);
        if ($label !== null) {
            return $label;
        }
    }

    $productName = extractProductName($entity, $locale);
    if ($productName !== null && $productName !== '' && !looksLikeProductNameOptionCode($productName)) {
        return $productName;
    }

    if ($fallbackId !== null && $fallbackId !== '') {
        return $fallbackId;
    }

    return $articleRaw ?? $productName;
}

/**
 * Deutsche Produktbezeichnung aus values extrahieren.
 */
function extractProductName(array $entity, ?string $locale = null): ?string {
    $locale = $locale ?? PIM_LOCALE;
    $raw    = extractLocalizedAttr($entity, 'product_name', $locale);
    if ($raw === null || $raw === '') {
        return null;
    }

    if (looksLikeProductNameOptionCode($raw)) {
        return resolveOptionCodeLabel($raw, ['product_name', PIM_ARTICLE_NAME_ATTR]) ?? $raw;
    }

    return $raw;
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

    $activeProducts   = sortProductsByBuildYearDesc($activeProducts);
    $disabledProducts = sortProductsByBuildYearDesc($disabledProducts);

    return ['active' => $activeProducts, 'disabled' => $disabledProducts];
}

/**
 * Produkt inkl. geerbter Werte aus Parent-Modell-Kette (ohne erneuten Produkt-GET).
 */
function mergeProductWithAncestorValues(array $product, array $onlyAttrs = []): array {
    $entities = [];
    $parentCode = $product['parent'] ?? null;
    if ($parentCode) {
        $entities = getProductModelAncestorChain($parentCode, $onlyAttrs);
    }
    $entities[] = $product;
    $product['values'] = mergeEntityValues($entities);
    return $product;
}

/**
 * Varianten nach Länge, dann Artikelnummer sortieren.
 */
function sortBendingVariantProducts(array $products): array {
    usort($products, function ($a, $b) {
        $lenA = extractAttrValueFirst($a, PIM_BENDING_LENGTH_ATTRS)['raw'];
        $lenB = extractAttrValueFirst($b, PIM_BENDING_LENGTH_ATTRS)['raw'];
        if ($lenA !== null && $lenB !== null && is_numeric($lenA) && is_numeric($lenB)) {
            return (float)$lenA <=> (float)$lenB;
        }
        return strcasecmp($a['identifier'] ?? '', $b['identifier'] ?? '');
    });
    return $products;
}

/**
 * Interne paginierte Abfrage: Produkte zu einem Parent-Modell.
 */
function fetchProductsByParent(string $parentCode, array $onlyAttrs = []): array {
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
            return [];
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

    return $allProducts;
}

/**
 * Holt alle Varianten-Produkte zu einem Produktmodell (parent = Modellcode).
 * Fallback ohne Attribut-Filter, wenn ungültige Attribute einen API-Fehler auslösen.
 */
function getAkeneoProductsByParent(string $parentCode, array $onlyAttrs = []): array {
    if ($parentCode === '') {
        return [];
    }

    $products = fetchProductsByParent($parentCode, $onlyAttrs);

    if (empty($products) && !empty($onlyAttrs) && getLastApiError()) {
        $products = fetchProductsByParent($parentCode, []);
    }

    return sortBendingVariantProducts($products);
}
