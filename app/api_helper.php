<?php
// Konfiguration einbinden
include('config.php');

/**
 * Holt das Access Token vom Akeneo PIM
 */
function getAccessToken() {
    $data = [
        'grant_type' => 'password',
        'username' => API_USERNAME,
        'password' => API_PASSWORD,
        'client_id' => CLIENT_ID,
        'client_secret' => CLIENT_SECRET
    ];

    $options = [
        'http' => [
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ];

    $context = stream_context_create($options);
    $result = @file_get_contents(TOKEN_URL, false, $context);

    if ($result === FALSE) {
        die('Fehler: Die Token-URL konnte nicht erreicht werden. Prüfe die IP-Adresse in Portainer.');
    }

    $response = json_decode($result, true);

    if ($response === null || !isset($response['access_token'])) {
        echo "<h3>API-Verbindungsfehler beim Token-Abruf</h3>";
        echo "<strong>Antwort von Akeneo:</strong> <pre>" . htmlspecialchars($result) . "</pre>";
        die();
    }

    return $response['access_token'];
}

/**
 * Holt alle Produktfamilien aus Akeneo für Schritt 1
 */
function getAkeneoFamilies() {
    $accessToken = getAccessToken();
    $url = API_BASE_URL . "/families?limit=100";

    $options = [
        'http' => [
            'header' => ["Authorization: Bearer $accessToken", "Content-Type: application/json"],
            'method' => 'GET',
            'ignore_errors' => true
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ];

    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    if ($result === FALSE) {
        die("Fehler beim Abrufen der Produktfamilien.");
    }

    $response = json_decode($result, true);
    return isset($response['_embedded']['items']) ? $response['_embedded']['items'] : [];
}

/**
 * Holt Produkte basierend auf einer Produktfamilie und trennt sie nach Status
 */
function getAkeneoProductsByFamily($familyCode) {
    $accessToken = getAccessToken();
    $allProducts = [];
    $page = 1;
    $limit = 100;

    while (true) {
        $searchParams = ['family' => [['operator' => 'IN', 'value' => [$familyCode]]]];
        $url = API_BASE_URL . "/products?search=" . urlencode(json_encode($searchParams)) . "&page=$page&limit=$limit";

        $options = [
            'http' => [
                'header' => ["Authorization: Bearer $accessToken", "Content-Type: application/json"],
                'method' => 'GET',
                'ignore_errors' => true
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ];

        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        if ($result === FALSE) {
            die("Fehler beim Abrufen der Produkte.");
        }

        $response = json_decode($result, true);

        if ($response === null || isset($response['code'])) {
            break;
        }

        if (empty($response['_embedded']['items'])) {
            break;
        }

        $allProducts = array_merge($allProducts, $response['_embedded']['items']);

        if (!isset($response['_links']['next']) || count($response['_embedded']['items']) < $limit) {
            break;
        }

        $page++;
    }

    // Sortierung und Aufteilung
    $activeProducts = [];
    $disabledProducts = [];

    foreach ($allProducts as $product) {
        if (isset($product['enabled']) && $product['enabled'] === false) {
            $disabledProducts[] = $product;
        } else {
            $activeProducts[] = $product;
        }
    }

    usort($activeProducts, function($a, $b) { return strcasecmp($a['identifier'], $b['identifier']); });
    usort($disabledProducts, function($a, $b) { return strcasecmp($a['identifier'], $b['identifier']); });

    return ['active' => $activeProducts, 'disabled' => $disabledProducts];
}