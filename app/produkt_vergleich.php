<?php
include('api_helper.php');
include('db_helper.php');
include('common.php');

// --- Zustand bestimmen ---
$activeTab     = in_array($_GET['tab'] ?? '', ['automation', 'accessories', 'punching_tools', 'bending_tools']) ? $_GET['tab'] : 'products';
$selectedFamily = $_GET['family'] ?? null;
$filterStatus   = in_array($_GET['status'] ?? '', ['active', 'disabled', 'all']) ? $_GET['status'] : 'active';

// --- Alle Familien aus Akeneo laden ---
$allFamilies = getAkeneoFamilies();

// --- Familien nach Tabs filtern (DB-basiert oder Fallback: alle) ---
$hasConfig = hasAnyFamilyConfig();

function filterFamiliesForTab(array $allFamilies, string $tab, bool $hasConfig): array {
    if ($hasConfig) {
        $configured = getConfiguredFamiliesForContext($tab) ?? [];
        if (empty($configured)) {
            return [];
        }
        $allowedCodes = array_column($configured, 'family_code');
        $allFamilies  = array_values(array_filter($allFamilies, fn($f) => in_array($f['code'], $allowedCodes)));
    }

    usort($allFamilies, function($a, $b) {
        $labelA = $a['labels']['de_DE'] ?? $a['code'];
        $labelB = $b['labels']['de_DE'] ?? $b['code'];
        return strcasecmp($labelA, $labelB);
    });

    return $allFamilies;
}

$tabFamilies = filterFamiliesForTab($allFamilies, $activeTab, $hasConfig);

// --- Produkte laden wenn Familie gewählt ---
$products     = [];
$compareLinks = [];
if ($selectedFamily) {
    $products = getAkeneoProductsByFamily($selectedFamily);

    // Automatisch auf "Deaktivierte" wechseln wenn keine aktiven vorhanden sind
    if ($filterStatus === 'active' && empty($products['active']) && !empty($products['disabled'])) {
        $filterStatus = 'disabled';
    }

    $compareLinks = getCompareLinksForFamily($selectedFamily);
}

// --- Hilfsfunktion: Tab-URL ---
function tabUrl(string $tab, ?string $family = null, string $status = 'active'): string {
    $params = ['tab' => $tab, 'status' => $status];
    if ($family) $params['family'] = $family;
    return 'produkt_vergleich.php?' . http_build_query($params);
}

function vergleichReloadUrl(): string {
    $params = $_GET;
    $params['reload'] = '1';
    return 'produkt_vergleich.php?' . http_build_query($params);
}

$familiesCacheMeta = pimApiCacheMeta('families_v1');
$productsCacheMeta = $selectedFamily
    ? pimApiCacheMeta('products_family_' . $selectedFamily . '_v1')
    : null;

