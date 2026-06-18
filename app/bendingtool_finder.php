<?php
include('api_helper.php');
include('db_helper.php');
include('common.php');

// --- Cache-Datei (Produktmodelle) ---
$cacheFile   = sys_get_temp_dir() . '/bendingtool_finder_v10_cache.json';
$cacheTtlSec = 1800; // 30 Minuten
$forceReload = isset($_GET['reload']);
$loadDebug   = [];

/**
 * Familien für den Werkzeugfinder.
 * Priorität: PIM_BENDING_FAMILIES → DB-Tab „Abkantwerkzeuge“ → Fallback bendingtool_*
 */
function getBendingToolFamilies(): array {
    $allFamilies = getAkeneoFamilies();

    $explicitCodes = array_values(array_filter(array_map(
        'trim',
        explode(',', PIM_BENDING_FAMILIES)
    )));

    if (!empty($explicitCodes)) {
        return array_values(array_filter(
            $allFamilies,
            fn($f) => in_array($f['code'], $explicitCodes, true)
        ));
    }

    if (hasAnyFamilyConfig()) {
        $configured = getConfiguredFamiliesForContext('bending_tools') ?? [];
        if (!empty($configured)) {
            $allowed = array_column($configured, 'family_code');
            return array_values(array_filter(
                $allFamilies,
                fn($f) => in_array($f['code'], $allowed, true)
            ));
        }
    }

    return array_values(array_filter(
        $allFamilies,
        fn($f) => str_starts_with($f['code'], 'bendingtool_')
    ));
}

function modelToRow(array $model, array $familyLabels, array $heightOptions, array $seriesOptions): array {
    $fc   = resolveProductModelFamilyCode($model);
    $code = $model['code'] ?? '';

    return [
        'code'        => $code,
        'name'        => extractProductName($model) ?? $code,
        'familyCode'  => $fc,
        'familyLabel' => $familyLabels[$fc] ?? $fc,
        'imageUrl'    => $model['_imageUrl'] ?? null,
        'size'        => extractAttrValueFirst($model, PIM_BENDING_SIZE_ATTRS),
        'angle'       => extractAttrValueFirst($model, PIM_BENDING_ANGLE_ATTRS),
        'height'      => extractAttrValueFirstWithOptions($model, PIM_BENDING_HEIGHT_ATTRS, $heightOptions),
        'radius'      => extractBendingShoulderRadius($model),
        'series'      => extractAttrValueFirstWithOptions($model, PIM_BENDING_SERIES_ATTRS, $seriesOptions),
    ];
}

function bendingSeriesAttributeSearch(): array {
    $filter = trim(PIM_BENDING_SERIES_FILTER);
    if ($filter === '') {
        return [];
    }

    $seriesAttr = trim(explode(',', PIM_BENDING_SERIES_ATTRS)[0]);
    if ($seriesAttr === '') {
        return [];
    }

    return [
        $seriesAttr => [
            'operator' => 'CONTAINS',
            'value'    => $filter,
        ],
    ];
}

function matchesSeriesFilter(array $row): bool {
    $filter = trim(PIM_BENDING_SERIES_FILTER);
    if ($filter === '') {
        return true;
    }

    $needle = strtolower($filter);
    foreach (['raw', 'display'] as $key) {
        $val = strtolower((string)($row['series'][$key] ?? ''));
        if ($val !== '' && str_contains($val, $needle)) {
            return true;
        }
    }

    return false;
}

