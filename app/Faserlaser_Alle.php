<?php
// Konfiguration einbinden
include('config.php'); // API-Konfiguration wird hier geladen

function getAccessToken($tokenUrl, $clientId, $clientSecret, $username, $password) {
    $data = [
        'grant_type' => 'password',
        'username' => $username,
        'password' => $password,
        'client_id' => $clientId,
        'client_secret' => $clientSecret
    ];

    $options = [
        'http' => [
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
        ],
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($tokenUrl, false, $context);

    if ($result === FALSE) {
        die('Fehler beim Abrufen des Access Tokens');
    }

    $response = json_decode($result, true);
    return $response['access_token'];
}

function getSKUsByFamily($baseUrl, $accessToken, $familyCode) {
    $allProducts = [];
    $page = 1;
    $limit = 100; // Anzahl der Produkte pro Seite

    while (true) {
        $searchParams = json_encode([
            'family' => [['operator' => 'IN', 'value' => [$familyCode]]]
        ]);
        $url = "$baseUrl/products?search=" . urlencode($searchParams) . "&page=$page&limit=$limit";

        $options = [
            'http' => [
                'header' => [
                    "Authorization: Bearer $accessToken",
                    "Content-Type: application/json"
                ],
                'method' => 'GET'
            ],
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        if ($result === FALSE) {
            die("Fehler beim Abrufen der Produkte für die Familie $familyCode.");
        }

        $response = json_decode($result, true);

        if (empty($response['_embedded']['items'])) {
            break;
        }

        foreach ($response['_embedded']['items'] as $product) {
            $allProducts[] = $product; // Alle Produkte, egal ob aktiviert oder deaktiviert
        }

        if (!isset($response['_links']['next'])) {
            break;
        }

        $page++;
    }

    return $allProducts;
}

// Workflow
$accessToken = getAccessToken(TOKEN_URL, CLIENT_ID, CLIENT_SECRET, API_USERNAME, API_PASSWORD);

// Produktfamilie definieren
$familyCode = 'fiber_laser_cutting_machine';

$products = getSKUsByFamily(API_BASE_URL, $accessToken, $familyCode);

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SKU Auswahl</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container">
    <h1>Wähle das gewünschte aus</h1>
    <form id="skuForm" action="#" method="get">
        <div class="checkbox-list">
            <?php
            if (!empty($products)) {
                $activeProducts = [];
                $disabledProducts = [];

                foreach ($products as $product) {
                    if (isset($product['enabled']) && $product['enabled'] === false) {
                        $disabledProducts[] = $product;
                    } else {
                        $activeProducts[] = $product;
                    }
                }

                usort($activeProducts, function($a, $b) {
                    return strcasecmp($a['identifier'], $b['identifier']);
                });

                usort($disabledProducts, function($a, $b) {
                    return strcasecmp($a['identifier'], $b['identifier']);
                });

                foreach ($activeProducts as $product) {
                    echo "<label><input type='checkbox' class='sku-checkbox' name='skus[]' value='" . htmlspecialchars($product['identifier']) . "'> " . htmlspecialchars($product['identifier']) . "</label>";
                }

                foreach ($disabledProducts as $product) {
                    echo "<label class='disabled'><input type='checkbox' class='sku-checkbox disabled-checkbox' name='skus[]' value='" . htmlspecialchars($product['identifier']) . "'> <span class='disabled-text'>" . htmlspecialchars($product['identifier']) . " (Deaktiviert)</span></label>";
                }
            } else {
                echo "<p>Keine Produkte in der angegebenen Familie gefunden.</p>";
            }
            ?>
        </div>
        <input type="submit" value="Vergleich der Ausstattung" name="action" formaction="Vergleich_Austattung.php">
        <input type="submit" value="Vergleich der Technische Daten" name="action" formaction="Vergleich_TechnischeDaten.php">
    </form>
    <script>
    document.getElementById('skuForm').onsubmit = function(event) {
        var selectedSKUs = [];
        var checkboxes = document.querySelectorAll('.sku-checkbox:checked');
        checkboxes.forEach(function(checkbox) {
            selectedSKUs.push(checkbox.value);
        });

        if (selectedSKUs.length > 0) {
            var skuString = selectedSKUs.join(',');
            var hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'skus';
            hiddenInput.value = skuString;
            document.getElementById('skuForm').appendChild(hiddenInput);
        }
    }
    </script>
</div>
</body>
</html>
