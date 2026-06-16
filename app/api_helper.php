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
