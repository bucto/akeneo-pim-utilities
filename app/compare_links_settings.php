<?php
include('api_helper.php');
include('db_helper.php');
include('common.php');

requireAdmin();

$message = null;
$error   = null;
$dbOk    = (getDbConnection() !== null);
$editId  = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = ($editId > 0) ? getCompareLinkById($editId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dbOk) {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $deleteId = (int)($_POST['id'] ?? 0);
        if (deleteCompareLink($deleteId)) {
            $message = 'Link wurde gelöscht.';
            if ($editId === $deleteId) {
                header('Location: compare_links_settings.php?saved=1');
                exit;
            }
        } else {
            $error = 'Link konnte nicht gelöscht werden.';
        }
    } else {
        $id         = (int)($_POST['id'] ?? 0);
        $name       = trim($_POST['name'] ?? '');
        $url        = trim($_POST['url'] ?? '');
        $familyCode = trim($_POST['family_code'] ?? '');
        $sortOrder  = (int)($_POST['sort_order'] ?? 0);

        if ($name === '' || $url === '' || $familyCode === '') {
            $error = 'Bitte Name, URL und Produktfamilie ausfüllen.';
        } elseif (saveCompareLink($id > 0 ? $id : null, $name, $url, $familyCode, $sortOrder)) {
            header('Location: compare_links_settings.php?saved=1');
            exit;
        } else {
            $error = 'Link konnte nicht gespeichert werden.';
        }
    }
}

if (isset($_GET['saved'])) {
    $message = 'Vergleichs-Link wurde gespeichert.';
}

$akFamilies   = getAkeneoFamilies();
$familyLabels = getAkeneoFamilyLabelMap();
$links        = getAllCompareLinks();

