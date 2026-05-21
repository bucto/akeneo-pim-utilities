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

function getSKUsByCategories($baseUrl, $accessToken, $categoryCodes) {
    $allProducts = [];
    $page = 1;
    $limit = 100; // Anzahl der Produkte pro Seite

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
                'method' => 'GET'
            ],
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        if ($result === FALSE) {
            die("Fehler beim Abrufen der Produkte für die Kategorien " . implode(', ', $categoryCodes));
        }

        $response = json_decode($result, true);

        // Wenn keine Produkte mehr vorhanden sind, breche die Schleife ab
        if (empty($response['_embedded']['items'])) {
            break;
        }

        // Füge die Produkte zur Gesamtliste hinzu
        $allProducts = array_merge($allProducts, $response['_embedded']['items']);

        // Wenn weniger Produkte als die angegebene Limitzahl zurückgegeben wurden, sind keine weiteren Seiten vorhanden
        if (count($response['_embedded']['items']) < $limit) {
            break;
        }

        $page++; // Gehe zur nächsten Seite
    }

    return $allProducts;
}

// Workflow
$accessToken = getAccessToken(TOKEN_URL, CLIENT_ID, CLIENT_SECRET, API_USERNAME, API_PASSWORD);

// Hier kannst du mehrere Kategorien angeben
$categoryCodes = ['series_name_asf_eu','series_name_as_3', 'series_name_as_lul', 'series_name_fbs',  'series_name_as_uls_ntk', 'series_name_la_sr_ntk', 'series_name_as_lul2', 'series_name_mp_tower', 'series_name_asr_pr', 'series_name_stri', 'series_name_asf2_eu']; // Beispiel mit mehreren Kategorien

$products = getSKUsByCategories(API_BASE_URL, $accessToken, $categoryCodes);

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
    <h1>Wähle das  gewünschte aus</h1>

 
    <form id="skuForm" action="#" method="get">
        <div class="checkbox-list">

            <?php
            if (!empty($products)) {
                // Arrays für aktive und deaktivierte Produkte
                $activeProducts = [];
                $disabledProducts = [];

                // Teile Produkte in aktive und deaktivierte Produkte
                foreach ($products as $product) {
                    if (isset($product['enabled']) && $product['enabled'] === false) {
                        $disabledProducts[] = $product;
                    } else {
                        $activeProducts[] = $product;
                    }
                }

                // Sortiere beide Arrays alphabetisch nach SKU
                usort($activeProducts, function($a, $b) {
                    return strcasecmp($a['identifier'], $b['identifier']);
                });

                usort($disabledProducts, function($a, $b) {
                    return strcasecmp($a['identifier'], $b['identifier']);
                });

                // Ausgabe der aktiven Produkte
                foreach ($activeProducts as $product) {
                    echo "<label>";
                    echo "<input type='checkbox' class='sku-checkbox' name='skus[]' value='" . htmlspecialchars($product['identifier']) . "'> " . htmlspecialchars($product['identifier']);
                    echo "</label>";
                }

                // Ausgabe der deaktivierten Produkte unter den aktiven Produkten
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

        // Kombiniere die SKUs durch Komma getrennt
        if (selectedSKUs.length > 0) {
            var skuString = selectedSKUs.join(',');
            // Erstelle einen versteckten Input für die skus und setze den kombinierten Wert
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
