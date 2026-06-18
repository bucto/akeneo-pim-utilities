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

    <div class="page-content">
    <div class="page-head">
        <h1>Vergleich der Technischen Daten</h1>
        <a href="produkt_vergleich.php" class="back-link">← Zurück</a>
    </div>

    <?php
    function getMatrixAttributeDetails($baseUrl, $accessToken, $attributeCode) {
        return apiGet("$baseUrl/attributes/$attributeCode", $accessToken);
    }

    function getMatrixAttributeOptions($baseUrl, $accessToken, $attributeCode) {
        return apiGet("$baseUrl/attributes/$attributeCode/options?limit=100", $accessToken);
    }

    /**
     * Attributwert für die Vergleichsmatrix formatieren (Kanal/Locale-aware).
     */
    function formatMatrixValue(string $attribute, ?array $entries, array $optionsMap): string {
        $entry = pickValueEntry($entries ?? []);
        if (!$entry) {
            return '-';
        }

        $rawData = $entry['data'];

        if (is_array($rawData) && array_key_exists('amount', $rawData)) {
            $disp = formatAmount((string)$rawData['amount']);
            $unit = $rawData['unit'] ?? $entry['unit'] ?? null;
            if ($unit !== null && $unit !== '') {
                $disp .= ' ' . unitAbbr((string)$unit);
            }
            return $disp;
        }

        if (is_array($rawData) && !array_key_exists('amount', $rawData)) {
            $mapped = array_map(fn($v) => $optionsMap[$attribute][$v] ?? $v, $rawData);
            return implode(', ', $mapped);
        }

        $display = $optionsMap[$attribute][$rawData] ?? $rawData;

        if (is_numeric($display)) {
            $f       = (float)$display;
            $display = ($f == floor($f)) ? (int)$f : $f;
        }

        $legacyUnit = $entry['unit'] ?? '';
        if ($legacyUnit) {
            $display .= ' ' . unitAbbr($legacyUnit);
        }

        return (string)$display;
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
        // Werte von Parent-Modellen (1..n Ebenen) mit dem Produkt zusammenführen
        $product = getAkeneoProductWithInheritedValues($sku);
        if (!$product || !isset($product['values'])) continue;

        $products[$sku]  = $product['values'];
        $imageUrls[$sku] = $product['_imageUrl'] ?? null;

        foreach ($product['values'] as $attribute => $data) {
            if (!in_array($attribute, $attributes)) {
                $attributes[] = $attribute;
            }

            $entry  = pickValueEntry($data);
            $rawVal = $entry['data'] ?? null;

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
            $value = formatMatrixValue(
                $attribute,
                $products[$sku][$attribute] ?? null,
                $optionsMap
            );

            echo '<td>' . htmlspecialchars($value) . '</td>';
        }
        echo '</tr>';
    }

    echo '</table>';
    ?>
    </div>

<?php renderSiteFooter(); ?>

</body>
</html>
