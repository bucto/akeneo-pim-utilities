<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produktattribute Matrix</title>
    <link rel="stylesheet" href="css/vergleich.css">
</head>
<body>
    <h1>Vergleich der Produktattribute</h1>

    <?php
    // Konfigurationsdatei einbinden
    include 'config.php';

    /**
     * Holt das Access Token (mit integriertem SSL-Fix für die interne IP)
     */
    function getMatrixAccessToken($tokenUrl, $clientId, $clientSecret, $username, $password) {
        $data = [
            'grant_type' => 'password',
            'username' => $username,
            'password' => $password,
            'client_id' => $clientId,
            'client_secret' => $clientSecret
        ];

        $options = [
            'http' => [
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ];

        $context  = stream_context_create($options);
        $result = @file_get_contents($tokenUrl, false, $context);

        if ($result === FALSE) {
            die('Fehler beim Abrufen des Access Tokens. Bitte Netzwerkverbindung prüfen.');
        }

        $response = json_decode($result, true);
        if (!isset($response['access_token'])) {
            die('API-Fehler beim Token-Abruf: <pre>' . htmlspecialchars($result) . '</pre>');
        }

        return $response['access_token'];
    }

    /**
     * Holt Übersetzungen für die Spaltenbeschriftungen (z.B. "Breite" statt "width")
     */
    function getMatrixAttributeDetails($baseUrl, $accessToken, $attributeCode) {
        $url = "$baseUrl/attributes/$attributeCode";
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
        return $result ? json_decode($result, true) : null;
    }

    /**
     * Holt alle Daten zu einer bestimmten Maschinen-SKU
     */
    function getMatrixProductAttributes($baseUrl, $accessToken, $sku, $locale = 'de_DE') {
        $url = "$baseUrl/products/" . urlencode($sku) . "?locale=$locale";
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
            die("Fehler: Das Produkt mit der SKU '" . htmlspecialchars($sku) . "' wurde im PIM nicht gefunden.");
        }

        return json_decode($result, true);
    }

    /**
     * Holt die Klartext-Labels für Dropdown-Auswahllisten
     */
    function getMatrixAttributeOptions($baseUrl, $accessToken, $attributeCode) {
        $url = "$baseUrl/attributes/$attributeCode/options?limit=100";
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
        if ($result === FALSE) return null;

        $response = json_decode($result, true);

        // Spezifische Sonderbehandlung für 'pressbrake_drive'
        if ($attributeCode === 'pressbrake_drive' && isset($response['_embedded']['items'])) {
            foreach ($response['_embedded']['items'] as &$option) {
                if ($option['code'] === 'servo_hydraulic_press_brake') {
                    $option['labels']['de_DE'] = 'Servo-Hydraulische Abkantpresse';
                }
            }
        }

        return $response;
    }

    /**
     * Formatiert Zahlenwerte (entfernt unschöne .0000 Anhänge aus der Datenbank)
     */
    function formatMatrixNumericValue($value) {
        if (is_numeric($value) && preg_match('/^\d+\.0+$/', $value)) {
            return (int)$value;
        }
        return $value;
    }

    // Workflow & SKU-Auswertung aus der URL
    if (isset($_GET['skus']) && !empty($_GET['skus'])) {
        $skus = explode(',', $_GET['skus']);
    } else {
        die('Keine SKUs für den Vergleich übermittelt. Bitte gehe zurück zur Maschinenauswahl.');
    }

    // 1. Verbindung aufbauen
    $accessToken = getMatrixAccessToken(TOKEN_URL, CLIENT_ID, CLIENT_SECRET, API_USERNAME, API_PASSWORD);

    // 2. Daten sammeln
    $attributes = [];
    $products = [];
    $optionsMap = []; 

    foreach ($skus as $sku) {
        $sku = trim($sku);
        $product = getMatrixProductAttributes(API_BASE_URL, $accessToken, $sku, 'de_DE');
        
        if (!isset($product['values'])) {
            continue;
        }
        
        $products[$sku] = $product['values'];

        // Dynamisch alle vorkommenden Attribute registrieren
        foreach ($product['values'] as $attribute => $data) {
            if (!in_array($attribute, $attributes)) {
                $attributes[] = $attribute;
            }
            
            $rawVal = isset($data[0]['data']) ? $data[0]['data'] : null;
            if ($rawVal && is_string($rawVal) && !is_numeric($rawVal) && !isset($optionsMap[$attribute])) {
                $options = getMatrixAttributeOptions(API_BASE_URL, $accessToken, $attribute);
                if (isset($options['_embedded']['items'])) {
                    foreach ($options['_embedded']['items'] as $option) {
                        $optionsMap[$attribute][$option['code']] = isset($option['labels']['de_DE']) ? $option['labels']['de_DE'] : $option['code'];
                    }
                }
            }
        }
    }

    // 3. Ausgabe der HTML-Matrix
    echo '<table border="1" style="border-collapse: collapse; width: 100%; text-align: left;">';
    echo '<tr style="background-color: #f2f2f2;"><th style="padding: 10px;">Attribut</th>';

    // SKUs als Spaltenköpfe ausgeben
    foreach ($skus as $sku) {
        echo "<th style='padding: 10px;'>" . htmlspecialchars($sku) . "</th>";
    }
    echo '</tr>';

    // Zeilen für die einzelnen Merkmale generieren
    foreach ($attributes as $attribute) {
        // Unwichtige Systemattribute/Bilder herausfiltern
        if (in_array($attribute, ['picture', 'filename_picture_perspective', 'product_name'])) {
            continue;
        }

        // Deutschen Anzeigenamen für das Attribut ermitteln
        $attributeDetails = getMatrixAttributeDetails(API_BASE_URL, $accessToken, $attribute);
        $attributeName = isset($attributeDetails['labels']['de_DE']) ? $attributeDetails['labels']['de_DE'] : $attribute;

        // HIER BEHOBEN: Einfache Anführungszeichen im HTML-String verwendet
        echo "<tr><td style='font-weight: bold; padding: 10px; background-color: #fafafa;'>" . htmlspecialchars($attributeName) . "</td>";

        // Werte der einzelnen Maschinen vergleichen
        foreach ($skus as $sku) {
            $value = 'Nicht verfügbar';
            $unit = '';

            if (isset($products[$sku][$attribute][0])) {
                $rawData = $products[$sku][$attribute][0]['data'];
                
                // Eventuelle Maßeinheiten auslesen (z.B. MILLIMETER)
                if (isset($products[$sku][$attribute][0]['unit'])) {
                    $unit = $products[$sku][$attribute][0]['unit'];
                }

                // Wert oder Array (für Mehrfachauswahl) lesbar aufbereiten
                if (is_array($rawData)) {
                    $mappedArray = [];
                    foreach ($rawData as $subValue) {
                        $mappedArray[] = isset($optionsMap[$attribute][$subValue]) ? $optionsMap[$attribute][$subValue] : $subValue;
                    }
                    $value = implode(', ', $mappedArray);
                } else {
                    $value = isset($optionsMap[$attribute][$rawData]) ? $optionsMap[$attribute][$rawData] : $rawData;
                    $value = formatMatrixNumericValue($value);
                }
            }

            // Einheiten-Klartext anhängen (z.B. "mm")
            if ($unit) {
                $unit = strtolower($unit);
                if ($unit === 'millimeter') {
                    $unit = 'mm';
                }
                $value .= " " . $unit;
            }

            // HIER BEHOBEN: Einfache Anführungszeichen im HTML-String verwendet
            echo "<td style='padding: 10px;'>" . htmlspecialchars($value) . "</td>";
        }
        echo '</tr>';
    }

    echo '</table>';
    ?>
</body>
</html>