<?php include 'config.php'; include 'api_helper.php'; include 'common.php'; ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produkt-Vergleich – Technische Daten</title>
    <?php renderBaseStyles(); ?>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 30px;
            background-color: #f7fafc;
            color: #2d3748;
        }
        .page-head {
            display: flex;
            align-items: baseline;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 6px;
        }
        h1 {
            font-size: 22px;
            border-bottom: 3px solid #e2001a;
            padding-bottom: 10px;
            text-transform: uppercase;
            margin: 0 0 6px;
        }
        .back-link {
            font-size: 13px;
            color: #718096;
            text-decoration: none;
            border: 1px solid #cbd5e0;
            border-radius: 4px;
            padding: 5px 11px;
        }
        .back-link:hover { background: #edf2f7; }
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
            padding: 10px 14px;
            text-align: left;
        }
        th {
            background-color: #2d3748;
            color: white;
            font-weight: 600;
            vertical-align: top;
        }
        tr:nth-child(even) td { background-color: #f8fafc; }
        .attr-name {
            font-weight: bold;
            background-color: #edf2f7 !important;
            color: #2d3748;
            width: 240px;
        }
        .product-img {
            display: block;
            width: 90px;
            height: 90px;
            object-fit: contain;
            margin: 0 auto 6px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            background: #f8fafc;
        }
        .product-img-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 90px;
            height: 90px;
            margin: 0 auto 6px;
            background: #edf2f7;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            font-size: 26px;
            color: #cbd5e0;
        }
        .sku-label {
            font-size: 13px;
            text-align: center;
            display: block;
        }
    </style>
</head>
<body>

    <?php renderSiteHeader('vergleich'); ?>

    <div class="page-head">
        <h1>Vergleich der Technischen Daten</h1>
        <a href="produkt_vergleich.php" class="back-link">← Zurück</a>
    </div>

    <?php
    function getMatrixAttributeDetails($baseUrl, $accessToken, $attributeCode) {
        return apiGet("$baseUrl/attributes/$attributeCode", $accessToken);
    }

    function getMatrixProductAttributes($baseUrl, $accessToken, $sku, $locale = 'de_DE') {
        return apiGet("$baseUrl/products/" . urlencode($sku) . "?locale=$locale", $accessToken);
    }

    function getMatrixAttributeOptions($baseUrl, $accessToken, $attributeCode) {
        return apiGet("$baseUrl/attributes/$attributeCode/options?limit=100", $accessToken);
    }

    if (!isset($_GET['skus']) || empty($_GET['skus'])) {
        die('<p>Keine SKUs für den Vergleich übermittelt.</p>');
    }

    $skus        = array_map('trim', explode(',', $_GET['skus']));
    $accessToken = getAccessToken();

    $attributes = [];
    $products   = [];
    $optionsMap = [];
    $imageUrls  = [];

    foreach ($skus as $sku) {
        $product = getMatrixProductAttributes(API_BASE_URL, $accessToken, $sku, 'de_DE');
        if (!$product || !isset($product['values'])) continue;

        $products[$sku]  = $product['values'];
        $imageUrls[$sku] = extractProductImageUrl($product);

        foreach ($product['values'] as $attribute => $data) {
            if (!in_array($attribute, $attributes)) {
                $attributes[] = $attribute;
            }

            $rawVal = $data[0]['data'] ?? null;

            // Option-Labels laden für:
            // a) Einfacher Select: $rawVal ist ein nicht-numerischer String
            // b) Multi-Select:     $rawVal ist ein numerisch-indiziertes Array von Strings
            //    (Maßattribute haben 'amount'/'unit'-Schlüssel → kein Option-Lookup)
            $needsOptionLookup = false;
            if (!isset($optionsMap[$attribute])) {
                if (is_string($rawVal) && $rawVal !== '' && !is_numeric($rawVal)) {
                    $needsOptionLookup = true;
                } elseif (is_array($rawVal)
                    && !array_key_exists('amount', $rawVal)
                    && !empty($rawVal)
                    && is_string(array_values($rawVal)[0])) {
                    $needsOptionLookup = true;
                }
            }

            if ($needsOptionLookup) {
                $options = getMatrixAttributeOptions(API_BASE_URL, $accessToken, $attribute);
                if ($options && isset($options['_embedded']['items'])) {
                    foreach ($options['_embedded']['items'] as $option) {
                        $optionsMap[$attribute][$option['code']] =
                            $option['labels']['de_DE'] ?? $option['code'];
                    }
                }
                // Leeres Array als Marker setzen, damit der Lookup nicht erneut ausgelöst wird
                if (!isset($optionsMap[$attribute])) {
                    $optionsMap[$attribute] = [];
                }
            }
        }
    }

    echo '<table>';

    // Header-Zeile mit Produktbildern
    echo '<tr><th class="attr-name">Technische Eigenschaft</th>';
    foreach ($skus as $sku) {
        $img = $imageUrls[$sku] ?? null;
        echo '<th>';
        if ($img) {
            echo '<img class="product-img" src="' . htmlspecialchars($img) . '" alt="' . htmlspecialchars($sku) . '"
                       onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';">';
            echo '<span class="product-img-placeholder" style="display:none;">📷</span>';
        } else {
            echo '<span class="product-img-placeholder">📷</span>';
        }
        echo '<span class="sku-label">' . htmlspecialchars($sku) . '</span>';
        echo '</th>';
    }
    echo '</tr>';

    $imageAttrs = array_map('trim', explode(',', PIM_IMAGE_ATTRS));
    $skipAttrs  = array_merge($imageAttrs, ['product_name']);

    foreach ($attributes as $attribute) {
        if (in_array($attribute, $skipAttrs)) continue;

        $attributeDetails = getMatrixAttributeDetails(API_BASE_URL, $accessToken, $attribute);
        $attributeName    = $attributeDetails['labels']['de_DE'] ?? $attribute;

        echo '<tr><td class="attr-name">' . htmlspecialchars($attributeName) . '</td>';

        foreach ($skus as $sku) {
            $value = '-';

            if (isset($products[$sku][$attribute][0])) {
                $entry   = $products[$sku][$attribute][0];
                $rawData = $entry['data'];

                // --- Maßattribut: {"amount": "7480.0000", "unit": "MILLIMETER"} ---
                if (is_array($rawData) && array_key_exists('amount', $rawData) && array_key_exists('unit', $rawData)) {
                    $amount = formatAmount((string)$rawData['amount']);
                    $abbr   = unitAbbr($rawData['unit']);
                    $value  = $amount . ' ' . $abbr;

                // --- Multi-Select: array von Option-Codes ---
                } elseif (is_array($rawData)) {
                    $mapped = array_map(fn($v) => $optionsMap[$attribute][$v] ?? $v, $rawData);
                    $value  = implode(', ', $mapped);

                // --- Einfacher Wert (String, Zahl, Boolean) ---
                } else {
                    // Option-Label nachschlagen
                    $display = $optionsMap[$attribute][$rawData] ?? $rawData;

                    // Zahl formatieren
                    if (is_numeric($display)) {
                        $f       = (float)$display;
                        $display = ($f == floor($f)) ? (int)$f : $f;
                    }

                    // Einheit aus separatem Feld (älteres Akeneo-Format)
                    $legacyUnit = $entry['unit'] ?? '';
                    if ($legacyUnit) {
                        $display .= ' ' . unitAbbr($legacyUnit);
                    }

                    $value = $display;
                }
            }

            echo '<td>' . htmlspecialchars((string)$value) . '</td>';
        }
        echo '</tr>';
    }

    echo '</table>';
    ?>
</body>
</html>