function loadBendingToolData(): array {
    global $loadDebug;

    $bendingFamilies = getBendingToolFamilies();
    $loadDebug['families'] = array_map(
        fn($f) => ($f['labels']['de_DE'] ?? $f['code']) . ' (' . $f['code'] . ')',
        $bendingFamilies
    );

    if (empty($bendingFamilies)) {
        $loadDebug['message'] = 'Keine passenden Familien gefunden (DB-Tab Abkantwerkzeuge, PIM_BENDING_FAMILIES oder Präfix bendingtool_).';
        return [];
    }

    $imageAttrs  = array_map('trim', explode(',', PIM_IMAGE_ATTRS));
    $filterAttrs = array_values(array_unique(array_merge(
        array_map('trim', explode(',', PIM_BENDING_SIZE_ATTRS)),
        array_map('trim', explode(',', PIM_BENDING_ANGLE_ATTRS)),
        ['product_name']
    )));
    $extraAttrs = array_merge(
        array_map('trim', explode(',', PIM_BENDING_HEIGHT_ATTRS)),
        array_map('trim', explode(',', PIM_BENDING_RADIUS_ATTRS)),
        array_map('trim', explode(',', PIM_BENDING_SERIES_ATTRS)),
        ['bendingtool_die_1v_shoulder_radius', 'bendingtool_die_2v_shoulder_radius']
    );
    $onlyAttrs = array_values(array_unique(array_merge($filterAttrs, $extraAttrs, $imageAttrs)));

    $familyCodes  = array_column($bendingFamilies, 'code');
    $familyLabels = getAkeneoFamilyLabelMap();

    $heightAttr = trim(explode(',', PIM_BENDING_HEIGHT_ATTRS)[0]);
    $seriesAttr = trim(explode(',', PIM_BENDING_SERIES_ATTRS)[0]);
    $heightOptions = getAkeneoAttributeOptionLabels($heightAttr);
    $seriesOptions = getAkeneoAttributeOptionLabels($seriesAttr);

    $seriesSearch = bendingSeriesAttributeSearch();
    $loadDebug['series_filter'] = trim(PIM_BENDING_SERIES_FILTER) ?: null;
    if (!empty($seriesSearch)) {
        $loadDebug['series_search'] = $seriesSearch;
    }

    // 1) Produktmodelle — mit Serie-Filter direkt per API (schneller), sonst robust ohne Attribute zuerst
    if (!empty($seriesSearch)) {
        $allModels = getAkeneoProductModelsByFamilies($familyCodes, $onlyAttrs, false, $seriesSearch);
        $loadDebug['api_error'] = getLastApiError();
        $loadDebug['source'] = 'product-models-series-filter';
    } else {
        $allModels = getAkeneoProductModelsByFamilies($familyCodes, [], false);
        $loadDebug['api_error'] = getLastApiError();

        if (empty($allModels)) {
            $allModels = getAkeneoProductModelsByFamilies($familyCodes, $onlyAttrs, false);
            $loadDebug['api_error'] = getLastApiError();
        }
        $loadDebug['source'] = 'product-models';
    }

    $loadDebug['product_models_total'] = count($allModels);

    $models = filterLeafProductModels($allModels);
    $loadDebug['product_models_leaf'] = count($models);
    if (empty($loadDebug['source'])) {
        $loadDebug['source'] = 'product-models';
    }

    // Blatt-Filter zu aggressiv → alle Modelle nutzen
    if (empty($models) && !empty($allModels)) {
        $models = $allModels;
        $loadDebug['source'] .= '-all';
    }

    // 2) Fallback: Root-Modelle
    if (empty($models)) {
        $models = getAkeneoProductModelsByFamilies($familyCodes, $onlyAttrs, true, $seriesSearch);
        $loadDebug['product_models_root'] = count($models);
        if (!empty($models)) {
            $loadDebug['source'] = 'product-models-root';
        }
    }

    // 3) Fallback ohne Serie-API-Filter (Serie wird nach Anreicherung clientseitig gefiltert)
    if (empty($models) && !empty($seriesSearch)) {
        $allModels = getAkeneoProductModelsByFamilies($familyCodes, $onlyAttrs, false);
        $loadDebug['api_error'] = getLastApiError();
        $loadDebug['product_models_total_fallback'] = count($allModels);
        $models = filterLeafProductModels($allModels);
        if (empty($models) && !empty($allModels)) {
            $models = $allModels;
        }
        if (!empty($models)) {
            $loadDebug['source'] = 'product-models-fallback-no-series-search';
        }
    }

    // 4) Fallback: Parent-Codes aus Produkten (nur wenn Modell-API leer — kein Massen-Index)
    if (empty($models)) {
        $products = getAkeneoProductsByFamilies($familyCodes, $onlyAttrs);
        $loadDebug['products_total'] = count($products);

        $parentCodes = [];
        foreach ($products as $product) {
            $parent = $product['parent'] ?? null;
            if ($parent && !isset($parentCodes[$parent])) {
                $parentCodes[$parent] = true;
            }
        }

        $loadDebug['unique_parents'] = count($parentCodes);
        $models = [];

        foreach (array_keys($parentCodes) as $parentCode) {
            $model = getAkeneoProductModel($parentCode, $onlyAttrs);
            if ($model) {
                $models[] = enrichProductModelWithAncestors($model, $onlyAttrs);
            }
        }

        if (!empty($models)) {
            $loadDebug['source'] = 'products-by-parent';
        }
    }

    if (empty($models)) {
        $loadDebug['message'] = 'Keine Produktmodelle gefunden. Prüfen Sie Familien-Zuweisung (Tab Abkantwerkzeuge) und PIM-Struktur.';
        if (getLastApiError()) {
            $loadDebug['message'] .= ' API: ' . getLastApiError();
        }
        return [];
    }

    $models = array_map(
        fn($m) => enrichProductModelWithAncestors($m, $onlyAttrs),
        $models
    );

    $rows = array_map(
        fn($m) => modelToRow($m, $familyLabels, $heightOptions, $seriesOptions),
        $models
    );
    $rows = array_values(array_filter($rows, 'matchesSeriesFilter'));
    $loadDebug['rows_after_series_filter'] = count($rows);
    usort($rows, fn($a, $b) => strcasecmp($a['name'], $b['name']));

    return $rows;
}