usort($akFamilies, fn($a, $b) => strcasecmp(
    $a['labels']['de_DE'] ?? $a['code'],
    $b['labels']['de_DE'] ?? $b['code']
));
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vergleichs-Links – Admin</title>
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
            flex-wrap: wrap;
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
            transition: background 0.2s;
        }
        .btn-primary { background: var(--amada-red); color: #fff; }
        .btn-primary:hover { background: #b80014; }
        .btn-ghost {
            background: #fff;
            color: #4a5568;
            border: 1px solid var(--border);
        }
        .btn-ghost:hover { background: var(--light-bg); }
        .btn-danger {
            background: #fff;
            color: #9b2c2c;
            border: 1px solid #fc8181;
            padding: 6px 12px;
            font-size: 12px;
        }
        .btn-danger:hover { background: #fff5f5; }
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
            background: #fff;
            color: #4a5568;
            border: 1px solid var(--border);
            text-decoration: none;
            border-radius: 4px;
        }
        .btn-small:hover { background: var(--light-bg); }
        .notice {
            padding: 12px 14px;
            border-radius: 5px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .notice-ok { background: #f0fff4; border: 1px solid #9ae6b4; color: #276749; }
        .notice-error { background: #fff5f5; border: 1px solid #fc8181; color: #9b2c2c; }
        .hint {
            font-size: 14px;
            color: #4a5568;
            line-height: 1.5;
            margin: 0 0 20px;
        }
        .form-card {
            background: var(--light-bg);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 18px;
            margin-bottom: 24px;
        }
        .form-card h2 {
            margin: 0 0 14px;
            font-size: 16px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        @media (max-width: 720px) {
            .form-grid { grid-template-columns: 1fr; }
        }
        .form-field label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 6px;
        }
        .form-field input,
        .form-field select {
            width: 100%;
            padding: 10px 12px;
            font-size: 14px;
            border: 1px solid var(--border);
            border-radius: 5px;
            background: #fff;
        }
        .form-field.full { grid-column: 1 / -1; }
        .form-actions {
            margin-top: 14px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .table-wrap {
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 6px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        th, td {
            padding: 10px 12px;
            border-bottom: 1px solid #edf2f7;
            text-align: left;
            vertical-align: top;
        }
        tr:last-child td { border-bottom: none; }
        td code, .url-cell {
            font-size: 12px;
            word-break: break-all;
            color: #4a5568;
        }
        .actions-cell {
            white-space: nowrap;
        }
        .actions-cell form { display: inline; }
    </style>
</head>
<body>

<?php renderSiteHeader('admin-links'); ?>

<div class="container">
    <div class="page-header">
        <h1>Vergleichs-Links</h1>
        <div class="header-actions">
            <a href="pim_family_settings.php" class="btn btn-ghost">PIM-Familien</a>
            <a href="index.php" class="btn btn-ghost">← Startseite</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="notice notice-ok"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="notice notice-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (!$dbOk): ?>
        <div class="notice notice-error">
            <strong>Datenbankverbindung nicht verfügbar.</strong><br>
            Bitte zuerst <code>database/init/02_pim_compare_links.sql</code> ausführen und die DB-Umgebungsvariablen prüfen.
        </div>
    <?php else: ?>

        <p class="hint">
            Pflege hier Schnellauswahl-Links für den Produkt-Vergleich.
            Jeder Link hat einen <strong>Namen</strong>, eine <strong>URL</strong> (z.&nbsp;B.
            <code>Vergleich_TechnischeDaten.php?skus=LC2415,LC3015</code>) und gilt für eine
            <strong>Produktfamilie</strong>. Die Links erscheinen im Produkt-Vergleich beim ?-Symbol.
        </p>

        <div class="form-card">
            <h2><?php echo $editRow ? 'Link bearbeiten' : 'Neuen Link anlegen'; ?></h2>
            <form method="post" action="compare_links_settings.php<?php echo $editRow ? '?edit=' . (int)$editRow['id'] : ''; ?>">
                <input type="hidden" name="action" value="save">
                <?php if ($editRow): ?>
                    <input type="hidden" name="id" value="<?php echo (int)$editRow['id']; ?>">
                <?php endif; ?>
                <div class="form-grid">
                    <div class="form-field">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" required maxlength="200"
                               placeholder="z. B. Einstiegsmodelle Tech. Daten"
                               value="<?php echo htmlspecialchars($editRow['name'] ?? ''); ?>">
                    </div>
                    <div class="form-field">
                        <label for="family_code">Produktfamilie</label>
                        <select id="family_code" name="family_code" required>
                            <option value="">— Bitte wählen —</option>
                            <?php foreach ($akFamilies as $family):
                                $code  = $family['code'];
                                $label = $family['labels']['de_DE'] ?? $code;
                                $sel   = ($editRow['family_code'] ?? '') === $code ? 'selected' : '';
                            ?>
                                <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $sel; ?>>
                                    <?php echo htmlspecialchars($label . ' (' . $code . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field full">
                        <label for="url">URL</label>
                        <input type="text" id="url" name="url" required maxlength="500"
                               placeholder="Vergleich_TechnischeDaten.php?skus=SKU1,SKU2"
                               value="<?php echo htmlspecialchars($editRow['url'] ?? ''); ?>">
                    </div>
                    <div class="form-field">
                        <label for="sort_order">Reihenfolge</label>
                        <input type="number" id="sort_order" name="sort_order" min="0" step="1"
                               value="<?php echo (int)($editRow['sort_order'] ?? 0); ?>">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $editRow ? 'Speichern' : 'Link hinzufügen'; ?>
                    </button>
                    <?php if ($editRow): ?>
                        <a href="compare_links_settings.php" class="btn btn-ghost">Abbrechen</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>URL</th>
                        <th>Produktfamilie</th>
                        <th>Reihenf.</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($links)): ?>
                    <tr>
                        <td colspan="5" style="color:#718096;font-style:italic;">Noch keine Links angelegt.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($links as $link):
                        $famCode  = $link['family_code'];
                        $famLabel = $familyLabels[$famCode] ?? $famCode;
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($link['name']); ?></td>
                            <td class="url-cell"><code><?php echo htmlspecialchars($link['url']); ?></code></td>
                            <td>
                                <?php echo htmlspecialchars($famLabel); ?>
                                <br><code><?php echo htmlspecialchars($famCode); ?></code>
                            </td>
                            <td><?php echo (int)$link['sort_order']; ?></td>
                            <td class="actions-cell">
                                <a class="btn-small"
                                   href="compare_links_settings.php?edit=<?php echo (int)$link['id']; ?>">
                                    Bearbeiten
                                </a>
                                <form method="post" action="compare_links_settings.php"
                                      onsubmit="return confirm('Link wirklich löschen?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int)$link['id']; ?>">
                                    <button type="submit" class="btn btn-danger">Löschen</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</div>

<?php renderSiteFooter(); ?>

</body>
</html>
