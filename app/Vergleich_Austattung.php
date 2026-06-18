<?php include 'config.php'; include 'api_helper.php'; include 'common.php'; ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produkt-Vergleich – Ausstattung</title>
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
            background-color: var(--table-head-bg);
            color: white;
            font-weight: 600;
            vertical-align: top;
        }
        tr:nth-child(even) td { background-color: #f8fafc; }
        .assoc-type {
            font-weight: bold;
            background-color: #edf2f7 !important;
            color: #2d3748;
            width: 240px;
        }
        .badge {
            display: inline-block;
            background: #e2e8f0;
            padding: 4px 8px;
            margin: 2px 0;
            border-radius: 4px;
            font-size: 13px;
            line-height: 1.3;
        }
        .badge-sku {
            display: block;
            font-size: 11px;
            color: #718096;
            margin-top: 1px;
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
        .article-name-label {
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            display: block;
            margin-top: 4px;
            line-height: 1.3;
        }
    </style>
</head>
<body>

    <?php renderSiteHeader('vergleich'); ?>

    <div class="page-content">
    <div class="page-head">
        <h1>Vergleich der Ausstattung &amp; Verbindungen</h1>
        <a href="produkt_vergleich.php" class="back-link">← Zurück</a>
    </div>

    <?php

    function getProductData($baseUrl, $accessToken, $sku): ?array {
        return apiGet("$baseUrl/products/" . urlencode($sku), $accessToken);
    }

    if (!isset($_GET['skus']) || empty($_GET['skus'])) {
        die('<p>Keine SKUs für den Vergleich übermittelt.</p>');
    }

    $skus        = array_map('trim', explode(',', $_GET['skus']));
    $accessToken = getAccessToken();

    $matrix           = [];
    $allAssocTypes    = [];
    $imageUrls        = [];
    $articleNames     = [];
    $seriesBuildInfo  = [];
    $linkedProducts   = [];
    $linkedModels     = [];

    // Hauptprodukte laden + Assoziationsmatrix aufbauen
    foreach ($skus as $sku) {
        $product = getProductData(API_BASE_URL, $accessToken, $sku);
        if (!$product) continue;

        $imageUrls[$sku]       = extractProductImageUrl($product);
        $articleNames[$sku]    = extractArticleName($product) ?? $sku;
        $seriesBuildInfo[$sku] = getSeriesBuildInfoForProduct($product);

        foreach ($product['associations'] ?? [] as $type => $data) {
            $items = collectAssociationItems($data);
            if (empty($items)) {
                continue;
            }

            if (!in_array($type, $allAssocTypes, true)) {
                $allAssocTypes[] = $type;
            }

            $matrix[$type][$sku] = $items;

            foreach ($items as $item) {
                if ($item['kind'] === 'product') {
                    $linkedProducts[] = $item['code'];
                } else {
                    $linkedModels[] = $item['code'];
                }
            }
        }
    }

    $assocTypeLabels = getAkeneoAssociationTypeLabels();

    usort($allAssocTypes, fn($a, $b) =>
        strcasecmp($assocTypeLabels[$a] ?? $a, $assocTypeLabels[$b] ?? $b)
    );

    $verbindungFilter = array_values(array_filter(array_map(
        'trim',
        explode(',', PIM_ASSOC_VERBINDUNG_TYPES)
    )));
    if (!empty($verbindungFilter)) {
        $allAssocTypes = array_values(array_filter(
            $allAssocTypes,
            fn($type) => in_array($type, $verbindungFilter, true)
        ));
    }

    $linkedProductNames = getArticleNamesForProducts($linkedProducts);
    $linkedModelNames   = getArticleNamesForProductModels($linkedModels);

    $skus = sortSkusByBuildYearDesc($skus, $seriesBuildInfo);

    if (!empty($allAssocTypes)) {
        echo '<table>';

        echo '<tr><th class="assoc-type">Verbindung</th>';
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
            echo '<span class="article-name-label">' . htmlspecialchars($articleNames[$sku] ?? $sku) . '</span>';
            echo '</th>';
        }
        echo '</tr>';

        echo '<tr><td class="assoc-type">SKU</td>';
        foreach ($skus as $sku) {
            echo '<td>' . htmlspecialchars($sku) . '</td>';
        }
        echo '</tr>';

        echo '<tr><td class="assoc-type">Baujahr</td>';
        foreach ($skus as $sku) {
            echo '<td>' . htmlspecialchars($seriesBuildInfo[$sku]['buildYear']['display'] ?? '–') . '</td>';
        }
        echo '</tr>';

        echo '<tr><td class="assoc-type">Wurde gebaut bis</td>';
        foreach ($skus as $sku) {
            echo '<td>' . htmlspecialchars($seriesBuildInfo[$sku]['builtUntil']['display'] ?? '–') . '</td>';
        }
        echo '</tr>';

        foreach ($allAssocTypes as $type) {
            $typeLabel = $assocTypeLabels[$type] ?? $type;
            echo '<tr><td class="assoc-type">' . htmlspecialchars($typeLabel) . '</td>';

            foreach ($skus as $sku) {
                echo '<td>';
                if (!empty($matrix[$type][$sku])) {
                    foreach ($matrix[$type][$sku] as $item) {
                        $code = $item['code'];
                        if ($item['kind'] === 'product') {
                            $name = $linkedProductNames[$code] ?? $code;
                        } else {
                            $name = $linkedModelNames[$code] ?? $code;
                        }

                        echo '<span class="badge">';
                        echo htmlspecialchars($name);
                        if ($name !== $code) {
                            echo '<span class="badge-sku">' . htmlspecialchars($code) . '</span>';
                        }
                        echo '</span><br>';
                    }
                } else {
                    echo '<span style="color:#a0aec0;">– Keine –</span>';
                }
                echo '</td>';
            }
            echo '</tr>';
        }

        echo '</table>';
    } else {
        echo '<p>Für die ausgewählten Produkte wurden keine verknüpften Ausstattungen im PIM gefunden.</p>';
    }
    ?>
    </div>

<?php renderSiteFooter(); ?>

</body>
</html>