$tabs = [
    'products'       => 'Maschinen',
    'automation'     => 'Automation',
    'accessories'    => 'Zubehör',
    'punching_tools' => 'Stanzwerkzeuge',
    'bending_tools'  => 'Abkantwerkzeuge',
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produkt-Vergleich – AMADA</title>
    <?php renderBaseStyles(); ?>
    <style>
        .container {
            max-width: 1400px;
            background: #fff;
            margin: 0 auto;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            overflow: hidden;
        }

        /* --- Kopfzeile --- */
        .page-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 22px 28px 0;
            flex-wrap: wrap;
            gap: 10px;
        }
        .page-head h1 {
            font-size: 22px;
            margin: 0;
            color: #1a202c;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 3px solid var(--amada-red);
            padding-bottom: 8px;
        }
        .cache-info {
            font-size: 12px;
            color: #a0aec0;
            margin-top: 6px;
        }
        .cache-info a {
            color: #718096;
            text-decoration: none;
        }
        .cache-info a:hover { color: var(--amada-red); }
        .settings-link {
            font-size: 13px;
            color: #718096;
            text-decoration: none;
            border: 1px solid var(--border);
            border-radius: 5px;
            padding: 6px 12px;
            white-space: nowrap;
            transition: all 0.2s;
        }
        .settings-link:hover {
            background: var(--light-bg);
            color: var(--dark-gray);
        }

        /* --- Tabs --- */
        .tab-bar {
            display: flex;
            padding: 16px 28px 0;
            gap: 0;
            border-bottom: 2px solid var(--border);
        }
        .tab-btn {
            padding: 10px 22px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            color: #718096;
            border: 1px solid transparent;
            border-bottom: none;
            border-radius: 6px 6px 0 0;
            transition: background 0.15s, color 0.15s;
            white-space: nowrap;
        }
        .tab-btn:hover { background: #f0f4f8; color: var(--dark-gray); }
        .tab-btn.active {
            background: #fff;
            color: var(--dark-gray);
            border-color: var(--border);
            border-bottom-color: #fff;
            margin-bottom: -2px;
        }

        /* --- Inhalt --- */
        .tab-content {
            padding: 24px 28px 28px;
        }
        label.step-label {
            font-weight: 600;
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
            font-size: 14px;
        }
        .family-select {
            width: 100%;
            padding: 11px 12px;
            font-size: 14px;
            border: 1px solid var(--border);
            border-radius: 5px;
            background: #fff;
            outline: none;
            transition: border-color 0.2s;
        }
        .family-select:focus { border-color: #4a5568; }

        /* --- Filter --- */
        .filter-box {
            margin: 20px 0 14px;
            padding: 10px 14px;
            background: var(--light-bg);
            border: 1px solid var(--border);
            border-radius: 5px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .filter-title {
            font-size: 13px;
            font-weight: 600;
            color: #718096;
        }
        .filter-btn {
            padding: 5px 13px;
            background: #fff;
            border: 1px solid var(--border);
            cursor: pointer;
            text-decoration: none;
            color: #4a5568;
            font-size: 13px;
            border-radius: 4px;
            transition: all 0.2s;
        }
        .filter-btn:hover { background: var(--light-bg); }
        .filter-btn.active {
            background: var(--dark-gray);
            color: #fff;
            border-color: var(--dark-gray);
            font-weight: 600;
        }

        /* --- Produktliste --- */
        .product-count {
            font-size: 13px;
            color: #718096;
            margin-bottom: 8px;
        }
        .checkbox-list {
            max-height: 420px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 5px;
            background: #fff;
            margin-bottom: 22px;
        }
        .product-row {
            display: flex;
            align-items: center;
            padding: 10px 14px;
            border-bottom: 1px solid #edf2f7;
            gap: 12px;
            cursor: pointer;
            transition: background 0.1s;
        }
        .product-row:last-child { border-bottom: none; }
        .product-row:hover { background: var(--light-bg); }
        .product-row.disabled-row { background: #fafafa; }
        .product-row input[type="checkbox"] {
            flex-shrink: 0;
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        .product-thumb {
            flex-shrink: 0;
            width: 52px;
            height: 52px;
            object-fit: contain;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: #f8fafc;
        }
        .product-thumb-placeholder {
            flex-shrink: 0;
            width: 52px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f4f8;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-size: 18px;
            color: #cbd5e0;
        }
        .product-label {
            flex: 1;
            font-size: 14px;
            color: var(--dark-gray);
        }
        .product-label.disabled-text { color: #a0aec0; font-style: italic; }
        .disabled-badge {
            font-size: 11px;
            background: #e2e8f0;
            color: #718096;
            border-radius: 3px;
            padding: 2px 7px;
            white-space: nowrap;
        }

        /* --- Buttons --- */
        .btn-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .submit-btn {
            padding: 13px;
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            background: var(--amada-red);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            transition: background 0.2s;
        }
        .submit-btn:hover { background: #b80014; }
        .submit-btn.secondary { background: var(--dark-gray); }
        .submit-btn.secondary:hover { background: #1a202c; }

        p.no-data {
            color: #718096;
            padding: 18px;
            margin: 0;
            font-style: italic;
            font-size: 14px;
        }
        .section-title {
            font-size: 15px;
            font-weight: 600;
            margin: 20px 0 10px;
            color: var(--dark-gray);
        }
        .section-title-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0 10px;
            flex-wrap: wrap;
        }
        .section-title-row .section-title {
            margin: 0;
        }
        .help-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border: 1px solid var(--border);
            border-radius: 50%;
            background: #fff;
            color: #4a5568;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
        }
        .help-toggle:hover,
        .help-toggle[aria-expanded="true"] {
            background: var(--light-bg);
            border-color: #a0aec0;
            color: var(--dark-gray);
        }
        .suggestion-panel {
            display: none;
            margin-bottom: 16px;
            padding: 14px 16px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 6px;
        }
        .suggestion-panel.open {
            display: block;
        }
        .suggestion-intro {
            margin: 0 0 12px;
            font-size: 13px;
            color: #4a5568;
            line-height: 1.45;
        }
        .suggestion-group-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #718096;
            margin: 0 0 8px;
        }
        .suggestion-group + .suggestion-group {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid #e2e8f0;
        }
        .suggestion-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .suggestion-item {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 10px 12px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
        }
        .suggestion-meta {
            flex: 1;
            min-width: 180px;
        }
        .suggestion-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--dark-gray);
        }
        .suggestion-skus {
            display: block;
            margin-top: 3px;
            font-size: 12px;
            color: #718096;
            line-height: 1.35;
            word-break: break-word;
        }
        .suggestion-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }
        .suggestion-link {
            display: inline-block;
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 5px;
            text-decoration: none;
            border: 1px solid var(--border);
            background: #fff;
            color: #4a5568;
            transition: background 0.15s, color 0.15s;
        }
        .suggestion-link:hover {
            background: var(--light-bg);
            color: var(--dark-gray);
        }
        .suggestion-link.tech {
            background: var(--dark-gray);
            border-color: var(--dark-gray);
            color: #fff;
        }
        .suggestion-link.tech:hover {
            background: #1a202c;
            color: #fff;
        }
        .suggestion-link.ausstattung {
            border-color: #fed7d7;
            color: #9b2c2c;
        }
        .suggestion-link.ausstattung:hover {
            background: #fff5f5;
        }
    </style>
</head>
<body>

<?php renderSiteHeader('vergleich'); ?>

<div class="container">

    <div class="page-head">
        <div>
            <h1>Produkt-Vergleich</h1>
            <?php if (PIM_API_CACHE_ENABLED && ($familiesCacheMeta || $productsCacheMeta)): ?>
                <div class="cache-info">
                    <?php
                    $meta = $productsCacheMeta ?? $familiesCacheMeta;
                    if ($meta):
                    ?>
                        Daten gecacht (vor <?php echo max(1, (int)round($meta['age'] / 60)); ?> Min.)
                        · <a href="<?php echo htmlspecialchars(vergleichReloadUrl()); ?>">Neu laden</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab-Leiste -->
    <div class="tab-bar">
        <?php foreach ($tabs as $tabKey => $tabLabel): ?>
            <a href="<?php echo tabUrl($tabKey, null, $filterStatus); ?>"
               class="tab-btn <?php echo $activeTab === $tabKey ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($tabLabel); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Tab-Inhalt -->
    <div class="tab-content">

        <!-- Schritt 1: Familie wählen -->
        <label for="familyDropdown" class="step-label">
            Schritt 1: Produktfamilie wählen (<?php echo htmlspecialchars($tabs[$activeTab]); ?>)
        </label>

        <?php if (empty($tabFamilies)): ?>
            <p class="no-data">
                Keine Familien für diesen Bereich konfiguriert.
                <?php if (isAdminEnabled()): ?>
                    <a href="pim_family_settings.php">Jetzt zuweisen →</a>
                <?php else: ?>
                    Bitte wenden Sie sich an den Administrator.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <select id="familyDropdown" class="family-select"
                    onchange="location = this.value;">
                <option value="<?php echo tabUrl($activeTab, null, $filterStatus); ?>">
                    -- Bitte Produktfamilie wählen --
                </option>
                <?php foreach ($tabFamilies as $family):
                    $fCode   = $family['code'];
                    $fLabel  = $family['labels']['de_DE'] ?? $fCode;
                    $sel     = ($selectedFamily === $fCode) ? 'selected' : '';
                ?>
                    <option value="<?php echo tabUrl($activeTab, $fCode, $filterStatus); ?>" <?php echo $sel; ?>>
                        <?php echo htmlspecialchars($fLabel . ' (' . $fCode . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <?php if ($selectedFamily): ?>

            <!-- Status-Filter -->
            <div class="filter-box">
                <span class="filter-title">Status:</span>
                <a href="<?php echo tabUrl($activeTab, $selectedFamily, 'active'); ?>"
                   class="filter-btn <?php echo $filterStatus === 'active'   ? 'active' : ''; ?>">Nur Aktive</a>
                <a href="<?php echo tabUrl($activeTab, $selectedFamily, 'disabled'); ?>"
                   class="filter-btn <?php echo $filterStatus === 'disabled' ? 'active' : ''; ?>">Nur Deaktivierte</a>
                <a href="<?php echo tabUrl($activeTab, $selectedFamily, 'all'); ?>"
                   class="filter-btn <?php echo $filterStatus === 'all'      ? 'active' : ''; ?>">Alle anzeigen</a>
            </div>

            <!-- Schritt 2: Produkte auswählen -->
            <div class="section-title-row">
                <p class="section-title">Schritt 2: Produkte auswählen</p>
                <?php if (!empty($compareLinks)): ?>
                    <button type="button"
                            class="help-toggle"
                            id="suggestionHelpToggle"
                            aria-expanded="false"
                            aria-controls="suggestionPanel"
                            title="Schnellauswahl anzeigen">?</button>
                <?php endif; ?>
            </div>

            <?php if (!empty($compareLinks)): ?>
                <div class="suggestion-panel" id="suggestionPanel" role="region" aria-label="Schnellauswahl">
                    <p class="suggestion-intro">
                        Vorgefertigte Vergleichs-Links für diese Produktfamilie — ein Klick öffnet den Vergleich direkt.
                    </p>

                    <div class="suggestion-group">
                        <div class="suggestion-list">
                            <?php foreach ($compareLinks as $link):
                                $techUrl = buildCompareSkusUrl('Vergleich_TechnischeDaten.php', $link['skus']);
                                $ausUrl  = buildCompareSkusUrl('Vergleich_Austattung.php', $link['skus']);
                            ?>
                                <div class="suggestion-item">
                                    <div class="suggestion-meta">
                                        <span class="suggestion-label"><?php echo htmlspecialchars($link['name']); ?></span>
                                    </div>
                                    <div class="suggestion-actions">
                                        <a class="suggestion-link tech"
                                           href="<?php echo htmlspecialchars($techUrl); ?>">
                                            Tech. Daten →
                                        </a>
                                        <a class="suggestion-link ausstattung"
                                           href="<?php echo htmlspecialchars($ausUrl); ?>">
                                            Ausstattung →
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <form id="skuForm" action="#" method="get">
                <!-- Versteckte Felder um Tab-Zustand beim Vergleich mitzugeben -->
                <input type="hidden" name="tab"    value="<?php echo htmlspecialchars($activeTab); ?>">
                <input type="hidden" name="family" value="<?php echo htmlspecialchars($selectedFamily); ?>">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($filterStatus); ?>">

                <?php
                $activeCount   = count($products['active']);
                $disabledCount = count($products['disabled']);
                $totalShown    = 0;
                if ($filterStatus === 'active' || $filterStatus === 'all')   $totalShown += $activeCount;
                if ($filterStatus === 'disabled' || $filterStatus === 'all') $totalShown += $disabledCount;
                ?>
                <p class="product-count"><?php echo $totalShown; ?> Produkt(e) gefunden</p>

                <div class="checkbox-list" id="productCheckboxList">
                    <?php
                    $hasItems = false;

                    // Aktive Produkte
                    if ($filterStatus === 'all' || $filterStatus === 'active') {
                        foreach ($products['active'] as $product) {
                            $hasItems  = true;
                            $imgUrl    = $product['_imageUrl'] ?? null;
                            $ident     = $product['identifier'];
                            ?>
                            <label class="product-row">
                                <input type="checkbox" class="sku-checkbox" name="skus[]"
                                       value="<?php echo htmlspecialchars($ident); ?>">
                                <?php if ($imgUrl): ?>
                                    <img class="product-thumb"
                                         src="<?php echo htmlspecialchars($imgUrl); ?>"
                                         alt="<?php echo htmlspecialchars($ident); ?>"
                                         loading="lazy"
                                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                    <span class="product-thumb-placeholder" style="display:none;">📷</span>
                                <?php else: ?>
                                    <span class="product-thumb-placeholder">📷</span>
                                <?php endif; ?>
                                <span class="product-label">
                                    <?php echo htmlspecialchars($ident); ?>
                                </span>
                            </label>
                            <?php
                        }
                    }

                    // Deaktivierte Produkte
                    if ($filterStatus === 'all' || $filterStatus === 'disabled') {
                        foreach ($products['disabled'] as $product) {
                            $hasItems = true;
                            $imgUrl   = $product['_imageUrl'] ?? null;
                            $ident    = $product['identifier'];
                            ?>
                            <label class="product-row disabled-row">
                                <input type="checkbox" class="sku-checkbox" name="skus[]"
                                       value="<?php echo htmlspecialchars($ident); ?>">
                                <?php if ($imgUrl): ?>
                                    <img class="product-thumb"
                                         src="<?php echo htmlspecialchars($imgUrl); ?>"
                                         alt="<?php echo htmlspecialchars($ident); ?>"
                                         loading="lazy"
                                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                    <span class="product-thumb-placeholder" style="display:none;">📷</span>
                                <?php else: ?>
                                    <span class="product-thumb-placeholder">📷</span>
                                <?php endif; ?>
                                <span class="product-label disabled-text">
                                    <?php echo htmlspecialchars($ident); ?>
                                    <span class="disabled-badge">Deaktiviert</span>
                                </span>
                            </label>
                            <?php
                        }
                    }

                    if (!$hasItems) {
                        echo "<p class='no-data'>Keine Produkte mit diesem Status-Filter gefunden.</p>";
                    }
                    ?>
                </div>

                <!-- Schritt 3: Vergleich starten -->
                <p class="section-title">Schritt 3: Vergleich starten</p>
                <div class="btn-group">
                    <input type="submit" value="Ausstattung vergleichen"
                           class="submit-btn"
                           formaction="Vergleich_Austattung.php">
                    <input type="submit" value="Tech. Daten vergleichen"
                           class="submit-btn secondary"
                           formaction="Vergleich_TechnischeDaten.php">
                </div>
            </form>

        <?php endif; ?>

    </div><!-- .tab-content -->
</div><!-- .container -->

<script>
(function () {
    var helpToggle = document.getElementById('suggestionHelpToggle');
    var panel      = document.getElementById('suggestionPanel');

    if (helpToggle && panel) {
        helpToggle.addEventListener('click', function () {
            var open = panel.classList.toggle('open');
            helpToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }
})();

document.getElementById('skuForm') && (document.getElementById('skuForm').onsubmit = function(e) {
    var checked = document.querySelectorAll('.sku-checkbox:checked');
    if (checked.length === 0) {
        alert('Bitte wähle mindestens ein Produkt aus.');
        e.preventDefault();
        return false;
    }

    var skus = Array.from(checked).map(cb => cb.value).join(',');
    var hidden = document.createElement('input');
    hidden.type  = 'hidden';
    hidden.name  = 'skus';
    hidden.value = skus;
    this.appendChild(hidden);

    // Checkbox-Felder deaktivieren damit skus[] nicht doppelt übertragen wird
    document.querySelectorAll('input[name="skus[]"]').forEach(cb => cb.disabled = true);
});
</script>

<?php renderSiteFooter(); ?>

</body>
</html>