// Cache lesen oder neu laden
$cacheAge  = null;
$cacheTime = null;
if (!$forceReload && file_exists($cacheFile)) {
    $age = time() - filemtime($cacheFile);
    if ($age < $cacheTtlSec) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if (is_array($cached) && !empty($cached)) {
            $rows      = $cached;
            $cacheAge  = $age;
            $cacheTime = filemtime($cacheFile);
        }
    }
}

if (!isset($rows)) {
    $rows = loadBendingToolData();
    if (!empty($rows)) {
        file_put_contents($cacheFile, json_encode($rows));
    } elseif (file_exists($cacheFile)) {
        unlink($cacheFile);
    }
    $cacheTime = time();
    $cacheAge  = 0;
}

// Wenn nach reload weitergeleitet wird, damit F5 nicht neu lädt
if ($forceReload) {
    header('Location: bendingtool_finder.php');
    exit;
}

// --- Eindeutige Filterwerte ---
function collectUniqueFilterValues(array $rows, string $field, bool $numericSort = false): array {
    $values = array_values(array_unique(array_filter(
        array_map(fn($r) => $r[$field]['raw'] ?? null, $rows),
        fn($v) => $v !== null && $v !== ''
    )));

    if ($numericSort) {
        sort($values, SORT_NUMERIC);
    } else {
        sort($values, SORT_NATURAL | SORT_FLAG_CASE);
    }

    $labels = [];
    foreach ($rows as $row) {
        $raw = $row[$field]['raw'] ?? null;
        if ($raw !== null && $raw !== '') {
            $labels[$raw] = $row[$field]['display'];
        }
    }

    return ['values' => $values, 'labels' => $labels];
}

function collectUniqueFamilies(array $rows): array {
    $labels = [];
    foreach ($rows as $row) {
        $code = $row['familyCode'] ?? '';
        if ($code === '') {
            continue;
        }
        $labels[$code] = $row['familyLabel'] ?? $code;
    }

    $values = array_keys($labels);
    usort($values, fn($a, $b) => strcasecmp($labels[$a], $labels[$b]));

    return ['values' => $values, 'labels' => $labels];
}

$bendingToolFamilies = getBendingToolFamilies();
$familyLabelsFilter  = getAkeneoFamilyLabelMap();
$uniqueFamilies      = array_column($bendingToolFamilies, 'code');
foreach ($uniqueFamilies as $code) {
    if (!isset($familyLabelsFilter[$code])) {
        $familyLabelsFilter[$code] = $code;
    }
}
usort($uniqueFamilies, fn($a, $b) => strcasecmp(
    $familyLabelsFilter[$a] ?? $a,
    $familyLabelsFilter[$b] ?? $b
));

$rowFamilyFilter = collectUniqueFamilies($rows);
foreach ($rowFamilyFilter['values'] as $code) {
    if (!in_array($code, $uniqueFamilies, true)) {
        $uniqueFamilies[] = $code;
        $familyLabelsFilter[$code] = $rowFamilyFilter['labels'][$code] ?? $code;
    }
}

