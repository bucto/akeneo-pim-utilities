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
            'ignore_errors' => true // Erlaubt es PHP, auch Fehlermeldungen von Akeneo zu lesen
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($tokenUrl, false, $context);

    if ($result === FALSE) {
        die('Fehler: Die Token-URL konnte nicht erreicht werden. Prüfe die API_BASE_URL / TOKEN_URL.');
    }

    $response = json_decode($result, true);

    // Fehler abfangen, falls das JSON ungültig ist oder Akeneo einen Fehler meldet
    if ($response === null || !isset($response['access_token'])) {
        echo "<h3>API-Verbindungsfehler beim Token-Abruf</h3>";
        echo "<strong>Antwort von Akeneo:</strong> <pre>" . htmlspecialchars($result) . "</pre>";
        die();
    }

    return $response['access_token'];
}

function getSKUsByCategories($baseUrl, $accessToken, $categoryCodes) {
    $allProducts = [];
    $page = 1;
    $limit = 100;

    while (true) {
        $url = "$baseUrl/products?search=" . urlencode(json_encode([ 
            'categories' => [['operator' => 'IN', 'value' => $categoryCodes]] 
        ])) . "&page=$page&limit=$limit";

        $options = [
            'http' => [
                'header' => [
                    "Authorization: Bearer $accessToken",
                    "Content-Type: application/json"
                ],
                'method' => 'GET',
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        if ($result === FALSE) {
            die("Fehler beim Abrufen der Produkte für die Kategorien.");
        }

        $response = json_decode($result, true);

        // Fehler abfangen, falls beim Produkt-Abruf etwas schiefgeht
        if ($response === null || isset($response['code'])) {
            echo "<h3>API-Verbindungsfehler beim Produkt-Abruf</h3>";
            echo "<strong>Antwort von Akeneo:</strong> <pre>" . htmlspecialchars($result) . "</pre>";
            die();
        }

        if (empty($response['_embedded']['items'])) {
            break;
        }

        $allProducts = array_merge($allProducts, $response['_embedded']['items']);

        if (count($response['_embedded']['items']) < $limit) {
            break;
        }

        $page++;
    }

    return $allProducts;
}

// Workflow
$accessToken = getAccessToken(TOKEN_URL, CLIENT_ID, CLIENT_SECRET, API_USERNAME, API_PASSWORD);

// Hier kannst du mehrere Kategorien angeben
$categoryCodes = ['series_name_mp','series_name_mp_sheet_cat', 'series_name_rmp', 'series_name_mp_flexit', 'series_europe_mp', 'series_name_rmp_ntk'];

$products = getSKUsByCategories(API_BASE_URL, $accessToken, $categoryCodes);
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SKU Auswahl</title>
    <link rel="stylesheet" href="css/styles.css">
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
                    echo "<label>";
                    echo "<input type='checkbox' class='sku-checkbox' name='skus[]' value='" . htmlspecialchars($product['identifier']) . "'> " . htmlspecialchars($product['identifier']);
                    echo "</label>";
                }

                foreach ($disabledProducts as $product) {
                    echo "<label class='disabled'>";
                    echo "<input type='checkbox' class='sku-checkbox disabled-checkbox' name='skus[]' value='" . htmlspecialchars($product['identifier']) . "'> ";
                    echo "<span class='disabled-text'>" . htmlspecialchars($product['identifier']) . " (Deaktiviert)</span>";
                    echo "</label>";
                }
            } else {
                echo "<p>Keine Produkte in den angegebenen Kategorien gefunden.</p>";
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