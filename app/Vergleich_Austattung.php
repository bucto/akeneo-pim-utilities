<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produkt-Assoziationen Vergleich</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        th {
            background-color: #f4f4f4;
        }
        .product-container {
            margin-bottom: 20px;
        }
        h1 {
            text-align: center;
        }
    </style>
</head>
<body>
    <h1>Produktvergleich mit Assoziationen</h1>

    <?php
    // Konfiguration einbinden
    include('config.php'); // Datei einbinden, die die Konfigurationsvariablen enthält

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

    function getProductAssociations($baseUrl, $accessToken, $sku) {
        $url = "$baseUrl/products/$sku";

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
            die("Fehler beim Abrufen der Assoziationen für SKU $sku");
        }

        return json_decode($result, true);
    }

    if (isset($_GET['skus']) && !empty($_GET['skus'])) {
        // Die SKUs aus dem URL-Parameter holen
        $productSKUs = explode(',', $_GET['skus']);
        $productSKUs = array_map('trim', $productSKUs); // Entfernt Leerzeichen

        $accessToken = getAccessToken(TOKEN_URL, CLIENT_ID, CLIENT_SECRET, API_USERNAME, API_PASSWORD);

        // Header der Tabelle
        echo '<table>';
        echo '<tr><td></td>'; // Leere Zelle in der ersten Spalte für die erste Zeile
        
        // Produkt-SKUs als Header
        foreach ($productSKUs as $sku) {
            echo "<th>$sku</th>";
        }
        echo '</tr>';

        // Abfrage der Assoziationen für jedes Produkt
        $associationsGroupedByType = [];

        foreach ($productSKUs as $sku) {
            $associations = getProductAssociations(API_BASE_URL, $accessToken, $sku);

            // Assoziationen nach Typ gruppieren
            foreach ($associations['associations'] as $associationType => $associationData) {
                if (isset($associationData['products'])) {
                    $associationsGroupedByType[$associationType][$sku] = $associationData['products'];
                }
            }
        }

        // Zeilen für die Assoziationen
        foreach ($associationsGroupedByType as $associationType => $associationData) {
            echo "<tr><td><strong>$associationType</strong></td>";

            foreach ($productSKUs as $sku) {
                $products = isset($associationData[$sku]) ? implode('<br>', $associationData[$sku]) : '';
                echo "<td>$products</td>";
            }

            echo '</tr>';
        }

        echo '</table>';
    } else {
        echo '<p>Bitte übergebe die Produkt-SKUs als URL-Parameter (z.B. ?skus=VENTIS-3015AJe_(4kW),VENTIS-3015AJe_(6kW))</p>';
    }
    ?>
</body>
</html>
