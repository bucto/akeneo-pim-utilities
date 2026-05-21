<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produktattribute Matrix</title>
    <link rel="stylesheet" href="vergleich.css">
</head>
<body>
    <h1>Vergleich der Produktattribute 2025</h1>

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
            ],
        ];

        $context  = stream_context_create($options);
        $result = file_get_contents($tokenUrl, false, $context);

        if ($result === FALSE) {
            $error = error_get_last();
            die('Fehler beim Abrufen des Access Tokens: ' . $error['message']);
        }

        $response = json_decode($result, true);
        if (!isset($response['access_token'])) {
            die('Unerwartete Antwort vom Server: ' . print_r($response, true));
        }

        return $response['access_token'];
    }

    // Funktion, um Attributdetails abzurufen
    function getAttributeDetails($baseUrl, $accessToken, $attributeCode, $locale = 'de_DE') {
        $url = "$baseUrl/attributes/$attributeCode"; // URL für Attributdetails

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
            $error = error_get_last();
            die('Fehler beim Abrufen der Attributdetails: ' . $error['message']);
        }

        return json_decode($result, true);
    }

    // Funktion, um Produktattribute abzurufen (mit Sprache)
    function getProductAttributes($baseUrl, $accessToken, $sku, $locale = 'de_DE') {
        $url = "$baseUrl/products/$sku?locale=$locale"; // Produktdaten für die gegebene SKU abrufen

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
            $error = error_get_last();
            die('Fehler beim Abrufen der Produktattribute: ' . $error['message']);
        }

        return json_decode($result, true);
    }

    // Funktion, um Attributoptionen abzurufen
    function getAttributeOptions($baseUrl, $accessToken, $attributeCode, $locale = 'de_DE') {
        $url = "$baseUrl/attributes/$attributeCode/options"; // URL für Attributoptionen

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
            $error = error_get_last();
            return null; // Wenn die Optionen nicht existieren (404), einfach null zurückgeben
        }

        $response = json_decode($result, true);

        // Wenn das Attribut 'pressbrake_drive' ist, dann prüfen wir speziell die Option
        if ($attributeCode === 'pressbrake_drive') {
            // Durch die Optionen gehen und den richtigen Namen zuordnen
            foreach ($response['_embedded']['items'] as &$option) {
                if ($option['code'] === 'servo_hydraulic_press_brake') {
                    // Setze die deutsche Bezeichnung für 'servo_hydraulic_press_brake'
                    $option['labels']['de_DE'] = 'Servo-Hydraulische Abkantpresse';
                }
            }
        }

        return $response;
    }

    // Funktion, um numerische Werte zu formatieren (z. B. .0000 entfernen)
    function formatNumericValue($value) {
        // Überprüfe, ob der Wert eine Zahl ist
        if (is_numeric($value)) {
            // Wenn der Wert eine Ganzzahl oder Dezimalzahl mit .0000 ist, entferne den Dezimalteil
            if (preg_match('/^\d+\.0+$/', $value)) {
                $value = (int)$value; // Umwandlung in Ganzzahl entfernt .0000
            }
        }
        return $value;
    }

    // Workflow
    if (isset($_GET['skus'])) {
        $skus = explode(',', $_GET['skus']); // SKUs von der URL als Array
    } else {
        die('Keine SKUs übermittelt.');
    }

    // 1. Access Token abrufen
    $accessToken = getAccessToken(TOKEN_URL, CLIENT_ID, CLIENT_SECRET, API_USERNAME, API_PASSWORD);

    // 2. Alle Attribute für alle Produkte abrufen
    $attributes = [];
    $products = [];
    $optionsMap = []; // Optionen Map außerhalb der Produkt Schleife definieren

    foreach ($skus as $sku) {
        $product = getProductAttributes(API_BASE_URL, $accessToken, $sku, 'de_DE'); // Die deutsche Sprache verwenden
        $products[$sku] = $product['values'];

        // Alle Attribute sammeln (nur einmal pro Produkt)
        foreach ($product['values'] as $attribute => $data) {
            if (!in_array($attribute, $attributes)) {
                $attributes[] = $attribute;
            }
        }

        // Attributoptionen abrufen, wenn es Optionen gibt
        foreach ($product['values'] as $attribute => $data) {
            if ($data[0]['type'] === 'pim_catalog_option') {
                $options = getAttributeOptions(API_BASE_URL, $accessToken, $attribute, 'de_DE');
                foreach ($options['_embedded']['items'] as $option) {
                    $optionsMap[$attribute][$option['code']] = $option['labels']['de_DE'];
                }
            }
        }
    }

    // 3. Matrix-Tabelle erstellen
    echo '<table border="1">';
    echo '<tr><th>Attribut</th>';

    // Produkt-SKUs als Spaltenüberschriften
    foreach ($skus as $sku) {
        echo "<th>$sku</th>";
    }
    echo '</tr>';

    // Zeilen für jedes Attribut
    foreach ($attributes as $attribute) {
        // Attribut überspringen, wenn es eines der unerwünschten ist
        if (in_array($attribute, ['picture', 'filename_picture_perspective', 'product_name'])) {
            continue;
        }

        // Hier wird der Attributname für 'de_DE' (z.B. "Breite") aus den Attributdetails geholt
        $attributeDetails = getAttributeDetails(API_BASE_URL, $accessToken, $attribute, 'de_DE');
        $attributeName = isset($attributeDetails['labels']['de_DE']) ? $attributeDetails['labels']['de_DE'] : $attribute;

        echo "<tr><td>$attributeName</td>";

        // Werte für jedes Produkt unter dem Attribut
        foreach ($skus as $sku) {
            // Prüfen, ob der Wert vorhanden ist
            $value = isset($products[$sku][$attribute]) ? $products[$sku][$attribute][0]['data'] : 'Nicht verfügbar';

            // Wenn das Attribut Optionen hat, den entsprechenden Namen anzeigen
            if (isset($optionsMap[$attribute][$value])) {
                // Zeige das Label anstelle des Codes
                $value = $optionsMap[$attribute][$value];
            }

            // Formatieren, um ".0000" zu entfernen, falls das Attribut eine Zahl ist
            $value = formatNumericValue($value);

            // Wenn der Wert ein Array ist (z. B. für mehrere Auswahlmöglichkeiten), dann diese als Liste anzeigen
            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            // Einheit hinzufügen, falls verfügbar
            $unit = isset($products[$sku][$attribute][0]['unit']) ? $products[$sku][$attribute][0]['unit'] : '';

            // Einheit umwandeln, falls nötig (z. B. "MILLIMETER" -> "mm")
            if ($unit) {
                $unit = strtolower($unit);
                if ($unit === 'millimeter') {
                    $unit = 'mm';
                }
            }

            // Einheit und Wert ausgeben
            echo "<td>" . htmlspecialchars($value . ($unit ? " ($unit)" : '')) . "</td>";
        }
        echo '</tr>';
    }

    echo '</table>';
    ?>
</body>
</html>
