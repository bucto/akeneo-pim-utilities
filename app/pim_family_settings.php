<?php
include('api_helper.php');
include('db_helper.php');
include('common.php');

requireAdmin();

$message = null;
$error   = null;
$dbOk    = (getDbConnection() !== null);

// --- POST: Konfiguration speichern ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dbOk) {
    $postedCodes = $_POST['families'] ?? [];

    foreach ($postedCodes as $code => $flags) {
        $excluded         = isset($flags['excluded']);
        $forProducts      = !$excluded && isset($flags['for_products']);
        $forAutomation    = !$excluded && isset($flags['for_automation']);
        $forAccessories   = !$excluded && isset($flags['for_accessories']);
        $forPunchingTools = !$excluded && isset($flags['for_punching_tools']);
        $forBendingTools  = !$excluded && isset($flags['for_bending_tools']);
        $label            = htmlspecialchars(strip_tags($flags['label'] ?? $code));

        upsertFamilyConfig($code, $label, $forProducts, $forAutomation, $forAccessories,
                           $forPunchingTools, $forBendingTools, $excluded);
    }

    $message = 'PIM-Familien-Konfiguration wurde gespeichert.';
}

// --- Familien aus Akeneo laden ---
$akFamilies = getAkeneoFamilies();

// --- DB-Konfiguration laden und als Lookup-Map ---
$dbConfig = [];
foreach (getAllFamilyConfig() as $row) {
    $dbConfig[$row['family_code']] = $row;
}

// --- Familien mergen: Akeneo + DB ---
$mergedFamilies = [];
foreach ($akFamilies as $fam) {
    $code  = $fam['code'];
    $label = $fam['labels']['de_DE'] ?? $code;
    $cfg   = $dbConfig[$code] ?? [
        'for_products'    => 0,
        'for_automation'  => 0,
        'for_accessories' => 0,
        'excluded'        => 0,
    ];

    $mergedFamilies[] = [
        'code'              => $code,
        'label'             => $label,
        'for_products'      => (bool)($cfg['for_products']      ?? 0),
        'for_automation'    => (bool)($cfg['for_automation']    ?? 0),
        'for_accessories'   => (bool)($cfg['for_accessories']   ?? 0),
        'for_punching_tools'=> (bool)($cfg['for_punching_tools']?? 0),
        'for_bending_tools' => (bool)($cfg['for_bending_tools'] ?? 0),
        'excluded'          => (bool)($cfg['excluded']          ?? 0),
    ];
}