$sizeFilter   = collectUniqueFilterValues($rows, 'size', true);
$angleFilter  = collectUniqueFilterValues($rows, 'angle', true);
$heightFilter = collectUniqueFilterValues($rows, 'height', true);
$radiusFilter = collectUniqueFilterValues($rows, 'radius', true);
$seriesFilter = collectUniqueFilterValues($rows, 'series', false);

$uniqueSizes   = $sizeFilter['values'];
$uniqueAngles  = $angleFilter['values'];
$uniqueHeights = $heightFilter['values'];
$uniqueRadii   = $radiusFilter['values'];
$uniqueSeries  = $seriesFilter['values'];

$sizeLabels   = $sizeFilter['labels'];
$angleLabels  = $angleFilter['labels'];
$heightLabels = $heightFilter['labels'];
$radiusLabels = $radiusFilter['labels'];
$seriesLabels = $seriesFilter['labels'];

$colCount = 8;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Werkzeugfinder – AMADA</title>
    <?php renderBaseStyles(); ?>
    <style>

        .page-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin: 0 auto 16px;
            max-width: 1400px;
        }
        h1 {
            font-size: 22px;
            margin: 0;
            border-bottom: 3px solid var(--amada-red);
            padding-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .page-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .cache-info {
            font-size: 12px;
            color: #718096;
        }
        .filter-bar {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px 20px;
            margin: 0 auto 18px;
            max-width: 1400px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: flex-end;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .filter-group select,
        .filter-group input[type="search"] {
            padding: 8px 10px;
            border: 1px solid var(--border);
            border-radius: 5px;
            font-size: 14px;
            min-width: 130px;
            background: #fff;
            outline: none;
        }
        .filter-group select:focus,
        .filter-group input[type="search"]:focus {
            border-color: #4a5568;
        }
        .filter-count {
            font-size: 13px;
            color: #718096;
            margin-left: auto;
            align-self: center;
            white-space: nowrap;
        }
        .btn-reset {
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid var(--border);
            border-radius: 5px;
            background: #fff;
            cursor: pointer;
            color: var(--dark-gray);
            align-self: flex-end;
        }
        .btn-reset:hover { background: var(--hover-bg); }

        /* ---- Tabelle ---- */
        .table-wrap {
            background: #fff;
            border-radius: 8px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            overflow-x: auto;
            max-width: 1400px;
            margin: 0 auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1100px;
        }
        thead th {
            background: var(--table-head-bg);
            color: #fff;
            padding: 10px 14px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer;
            user-select: none;
        }
        thead th:hover { background: var(--table-head-hover); }
        thead th .sort-icon { margin-left: 4px; opacity: 0.5; font-size: 11px; }
        thead th.sorted-asc  .sort-icon::after { content: '▲'; opacity: 1; }
        thead th.sorted-desc .sort-icon::after { content: '▼'; opacity: 1; }
        thead th:not(.sorted-asc):not(.sorted-desc) .sort-icon::after { content: '⇅'; }

        tbody tr { transition: background 0.1s; }
        tbody tr.model-row { cursor: pointer; }
        tbody tr.model-row:hover { background: var(--hover-bg); }
        tbody tr.model-row.expanded { background: #edf2f7; }
        tbody tr.hidden  { display: none; }
        tbody tr.disabled-row td { color: #a0aec0; }

        tr.variant-row td {
            padding: 0;
            background: #f7fafc;
            border-bottom: 2px solid #cbd5e0;
        }
        .variant-panel {
            padding: 14px 18px 18px 72px;
        }
        .variant-panel h3 {
            margin: 0 0 10px;
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .variant-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 520px;
        }
        .variant-table th {
            background: #e2e8f0;
            color: #2d3748;
            padding: 8px 12px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
        }
        .variant-table td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            background: #fff;
        }
        .variant-loading,
        .variant-empty,
        .variant-error {
            font-size: 13px;
            color: #718096;
            font-style: italic;
        }
        .variant-error { color: #c53030; font-style: normal; }
        .expand-hint {
            display: block;
            font-size: 11px;
            color: #a0aec0;
            margin-top: 2px;
        }
        .model-row.expanded .expand-hint { color: #4a5568; }

        td {
            padding: 9px 14px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
            vertical-align: middle;
        }
        td:last-child { border-right: none; }

        .product-thumb {
            width: 48px;
            height: 48px;
            object-fit: contain;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: var(--light-bg);
            display: block;
        }
        .thumb-placeholder {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--hover-bg);
            border: 1px solid var(--border);
            border-radius: 4px;
            font-size: 20px;
            color: #cbd5e0;
        }
        .sku {
            font-weight: 600;
            font-size: 13px;
        }
        .model-name {
            font-size: 14px;
            color: var(--dark-gray);
        }
        .model-code {
            display: block;
            font-size: 11px;
            color: #718096;
            margin-top: 2px;
        }
        .family-badge {
            font-size: 11px;
            background: var(--hover-bg);
            border: 1px solid var(--border);
            border-radius: 3px;
            padding: 2px 6px;
            color: #718096;
            white-space: nowrap;
        }
        .status-active   { color: #276749; font-weight: 600; font-size: 12px; }
        .status-disabled { color: #a0aec0; font-size: 12px; }

        .page-hint {
            font-size: 13px;
            color: #718096;
            margin: 0 auto 18px;
            max-width: 1400px;
            line-height: 1.5;
        }

        .val-highlight {
            font-weight: 700;
            font-size: 15px;
            color: var(--dark-gray);
        }
        .no-results {
            text-align: center;
            padding: 40px;
            color: #a0aec0;
            font-style: italic;
            font-size: 15px;
        }
        .debug-box {
            max-width: 1400px;
            margin: 0 auto 16px;
            padding: 14px 16px;
            background: #fffbeb;
            border: 1px solid #f6e05e;
            border-radius: 6px;
            font-size: 13px;
            color: #744210;
        }
        .debug-box code { font-size: 12px; }
    </style>
</head>
<body>

<?php renderSiteHeader('finder'); ?>

<div class="page-head">
    <h1>Werkzeugfinder</h1>
    <div class="page-actions">
        <?php if ($cacheTime): ?>
        <span class="cache-info">
            Daten vom <?php echo date('d.m.Y H:i', $cacheTime); ?> Uhr
            <?php if ($cacheAge !== null && $cacheAge > 0): ?>
                (vor <?php echo round($cacheAge / 60); ?> Min.)
            <?php endif; ?>
        </span>
        <?php endif; ?>
        <a href="bendingtool_finder.php?reload=1" class="back-link" title="Daten direkt aus dem PIM neu laden">↺ Neu laden</a>
    </div>
</div>

<p class="page-hint">
    Es werden <strong>Produktmodelle</strong> angezeigt — klicken Sie auf ein Modell, um alle zugehörigen Artikel
    mit den jeweiligen Werkzeuglängen anzuzeigen. Filtern Sie nach V-Öffnung, Winkel, Werkzeughöhe, Radius und Serie.
    <?php if (trim(PIM_BENDING_SERIES_FILTER) !== ''): ?>
        Aktuell nur Serie <strong><?php echo htmlspecialchars(trim(PIM_BENDING_SERIES_FILTER)); ?></strong>
        (über <code>PIM_BENDING_SERIES_FILTER</code>).
    <?php endif; ?>
</p>

<?php if (empty($rows) && isAdminEnabled() && !empty($loadDebug)): ?>
<div class="debug-box">
    <strong>Admin-Diagnose:</strong>
    <?php if (!empty($loadDebug['message'])): ?>
        <?php echo htmlspecialchars($loadDebug['message']); ?><br>
    <?php endif; ?>
    Familien: <?php echo count($loadDebug['families'] ?? []); ?>
    <?php if (!empty($loadDebug['families'])): ?>
        — <code><?php echo htmlspecialchars(implode(', ', $loadDebug['families'])); ?></code>
    <?php endif; ?>
    <br>
    Produktmodelle gesamt: <?php echo (int)($loadDebug['product_models_total'] ?? 0); ?>,
    Blatt-Modelle: <?php echo (int)($loadDebug['product_models_leaf'] ?? 0); ?>,
    Root-Modelle: <?php echo (int)($loadDebug['product_models_root'] ?? 0); ?>,
    Produkte: <?php echo (int)($loadDebug['products_total'] ?? 0); ?>,
    Parent-Codes: <?php echo (int)($loadDebug['unique_parents'] ?? 0); ?>
    <?php if (!empty($loadDebug['source'])): ?>
        <br>Quelle: <code><?php echo htmlspecialchars($loadDebug['source']); ?></code>
    <?php endif; ?>
    <?php if (!empty($loadDebug['series_filter'])): ?>
        <br>Serie-Filter: <code><?php echo htmlspecialchars($loadDebug['series_filter']); ?></code>
        — Zeilen nach Filter: <?php echo (int)($loadDebug['rows_after_series_filter'] ?? 0); ?>
    <?php endif; ?>
    <?php if (!empty($loadDebug['api_error'])): ?>
        <br>API-Fehler: <code><?php echo htmlspecialchars($loadDebug['api_error']); ?></code>
    <?php endif; ?>
    <br><small>Hinweis: PIM-URLs wie <code>…/product-model/2566</code> nutzen eine interne ID — die REST-API benötigt den Modell-<strong>Code</strong> (Feld „Code“ in Akeneo).</small>
</div>
<?php endif; ?>

<!-- Filter-Leiste -->
<div class="filter-bar">
    <div class="filter-group">
        <label>Modell suchen</label>
        <input type="search" id="filterSearch" placeholder="Name oder Modellcode …" oninput="applyFilters()">
    </div>
    <div class="filter-group">
        <label>Familie</label>
        <select id="filterFamily" onchange="applyFilters()">
            <option value="">Alle</option>
            <?php foreach ($uniqueFamilies as $val): ?>
                <option value="<?php echo htmlspecialchars((string)$val); ?>">
                    <?php echo htmlspecialchars($familyLabelsFilter[$val] ?? (string)$val); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-group">
        <label>V-Öffnung</label>
        <select id="filterSize" onchange="applyFilters()">
            <option value="">Alle</option>
            <?php foreach ($uniqueSizes as $val): ?>
                <option value="<?php echo htmlspecialchars((string)$val); ?>">
                    <?php echo htmlspecialchars($sizeLabels[$val] ?? (string)$val); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-group">
        <label>Winkel</label>
        <select id="filterAngle" onchange="applyFilters()">
            <option value="">Alle</option>
            <?php foreach ($uniqueAngles as $val): ?>
                <option value="<?php echo htmlspecialchars((string)$val); ?>">
                    <?php echo htmlspecialchars($angleLabels[$val] ?? (string)$val); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-group">
        <label>Werkzeughöhe</label>
        <select id="filterHeight" onchange="applyFilters()">
            <option value="">Alle</option>
            <?php foreach ($uniqueHeights as $val): ?>
                <option value="<?php echo htmlspecialchars((string)$val); ?>">
                    <?php echo htmlspecialchars($heightLabels[$val] ?? (string)$val); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-group">
        <label>Radius</label>
        <select id="filterRadius" onchange="applyFilters()">
            <option value="">Alle</option>
            <?php foreach ($uniqueRadii as $val): ?>
                <option value="<?php echo htmlspecialchars((string)$val); ?>">
                    <?php echo htmlspecialchars($radiusLabels[$val] ?? (string)$val); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-group">
        <label>Serie</label>
        <select id="filterSeries" onchange="applyFilters()">
            <option value="">Alle</option>
            <?php foreach ($uniqueSeries as $val): ?>
                <option value="<?php echo htmlspecialchars((string)$val); ?>">
                    <?php echo htmlspecialchars($seriesLabels[$val] ?? (string)$val); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn-reset" onclick="resetFilters()">↺ Filter zurücksetzen</button>
    <span class="filter-count" id="resultCount"></span>
</div>

<!-- Modell-Tabelle -->
<div class="table-wrap">
    <table id="productTable">
        <thead>
            <tr>
                <th style="width:60px;cursor:default;"></th>
                <th onclick="sortTable(1)" data-col="1">Bezeichnung <span class="sort-icon"></span></th>
                <th onclick="sortTable(2)" data-col="2">Familie <span class="sort-icon"></span></th>
                <th onclick="sortTable(3)" data-col="3">V-Öffnung <span class="sort-icon"></span></th>
                <th onclick="sortTable(4)" data-col="4">Winkel <span class="sort-icon"></span></th>
                <th onclick="sortTable(5)" data-col="5">Werkzeughöhe <span class="sort-icon"></span></th>
                <th onclick="sortTable(6)" data-col="6">Radius <span class="sort-icon"></span></th>
                <th onclick="sortTable(7)" data-col="7">Serie <span class="sort-icon"></span></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="<?php echo $colCount; ?>" class="no-results">
                Keine Werkzeugmodelle gefunden.<br>
                Familien müssen im Admin unter <strong>Abkantwerkzeuge</strong> zugewiesen sein
                (oder Code beginnt mit <code>bendingtool_</code>).<br>
                Bitte <a href="bendingtool_finder.php?reload=1">Neu laden</a> nach Konfiguration.
            </td></tr>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <tr class="model-row"
                data-code="<?php echo strtolower(htmlspecialchars($row['code'])); ?>"
                data-model="<?php echo htmlspecialchars($row['code']); ?>"
                data-name="<?php echo strtolower(htmlspecialchars($row['name'])); ?>"
                data-family="<?php echo htmlspecialchars($row['familyCode']); ?>"
                data-family-label="<?php echo strtolower(htmlspecialchars($row['familyLabel'])); ?>"
                data-size="<?php echo htmlspecialchars((string)($row['size']['raw'] ?? '')); ?>"
                data-angle="<?php echo htmlspecialchars((string)($row['angle']['raw'] ?? '')); ?>"
                data-height="<?php echo htmlspecialchars((string)($row['height']['raw'] ?? '')); ?>"
                data-radius="<?php echo htmlspecialchars((string)($row['radius']['raw'] ?? '')); ?>"
                data-series="<?php echo htmlspecialchars((string)($row['series']['raw'] ?? '')); ?>">
                <td>
                    <?php if ($row['imageUrl']): ?>
                        <img class="product-thumb"
                             src="<?php echo htmlspecialchars($row['imageUrl']); ?>"
                             alt="<?php echo htmlspecialchars($row['name']); ?>"
                             loading="lazy"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <span class="thumb-placeholder" style="display:none;">📷</span>
                    <?php else: ?>
                        <span class="thumb-placeholder">📷</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="model-name"><?php echo htmlspecialchars($row['name']); ?></span>
                    <span class="model-code"><?php echo htmlspecialchars($row['code']); ?></span>
                    <span class="expand-hint">▶ Artikel / Längen anzeigen</span>
                </td>
                <td><span class="family-badge"><?php echo htmlspecialchars($row['familyLabel'] ?: ($row['familyCode'] ?: '–')); ?></span></td>
                <td><span class="val-highlight"><?php echo htmlspecialchars($row['size']['display']); ?></span></td>
                <td><span class="val-highlight"><?php echo htmlspecialchars($row['angle']['display']); ?></span></td>
                <td><span class="val-highlight"><?php echo htmlspecialchars($row['height']['display']); ?></span></td>
                <td><span class="val-highlight"><?php echo htmlspecialchars($row['radius']['display']); ?></span></td>
                <td><?php echo htmlspecialchars($row['series']['display']); ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
const tbody = document.querySelector('#productTable tbody');
const colCount = <?php echo (int)$colCount; ?>;
const variantCache = new Map();

function applyFilters() {
    const search = document.getElementById('filterSearch').value.toLowerCase().trim();
    const family = document.getElementById('filterFamily').value;
    const size   = document.getElementById('filterSize').value;
    const angle  = document.getElementById('filterAngle').value;
    const height = document.getElementById('filterHeight').value;
    const radius = document.getElementById('filterRadius').value;
    const series = document.getElementById('filterSeries').value;

    closeAllVariants();

    let visible = 0;
    tbody.querySelectorAll('tr.model-row').forEach(row => {
        const matchSearch = !search
            || row.dataset.code.includes(search)
            || row.dataset.name.includes(search);
        const matchFamily = !family || row.dataset.family === family;
        const matchSize   = !size   || row.dataset.size   === size;
        const matchAngle  = !angle  || row.dataset.angle  === angle;
        const matchHeight = !height || row.dataset.height === height;
        const matchRadius = !radius || row.dataset.radius === radius;
        const matchSeries = !series || row.dataset.series === series;

        if (matchSearch && matchFamily && matchSize && matchAngle && matchHeight && matchRadius && matchSeries) {
            row.classList.remove('hidden');
            visible++;
        } else {
            row.classList.add('hidden');
        }
    });

    const total = tbody.querySelectorAll('tr.model-row').length;
    document.getElementById('resultCount').textContent =
        visible === total ? `${total} Modelle` : `${visible} von ${total} Modellen`;
}

function resetFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterFamily').value  = '';
    document.getElementById('filterSize').value    = '';
    document.getElementById('filterAngle').value   = '';
    document.getElementById('filterHeight').value  = '';
    document.getElementById('filterRadius').value  = '';
    document.getElementById('filterSeries').value  = '';
    applyFilters();
}

function closeAllVariants() {
    tbody.querySelectorAll('tr.variant-row').forEach(row => row.remove());
    tbody.querySelectorAll('tr.model-row.expanded').forEach(row => row.classList.remove('expanded'));
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function renderVariantTable(variants) {
    if (!variants.length) {
        return '<div class="variant-empty">Keine Artikel zu diesem Modell gefunden.</div>';
    }

    const rows = variants.map(variant => {
        const statusClass = variant.status?.raw === 'active' ? 'status-active' : 'status-disabled';
        return `<tr>
            <td class="sku">${escapeHtml(variant.identifier)}</td>
            <td class="sku">${escapeHtml(variant.sapNumber?.display ?? '–')}</td>
            <td><span class="val-highlight">${escapeHtml(variant.length?.display ?? '–')}</span></td>
            <td>${escapeHtml(variant.radius?.display ?? '–')}</td>
            <td><span class="${statusClass}">${escapeHtml(variant.status?.display ?? '–')}</span></td>
        </tr>`;
    }).join('');

    return `<table class="variant-table">
        <thead>
            <tr>
                <th>Artikelnummer</th>
                <th>SAP-Nummer</th>
                <th>Länge</th>
                <th>Radius</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>${rows}</tbody>
    </table>`;
}

async function toggleVariants(row) {
    const modelCode = row.dataset.model;
    const existing = row.nextElementSibling;

    if (existing?.classList.contains('variant-row')) {
        existing.remove();
        row.classList.remove('expanded');
        return;
    }

    closeAllVariants();
    row.classList.add('expanded');

    const detailRow = document.createElement('tr');
    detailRow.className = 'variant-row';
    detailRow.innerHTML = `<td colspan="${colCount}">
        <div class="variant-panel">
            <h3>Artikel / Längen</h3>
            <div class="variant-loading">Lade Artikel …</div>
        </div>
    </td>`;
    row.after(detailRow);

    const panel = detailRow.querySelector('.variant-panel');

    try {
        let payload = variantCache.get(modelCode);
        if (!payload) {
            const response = await fetch(`bendingtool_finder_api.php?model=${encodeURIComponent(modelCode)}`);
            payload = await response.json();
            if (!response.ok) {
                throw new Error(payload.error || 'Artikel konnten nicht geladen werden.');
            }
            variantCache.set(modelCode, payload);
        }

        panel.innerHTML = `<h3>Artikel / Längen (${payload.variants.length})</h3>${renderVariantTable(payload.variants)}`;
    } catch (error) {
        panel.innerHTML = `<h3>Artikel / Längen</h3><div class="variant-error">${escapeHtml(error.message)}</div>`;
    }
}

tbody.addEventListener('click', (event) => {
    const row = event.target.closest('tr.model-row');
    if (!row || row.classList.contains('hidden')) {
        return;
    }
    toggleVariants(row);
});

// Tabellen-Sortierung
let sortCol = -1, sortAsc = true;

function sortTable(col) {
    closeAllVariants();

    const rows = Array.from(tbody.querySelectorAll('tr.model-row'));

    if (sortCol === col) { sortAsc = !sortAsc; }
    else { sortCol = col; sortAsc = true; }

    document.querySelectorAll('thead th').forEach((th, i) => {
        th.classList.remove('sorted-asc', 'sorted-desc');
        if (i === col) th.classList.add(sortAsc ? 'sorted-asc' : 'sorted-desc');
    });

    const dataAttrMap = {
        1: 'name',
        2: 'familyLabel',
        3: 'size',
        4: 'angle',
        5: 'height',
        6: 'radius',
        7: 'series',
    };
    const attr = dataAttrMap[col] ?? 'name';

    rows.sort((a, b) => {
        const va = a.dataset[attr] ?? '';
        const vb = b.dataset[attr] ?? '';
        const numA = parseFloat(va), numB = parseFloat(vb);
        let cmp = (!isNaN(numA) && !isNaN(numB) && va !== '' && vb !== '')
            ? numA - numB
            : va.localeCompare(vb, 'de');
        return sortAsc ? cmp : -cmp;
    });

    rows.forEach(r => tbody.appendChild(r));
}

applyFilters();
</script>

<?php renderSiteFooter(); ?>

</body>
</html>
