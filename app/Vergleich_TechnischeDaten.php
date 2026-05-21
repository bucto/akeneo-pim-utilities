<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produktvergleich - Technische Daten</title>
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
        .attr-name {
            font-weight: bold;
            background-color: #edf2f7 !important;
            color: #2d3748;
            width: 250px;
        }
    </style>
</head>
<body>
    <h1>Vergleich der Technischen Daten</h1>

    <?php
    include 'config.php';

    function getMatrixAccessToken($tokenUrl, $clientId, $clientSecret, $username, $password) {
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

    function getMatrixAttributeDetails($baseUrl, $accessToken, $attributeCode) {
        $url = "$baseUrl/attributes/$attributeCode";
        $options = [
            'http' => ['header' => ["Authorization: Bearer $accessToken", "Content-Type: application/json"], 'method' => 'GET', 'ignore_errors' => true],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ];
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        return $result ? json_decode($result, true) : null;
    }

    function getMatrixProductAttributes($baseUrl, $accessToken, $sku, $locale = 'de_DE') {
        $url = "$baseUrl/products/" . urlencode($sku) . "?locale=$locale";
        $options = [
            'http' => ['header' => ["Authorization: Bearer $accessToken", "Content-Type: application/json"], 'method' => 'GET', 'ignore_errors' => true],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ];
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        return $result ? json_decode($result, true) : null;
    }

    function getMatrixAttributeOptions($baseUrl, $accessToken, $attributeCode) {
        $url = "$baseUrl/attributes/$attributeCode/options?limit=100";
        $options = [
            'http' => ['header' => ["Authorization: Bearer $accessToken", "Content-Type: application/json"], 'method' => 'GET', 'ignore_errors' => true],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ];
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        return $result ? json_decode($result, true) : null;
    }

    function formatMatrixNumericValue($value) {
        if (is_numeric($value)) {
            // Schneidet .0000 ab, falls vorhanden
            if (floor($value) == $value) {
                return (int)$value;
            }
            return (float)$value;
        }
        return $value;
    }

    if (isset($_GET['skus']) && !empty($_GET['skus'])) {
        $skus = explode(',', $_GET['skus']);
        $skus = array_map('trim', $skus);
    } else {
        die('<p>Keine SKUs für den Vergleich übermittelt.</p>');
    }

    $accessToken = getMatrixAccessToken(TOKEN_URL, CLIENT_ID, CLIENT_SECRET, API_USERNAME, API_PASSWORD);

    $attributes = [];
    $products = [];
    $optionsMap = []; 

    foreach ($skus as $sku) {
        $product = getMatrixProductAttributes(API_BASE_URL, $accessToken, $sku, 'de_DE');
        if (!$product || !isset($product['values'])) continue;
        
        $products[$sku] = $product['values'];

        foreach ($product['values'] as $attribute => $data) {
            if (!in_array($attribute, $attributes)) {
                $attributes[] = $attribute;
            }
            
            $rawVal = isset($data[0]['data']) ? $data[0]['data'] : null;
            if ($rawVal && is_string($rawVal) && !is_numeric($rawVal) && !isset($optionsMap[$attribute])) {
                $options = getMatrixAttributeOptions(API_BASE_URL, $accessToken, $attribute);
                if ($options && isset($options['_embedded']['items'])) {
                    foreach ($options['_embedded']['items'] as $option) {
                        $optionsMap[$attribute][$option['code']] = isset($option['labels']['de_DE']) ? $option['labels']['de_DE'] : $option['code'];
                    }
                }
            }
        }
    }

    echo '<table>';
    echo '<tr><th class="attr-name">Technische Eigenschaft</th>';
    foreach ($skus as $sku) {
        echo "<th>" . htmlspecialchars($sku) . "</th>";
    }
    echo '</tr>';

    foreach ($attributes as $attribute) {
        if (in_array($attribute, ['picture', 'filename_picture_perspective', 'product_name'])) continue;

        $attributeDetails = getMatrixAttributeDetails(API_BASE_URL, $accessToken, $attribute);
        $attributeName = isset($attributeDetails['labels']['de_DE']) ? $attributeDetails['labels']['de_DE'] : $attribute;

        echo "<tr><td class='attr-name'>" . htmlspecialchars($attributeName) . "</td>";

        foreach ($skus as $sku) {
            $value = '-';
            $unit = '';

            if (isset($products[$sku][$attribute][0])) {
                $rawData = $products[$sku][$attribute][0]['data'];
                if (isset($products[$sku][$attribute][0]['unit'])) {
                    $unit = $products[$sku][$attribute][0]['unit'];
                }

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

            if ($unit) {
                $unit = strtolower($unit);
                if ($unit === 'millimeter') $unit = 'mm';
                if ($unit === 'kilogram') $unit = 'kg';
                if ($unit === 'meter_per_minute') $unit = 'm/min';
                $value .= " " . $unit;
            }

            echo "<td>" . htmlspecialchars($value) . "</td>";
        }
        echo '</tr>';
    }
    echo '</table>';
    ?>
</body>
</html>