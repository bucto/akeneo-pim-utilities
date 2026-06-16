<?php
include('api_helper.php');
include('db_helper.php');
include('common.php');

// --- Cache-Datei (Produktmodelle) ---
$cacheFile   = sys_get_temp_dir() . '/bendingtool_finder_v3_cache.json';
$cacheTtlSec = 1800; // 30 Minuten
$forceReload = isset($_GET['reload']);
$loadDebug   = [];

/**
 * Familien für den Werkzeugfinder: DB-Tab „Abkantwerkzeuge“ oder Fallback bendingtool_*
 */
function getBendingToolFamilies(): array {
    $allFamilies = getAkeneoFamilies();

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

function modelToRow(array $model, array $familyLabels): array {
    $fc   = $model['family'] ?? '';
    $code = $model['code'] ?? '';

    return [
        'code'        => $code,
        'name'        => extractProductName($model) ?? $code,
        'familyCode'  => $fc,
        'familyLabel' => $familyLabels[$fc] ?? $fc,
        'imageUrl'    => $model['_imageUrl'] ?? null,
        'size'        => extractAttrValue($model, 'bendingtool_die_1v_size'),
        'angle'       => extractAttrValue($model, 'bendingtool_die_1v_angle'),
    ];
}

function loadBendingToolData(): array {
    global $loadDebug;

    $bendingFamilies = getBendingToolFamilies();
    $loadDebug['families'] = array_map(
        fn($f) => ($f['labels']['de_DE'] ?? $f['code']) . ' (' . $f['code'] . ')',
        $bendingFamilies
    );

    if (empty($bendingFamilies)) {
        $loadDebug['message'] = 'Keine passenden Familien gefunden (DB-Tab Abkantwerkzeuge oder Präfix bendingtool_).';
        return [];
    }

    $imageAttrs  = array_map('trim', explode(',', PIM_IMAGE_ATTRS));
    $filterAttrs = ['bendingtool_die_1v_size', 'bendingtool_die_1v_angle', 'product_name'];
    $onlyAttrs   = array_unique(array_merge($filterAttrs, $imageAttrs));

    $familyCodes  = array_column($bendingFamilies, 'code');
    $familyLabels = [];
    foreach ($bendingFamilies as $f) {
        $familyLabels[$f['code']] = $f['labels']['de_DE'] ?? $f['code'];
    }

    // 1) Produktmodelle laden — zuerst ohne Attribut-Filter (robuster), dann mit Attributen
    $allModels = getAkeneoProductModelsByFamilies($familyCodes, [], false);
    $loadDebug['api_error'] = getLastApiError();

    if (empty($allModels)) {
        $allModels = getAkeneoProductModelsByFamilies($familyCodes, $onlyAttrs, false);
        $loadDebug['api_error'] = getLastApiError();
    }

    $loadDebug['product_models_total'] = count($allModels);

    $models = filterLeafProductModels($allModels);
    $loadDebug['product_models_leaf'] = count($models);
    $loadDebug['source'] = 'product-models';

    // 2) Fallback: Root-Modelle, falls Blatt-Filter nichts liefert
    if (empty($models)) {
        $models = getAkeneoProductModelsByFamilies($familyCodes, $onlyAttrs, true);
        $loadDebug['product_models_root'] = count($models);
        if (!empty($models)) {
            $loadDebug['source'] = 'product-models-root';
        }
    }

    // 3) Fallback: Parent-Codes aus Varianten-Produkten → Modell laden
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
                $models[] = $model;
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

    $rows = array_map(fn($m) => modelToRow($m, $familyLabels), $models);
    usort($rows, fn($a, $b) => strcasecmp($a['name'], $b['name']));

    return $rows;
}

// Cache lesen oder neu laden
$cacheAge  = null;
$cacheTime = null;
if (!$forceReload && file_exists($cacheFile)) {
    $age = time() - filemtime($cacheFile);
    if ($age < $cacheTtlSec) {
        $rows      = json_decode(file_get_contents($cacheFile), true) ?? [];
        $cacheAge  = $age;
        $cacheTime = filemtime($cacheFile);
    }
}

if (!isset($rows)) {
    $rows = loadBendingToolData();
    file_put_contents($cacheFile, json_encode($rows));
    $cacheTime = time();
    $cacheAge  = 0;
}

// Wenn nach reload weitergeleitet wird, damit F5 nicht neu lädt
if ($forceReload) {
    header('Location: bendingtool_finder.php');
    exit;
}

// --- Eindeutige Filterwerte (numerisch sortiert) ---
$uniqueSizes = array_values(array_unique(array_filter(
    array_map(fn($r) => $r['size']['raw'], $rows),
    fn($v) => $v !== null
)));
$uniqueAngles = array_values(array_unique(array_filter(
    array_map(fn($r) => $r['angle']['raw'], $rows),
    fn($v) => $v !== null
)));
sort($uniqueSizes,  SORT_NUMERIC);
sort($uniqueAngles, SORT_NUMERIC);

// Anzeige-Labels für Filter (raw → display)
$sizeLabels  = [];
$angleLabels = [];
foreach ($rows as $r) {
    if ($r['size']['raw']  !== null) $sizeLabels[$r['size']['raw']]   = $r['size']['display'];
    if ($r['angle']['raw'] !== null) $angleLabels[$r['angle']['raw']] = $r['angle']['display'];
}
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
            min-width: 160px;
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
            min-width: 700px;
        }
        thead th {
            background: var(--dark-gray);
            color: #fff;
            padding: 10px 14px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer;
            user-select: none;
        }
        thead th:hover { background: #3d4f66; }
        thead th .sort-icon { margin-left: 4px; opacity: 0.5; font-size: 11px; }
        thead th.sorted-asc  .sort-icon::after { content: '▲'; opacity: 1; }
        thead th.sorted-desc .sort-icon::after { content: '▼'; opacity: 1; }
        thead th:not(.sorted-asc):not(.sorted-desc) .sort-icon::after { content: '⇅'; }

        tbody tr { transition: background 0.1s; }
        tbody tr:hover { background: var(--hover-bg); }
        tbody tr.hidden  { display: none; }
        tbody tr.disabled-row td { color: #a0aec0; }

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
    Es werden nur <strong>Produktmodelle</strong> angezeigt — nicht die einzelnen Varianten mit unterschiedlichen Werkzeuglängen oder Radien.
    So finden Sie schneller das passende Werkzeugmodell; die konkrete Länge wählen Sie anschließend im PIM oder Katalog.
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
        <label>V-Öffnung (bendingtool_die_1v_size)</label>
        <select id="filterSize" onchange="applyFilters()">
            <option value="">Alle</option>
            <?php foreach ($uniqueSizes as $val): ?>
                <option value="<?php echo htmlspecialchars($val); ?>">
                    <?php echo htmlspecialchars($sizeLabels[$val] ?? $val); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-group">
        <label>Winkel (bendingtool_die_1v_angle)</label>
        <select id="filterAngle" onchange="applyFilters()">
            <option value="">Alle</option>
            <?php foreach ($uniqueAngles as $val): ?>
                <option value="<?php echo htmlspecialchars($val); ?>">
                    <?php echo htmlspecialchars($angleLabels[$val] ?? $val); ?>
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
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="5" class="no-results">
                Keine Werkzeugmodelle gefunden.<br>
                Familien müssen im Admin unter <strong>Abkantwerkzeuge</strong> zugewiesen sein
                (oder Code beginnt mit <code>bendingtool_</code>).<br>
                Bitte <a href="bendingtool_finder.php?reload=1">Neu laden</a> nach Konfiguration.
            </td></tr>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <tr data-code="<?php echo strtolower(htmlspecialchars($row['code'])); ?>"
                data-name="<?php echo strtolower(htmlspecialchars($row['name'])); ?>"
                data-family="<?php echo strtolower(htmlspecialchars($row['familyLabel'])); ?>"
                data-size="<?php echo htmlspecialchars($row['size']['raw'] ?? ''); ?>"
                data-angle="<?php echo htmlspecialchars($row['angle']['raw'] ?? ''); ?>">
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
                </td>
                <td><span class="family-badge"><?php echo htmlspecialchars($row['familyLabel']); ?></span></td>
                <td><span class="val-highlight"><?php echo htmlspecialchars($row['size']['display']); ?></span></td>
                <td><span class="val-highlight"><?php echo htmlspecialchars($row['angle']['display']); ?></span></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
const tbody = document.querySelector('#productTable tbody');

function applyFilters() {
    const search = document.getElementById('filterSearch').value.toLowerCase().trim();
    const size   = document.getElementById('filterSize').value;
    const angle  = document.getElementById('filterAngle').value;

    let visible = 0;
    tbody.querySelectorAll('tr[data-code]').forEach(row => {
        const matchSearch = !search
            || row.dataset.code.includes(search)
            || row.dataset.name.includes(search);
        const matchSize   = !size  || row.dataset.size  === size;
        const matchAngle  = !angle || row.dataset.angle === angle;

        if (matchSearch && matchSize && matchAngle) {
            row.classList.remove('hidden');
            visible++;
        } else {
            row.classList.add('hidden');
        }
    });

    const total = tbody.querySelectorAll('tr[data-code]').length;
    document.getElementById('resultCount').textContent =
        visible === total ? `${total} Modelle` : `${visible} von ${total} Modellen`;
}

function resetFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterSize').value   = '';
    document.getElementById('filterAngle').value  = '';
    applyFilters();
}

// Tabellen-Sortierung
let sortCol = -1, sortAsc = true;

function sortTable(col) {
    const rows = Array.from(tbody.querySelectorAll('tr[data-code]'));

    if (sortCol === col) { sortAsc = !sortAsc; }
    else { sortCol = col; sortAsc = true; }

    document.querySelectorAll('thead th').forEach((th, i) => {
        th.classList.remove('sorted-asc', 'sorted-desc');
        if (i === col) th.classList.add(sortAsc ? 'sorted-asc' : 'sorted-desc');
    });

    const dataAttrMap = {1: 'name', 2: 'family', 3: 'size', 4: 'angle'};
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

// Initiale Anzeige der Gesamtzahl
applyFilters();
</script>

</body>
</html>