usort($mergedFamilies, fn($a, $b) => strcasecmp($a['label'], $b['label']));
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PIM-Familien konfigurieren – Admin</title>
    <?php renderBaseStyles(); ?>
    <style>
        .container {
            max-width: 1100px;
            background: #fff;
            margin: 0 auto;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        }
        :root {
            --row-hover:   #edf2f7;
            --excluded-bg: #fff5f5;
        }
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px solid var(--amada-red);
            padding-bottom: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .page-header h1 {
            font-size: 22px;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .btn {
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 5px;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-block;
            letter-spacing: 0.3px;
            transition: background 0.2s;
        }
        .btn-primary {
            background: var(--amada-red);
            color: #fff;
        }
        .btn-primary:hover { background: #b80014; }
        .btn-secondary {
            background: var(--dark-gray);
            color: #fff;
        }
        .btn-secondary:hover { background: #1a202c; }
        .btn-ghost {
            background: #fff;
            color: var(--dark-gray);
            border: 1px solid var(--border);
        }
        .btn-ghost:hover { background: var(--light-bg); }

        .notice {
            padding: 12px 16px;
            border-radius: 5px;
            font-size: 14px;
            margin-bottom: 16px;
        }
        .notice-ok    { background: #f0fff4; border: 1px solid #9ae6b4; color: #276749; }
        .notice-warn  { background: #fffbeb; border: 1px solid #f6e05e; color: #744210; }
        .notice-error { background: #fff5f5; border: 1px solid #fc8181; color: #742a2a; }

        .hint {
            font-size: 13px;
            color: #718096;
            margin-bottom: 16px;
            line-height: 1.5;
        }

        .filter-bar {
            margin-bottom: 14px;
        }
        .filter-bar input[type="search"] {
            width: 100%;
            max-width: 380px;
            padding: 9px 12px;
            border: 1px solid var(--border);
            border-radius: 5px;
            font-size: 14px;
            outline: none;
        }
        .filter-bar input[type="search"]:focus {
            border-color: #4a5568;
        }

        .table-wrap {
            border: 1px solid var(--border);
            border-radius: 6px;
            margin-bottom: 20px;
            overflow: visible; /* kein horizontales Scrollen mehr nötig */
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        /* Spaltenbreiten */
        col.col-label  { width: auto; min-width: 180px; }
        col.col-code   { width: 160px; }
        col.col-check  { width: 56px; }

        thead th {
            background: var(--dark-gray);
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            vertical-align: bottom;
            padding: 6px 4px 8px;
            text-align: center;
            border-right: 1px solid #4a5568;
        }
        /* Erste zwei Spalten: normaler horizontaler Text */
        thead th:first-child,
        thead th:nth-child(2) {
            text-align: left;
            padding: 10px 14px;
            vertical-align: middle;
        }
        /* Checkbox-Spaltenköpfe: Text senkrecht von unten nach oben */
        thead th.col-rotated {
            white-space: nowrap;
            width: 56px;
            padding: 10px 4px 6px;
        }
        thead th.col-rotated span {
            display: inline-block;
            writing-mode: vertical-lr;
            transform: rotate(180deg);
            font-size: 12px;
            line-height: 1;
            max-height: 130px;
        }
        /* "Nicht laden" in Warnfarbe */
        thead th.col-excluded {
            background: #742a2a;
        }

        tbody tr:hover { background: var(--row-hover); }
        tbody tr.excluded-row { background: var(--excluded-bg) !important; }
        td {
            padding: 8px 14px;
            border-bottom: 1px solid #edf2f7;
            font-size: 14px;
            vertical-align: middle;
        }
        td.center {
            text-align: center;
            padding: 8px 4px;
            border-right: 1px solid #edf2f7;
        }
        td code {
            font-size: 12px;
            background: #edf2f7;
            padding: 2px 6px;
            border-radius: 3px;
        }
        input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        input[type="checkbox"]:disabled { cursor: not-allowed; opacity: 0.4; }

        .save-bar {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>

<?php renderSiteHeader('admin'); ?>

<div class="container">
    <div class="page-header">
        <h1>PIM-Familien konfigurieren</h1>
        <div class="header-actions">
            <a href="index.php" class="btn btn-ghost">← Startseite</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="notice notice-ok"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if (!$dbOk): ?>
        <div class="notice notice-error">
            <strong>Datenbankverbindung nicht verfügbar.</strong><br>
            Die Familien-Konfiguration kann nicht gespeichert werden. Prüfe die DB-Umgebungsvariablen und ob der Container <code>internal_db</code> läuft.
        </div>
    <?php endif; ?>

    <p class="hint">
        Lege fest, unter welchem Tab jede PIM-Familie auf der Startseite erscheint:<br>
        <strong>Maschinen</strong> &nbsp;|&nbsp;
        <strong>Automation</strong> &nbsp;|&nbsp;
        <strong>Zubehör</strong> &nbsp;|&nbsp;
        <strong>Stanzwerkzeuge</strong> &nbsp;|&nbsp;
        <strong>Abkantwerkzeuge</strong>.<br>
        Mit <strong>Nicht laden</strong> wird die Familie komplett ausgeblendet (schnellere Ladezeiten).
        Solange keine Zuweisung gespeichert ist, werden alle Familien in allen Tabs angezeigt.
    </p>

    <div class="filter-bar">
        <input type="search" id="familyFilter" placeholder="Familie filtern …" oninput="filterTable(this.value)">
    </div>

    <form method="post" action="pim_family_settings.php">
        <div class="table-wrap">
            <table id="familyTable">
                <colgroup>
                    <col class="col-label">
                    <col class="col-code">
                    <col class="col-check">
                    <col class="col-check">
                    <col class="col-check">
                    <col class="col-check">
                    <col class="col-check">
                    <col class="col-check">
                </colgroup>
                <thead>
                    <tr>
                        <th>Familie (de_DE)</th>
                        <th>Code</th>
                        <th class="col-rotated"><span>Maschinen</span></th>
                        <th class="col-rotated"><span>Automation</span></th>
                        <th class="col-rotated"><span>Zubehör</span></th>
                        <th class="col-rotated"><span>Stanzwerkzeuge</span></th>
                        <th class="col-rotated"><span>Abkantwerkzeuge</span></th>
                        <th class="col-rotated col-excluded"><span>Nicht laden</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($mergedFamilies as $fam): ?>
                    <?php $code = $fam['code']; ?>
                    <tr class="family-row <?php echo $fam['excluded'] ? 'excluded-row' : ''; ?>"
                        data-search="<?php echo strtolower(htmlspecialchars($fam['label'] . ' ' . $code)); ?>">
                        <td><?php echo htmlspecialchars($fam['label']); ?></td>
                        <td><code><?php echo htmlspecialchars($code); ?></code></td>
                        <td class="center">
                            <input type="hidden"   name="families[<?php echo $code; ?>][label]" value="<?php echo htmlspecialchars($fam['label']); ?>">
                            <input type="checkbox" name="families[<?php echo $code; ?>][for_products]"
                                   <?php echo $fam['for_products']    ? 'checked' : ''; ?>
                                   <?php echo $fam['excluded']        ? 'disabled' : ''; ?>
                                   onchange="syncRow(this)">
                        </td>
                        <td class="center">
                            <input type="checkbox" name="families[<?php echo $code; ?>][for_automation]"
                                   <?php echo $fam['for_automation']  ? 'checked' : ''; ?>
                                   <?php echo $fam['excluded']        ? 'disabled' : ''; ?>
                                   onchange="syncRow(this)">
                        </td>
                        <td class="center">
                            <input type="checkbox" name="families[<?php echo $code; ?>][for_accessories]"
                                   <?php echo $fam['for_accessories']   ? 'checked' : ''; ?>
                                   <?php echo $fam['excluded']          ? 'disabled' : ''; ?>
                                   onchange="syncRow(this)">
                        </td>
                        <td class="center">
                            <input type="checkbox" name="families[<?php echo $code; ?>][for_punching_tools]"
                                   <?php echo $fam['for_punching_tools'] ? 'checked' : ''; ?>
                                   <?php echo $fam['excluded']           ? 'disabled' : ''; ?>
                                   onchange="syncRow(this)">
                        </td>
                        <td class="center">
                            <input type="checkbox" name="families[<?php echo $code; ?>][for_bending_tools]"
                                   <?php echo $fam['for_bending_tools']  ? 'checked' : ''; ?>
                                   <?php echo $fam['excluded']           ? 'disabled' : ''; ?>
                                   onchange="syncRow(this)">
                        </td>
                        <td class="center">
                            <input type="checkbox" name="families[<?php echo $code; ?>][excluded]"
                                   class="excluded-cb"
                                   <?php echo $fam['excluded']        ? 'checked' : ''; ?>
                                   onchange="toggleExcluded(this)">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="save-bar">
            <button type="submit" class="btn btn-primary" <?php echo !$dbOk ? 'disabled' : ''; ?>>
                Zuweisung speichern
            </button>
            <a href="index.php" class="btn btn-ghost">Abbrechen</a>
        </div>
    </form>
</div>

<script>
function toggleExcluded(cb) {
    const row = cb.closest('tr');
    const catCbs = row.querySelectorAll('input[type="checkbox"]:not(.excluded-cb)');
    if (cb.checked) {
        catCbs.forEach(c => { c.checked = false; c.disabled = true; });
        row.classList.add('excluded-row');
    } else {
        catCbs.forEach(c => { c.disabled = false; });
        row.classList.remove('excluded-row');
    }
}

function syncRow(cb) {
    // nothing extra needed – just visual feedback handled by CSS
}

function filterTable(query) {
    const q = query.toLowerCase();
    document.querySelectorAll('#familyTable tbody .family-row').forEach(row => {
        const search = row.dataset.search || '';
        row.style.display = (!q || search.includes(q)) ? '' : 'none';
    });
}
</script>
</body>
</html>
