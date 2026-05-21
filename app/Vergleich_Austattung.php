<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produktvergleich - Ausstattung & Assoziationen</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 30px;
            background-color: #f7fafc;
            color: #2d3748;
        }
        h1 {
            font-size: 24px;
            border-bottom: 3px solid #e2001a;
            padding-bottom: 10px;
            text-transform: uppercase;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #e2e8f0;
            padding: 12px 15px;
            text-align: left;
        }
        th {
            background-color: #2d3748;
            color: white;
            font-weight: 600;
        }
        tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .assoc-type {
            font-weight: bold;
            background-color: #edf2f7 !important;
            color: #2d3748;
            width: 250px;
        }
        .badge {
            display: inline-block;
            background: #e2e8f0;
            padding: 3px 8px;
            margin: 2px 0;
            border-radius: 4px;
            font-size: 13px;
        }
    </style>
</head>
<body>

    <h1>Vergleich der Ausstattung & Verbindungen</h1>

    <?php
    include 'config.php';

    function getAssocAccessToken($tokenUrl, $clientId, $clientSecret, $username, $password) {
        $data = ['grant_type' => 'password', 'username' => $username, 'password' => $password, 'client_id' => $clientId, 'client_secret' => $clientSecret];
        $options = [
            'http' => ['header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'method' => 'POST', 'content' => http_build_query($data), 'ignore_errors' => true],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ];
        $context = stream_context_create($options);
        $result = @file_get_contents($tokenUrl, false, $context);
        $response = json_decode($result, true);
        return $response['access_token'];
    }

    function getProductData($baseUrl, $accessToken, $sku) {
        $url = "$baseUrl/products/" . urlencode($sku);
        $options = [
            'http' => ['header' => ["Authorization: Bearer $accessToken", "Content-Type: application/json"], 'method' => 'GET', 'ignore_errors' => true],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ];
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        return $result ? json_decode($result, true) : null;
    }

    if (isset($_GET['skus']) && !empty($_GET['skus'])) {
        $skus = explode(',', $_GET['skus']);
        $skus = array_map('trim', $skus);
    } else {
        die('<p>Keine SKUs für den Vergleich übermittelt.</p>');
    }

    $accessToken = getAssocAccessToken(TOKEN_URL, CLIENT_ID, CLIENT_SECRET, API_USERNAME, API_PASSWORD);

    $matrix = [];
    $allAssocTypes = [];

    // Daten sammeln
    foreach ($skus as $sku) {
        $product = getProductData(API_BASE_URL, $accessToken, $sku);
        if ($product && isset($product['associations'])) {
            foreach ($product['associations'] as $type => $data) {
                if (!in_array($type, $allAssocTypes)) {
                    $allAssocTypes[] = $type;
                }
                if (!empty($data['products'])) {
                    $matrix[$type][$sku] = $data['products'];
                }
            }
        }
    }

    // Matrix anzeigen
    if (!empty($allAssocTypes)) {
        echo '<table>';
        echo '<tr><th class="assoc-type">Ausstattungs-Typ</th>';
        foreach ($skus as $sku) {
            echo "<th>" . htmlspecialchars($sku) . "</th>";
        }
        echo '</tr>';

        foreach ($allAssocTypes as $type) {
            echo "<tr><td class='assoc-type'>" . htmlspecialchars($type) . "</td>";
            foreach ($skus as $sku) {
                echo "<td>";
                if (isset($matrix[$type][$sku])) {
                    foreach ($matrix[$type][$sku] as $associatedSku) {
                        echo "<span class='badge'>" . htmlspecialchars($associatedSku) . "</span><br>";
                    }
                } else {
                    echo "<span style='color: #a0aec0;'>- Keine -</span>";
                }
                echo "</td>";
            }
            echo "</tr>";
        }
        echo '</table>';
    } else {
        echo '<p>Für die ausgewählten Produkte wurden keine verknüpften Ausstattungen im PIM gefunden.</p>';
    }
    ?>
</body>
</html>