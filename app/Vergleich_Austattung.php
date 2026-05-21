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

    // Funktion, um ein Access Token zu erhalten
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
        $result = file_get_contents($tokenUrl, false, $context);

        if ($result === FALSE) {
            die('Fehler beim Abrufen des Access Tokens. Bitte Portainer-Verbindung prüfen.');
        }

        $response = json_decode($result, true);
        if (!isset($response['access_token'])) {
            die('Unerwartete Antwort vom Server beim Token-Abruf: ' . print_r($response, true));
        }

        return $response['access_token'];
    }

    // Funktion, um Attributdetails abzurufen
    function getAttributeDetails($baseUrl, $accessToken, $attributeCode, $locale = 'de_DE') {
        $url = "$baseUrl/attributes/$attributeCode";

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
            return null;
        }

        return json_decode($result, true);
    }

    // Funktion, um Produktattribute abzurufen (mit Sprache)
    function getProductAttributes($baseUrl, $accessToken, $sku, $locale = 'de_DE') {
        $url = "$baseUrl/products/$sku?locale=$locale";

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
            die("Fehler beim Abrufen der Produktattribute für SKU: " . htmlspecialchars($sku));
        }

        return json_decode($result, true);
    }

    // Funktion, um Attributoptionen abzurufen
    function getAttributeOptions($baseUrl, $accessToken, $attributeCode, $locale = 'de_DE') {
        $url = "$baseUrl/attributes/$attributeCode/options?limit=100";

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
            return null; 
        }

        $response = json_decode($result, true);

        // Spezifische Namensanpassung für 'pressbrake_drive' beibehalten
        if ($attributeCode === 'pressbrake_drive' && isset($response['_embedded']['items'])) {
            foreach ($response['_embedded']['items'] as &$option) {
                if ($option['code'] === 'servo_hydraulic_press_brake') {
                    $option['labels']['de_DE'] = 'Servo-Hydraulische Abkantpresse';
                }
            }
        }

        return $response;
    }

    // Funktion, um numerische Werte zu formatieren (z. B. .0000 entfernen)
    function formatNumericValue($value) {
        if (is_numeric($value)) {
            if (preg_match('/^\d+\.0+$/', $value)) {
                $value = (int)$value;
            }
        }
        return $value;
    }

    // Workflow
    if (isset($_GET['skus']) && !empty($_GET['skus'])) {
        $skus = explode(',', $_GET['skus']);
    } else {
        die('Keine SKUs übermittelt.');
    }

    // 1. Access Token abrufen
    $accessToken = getAccessToken(TOKEN_URL, CLIENT_ID, CLIENT_SECRET, API_USERNAME, API_PASSWORD);

    // 2. Alle Attribute für alle Produkte abrufen
    $attributes = [];
    $products = [];
    $optionsMap = []; 

    foreach ($skus as $sku) {
        $sku = trim($sku);
        $product = getProductAttributes(API_BASE_URL, $accessToken, $sku, 'de_DE');
        
        if (!isset($product['values'])) {
            continue;
        }
        
        $products[$sku] = $product['values'];

        // Alle Attribute sammeln
        foreach ($product['values'] as $attribute => $data) {
            if (!in_array($attribute, $attributes)) {
                $attributes[] = $attribute;
            }
            
            // Sicherer Check, ob es sich um eine Option/Auswahlliste handelt
            // Wenn der Wert ein String ist und kein numerischer Wert, prüfen wir auf API-Optionen
            $rawVal = isset($data[0]['data']) ? $data[0]['data'] : null;
            if ($rawVal && is_string($rawVal) && !is_numeric($rawVal) && !isset($optionsMap[$attribute])) {
                $options = getAttributeOptions(API_BASE_URL, $accessToken, $attribute, 'de_DE');
                if (isset($options['_embedded']['items'])) {
                    foreach ($options['_embedded']['items'] as $option) {
                        $optionsMap[$attribute][$option['code']] = isset($option['labels']['de_DE']) ? $option['labels']['de_DE'] : $option['code'];
                    }
                }
            }
        }
    }

    // 3. Matrix-Tabelle erstellen
    echo '<table border="1" style="border-collapse: collapse; width: 100%; text-align: left;">';
    echo '<tr style="background-color: #f2f2f2;"><th>Attribut</th>';

    // Produkt-SKUs als Spaltenüberschriften
    foreach ($skus as $sku) {
        echo "<th>" . htmlspecialchars($sku) . "</th>";
    }
    echo '</tr>';

    // Zeilen für jedes Attribut
    foreach ($attributes as $attribute) {
        // Unerwünschte Systemattribute überspringen
        if (in_array($attribute, ['picture', 'filename_picture_perspective', 'product_name'])) {
            continue;
        }

        // Attribut-Label auf Deutsch holen
        $attributeDetails = getAttributeDetails(API_BASE_URL, $accessToken, $attribute, 'de_DE');
        $attributeName = isset($attributeDetails['labels']['de_DE']) ? $attributeDetails['labels']['de_DE'] : $attribute;

        echo "<tr><td style="font-weight: bold; padding: 8px;">" . htmlspecialchars($attributeName) . "</td>";

        // Werte für jedes Produkt unter dem Attribut ausgeben
        foreach ($skus as $sku) {
            $value = 'Nicht verfügbar';
            $unit = '';

            if (isset($products[$sku][$attribute][0])) {
                $rawData = $products[$sku][$attribute][0]['data'];
                
                // Einheiten-Handling
                if (isset($products[$sku][$attribute][0]['unit'])) {
                    $unit = $products[$sku][$attribute][0]['unit'];
                }

                // Options-Code in lesbares Label umwandeln
                if (is_array($rawData)) {
                    // Falls Mehrfachauswahl (Multiselect)
                    $mappedArray = [];
                    foreach ($rawData as $subValue) {
                        $mappedArray[] = isset($optionsMap[$attribute][$subValue]) ? $optionsMap[$attribute][$subValue] : $subValue;
                    }
                    $value = implode(', ', $mappedArray);
                } else {
                    // Einfacher Wert
                    $value = isset($optionsMap[$attribute][$rawData]) ? $optionsMap[$attribute][$rawData] : $rawData;
                    $value = formatNumericValue($value);
                }
            }

            // Einheit umwandeln (z. B. "MILLIMETER" -> "mm")
            if ($unit) {
                $unit = strtolower($unit);
                if ($unit === 'millimeter') {
                    $unit = 'mm';
                }
                $value .= " " . $unit;
            }

            echo "<td style="padding: 8px;">" . htmlspecialchars($value) . "</td>";
        }
        echo '</tr>';
    }

    echo '</table>';
    ?>
</body>
</html>