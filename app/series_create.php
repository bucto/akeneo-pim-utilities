<?php
include('api_helper.php');
include('common.php');

requireAdmin();

$message = null;
$error   = null;
$result  = null;

$form = [
    'code'            => '',
    'label'           => '',
    'parent_category' => PIM_SERIES_CATEGORY_PARENT,
    'product_name'    => '',
    'series_name'     => '',
    'build_year'      => '',
    'built_until'     => '',
    'enabled'         => true,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'code'            => trim($_POST['code'] ?? ''),
        'label'           => trim($_POST['label'] ?? ''),
        'parent_category' => trim($_POST['parent_category'] ?? PIM_SERIES_CATEGORY_PARENT),
        'product_name'    => trim($_POST['product_name'] ?? ''),
        'series_name'     => trim($_POST['series_name'] ?? ''),
        'build_year'      => trim($_POST['build_year'] ?? ''),
        'built_until'     => trim($_POST['built_until'] ?? ''),
        'enabled'         => isset($_POST['enabled']),
    ];

    if ($form['code'] === '') {
        $error = 'Bitte einen Code (Identifier) angeben.';
    } elseif ($form['label'] === '') {
        $error = 'Bitte eine Bezeichnung für die Kategorie angeben.';
    } else {
        $params = [
            'code'           => $form['code'],
            'label'          => $form['label'],
            'parentCategory' => $form['parent_category'],
            'enabled'        => $form['enabled'],
        ];
        if ($form['product_name'] !== '') {
            $params['productName'] = $form['product_name'];
        }
        if ($form['series_name'] !== '') {
            $params['seriesName'] = $form['series_name'];
        }
        if ($form['build_year'] !== '') {
            $params['buildYear'] = $form['build_year'];
        }
        if ($form['built_until'] !== '') {
            $params['builtUntil'] = $form['built_until'];
        }

        $result = createPimSeries($params);
        if ($result['ok']) {
            $message = 'Serie „' . $form['code'] . '“ wurde angelegt (Produkt + Kategorie mit gleichem Code).';
            $form = [
                'code'            => '',
                'label'           => '',
                'parent_category' => $form['parent_category'],
                'product_name'    => '',
                'series_name'     => '',
                'build_year'      => '',
                'built_until'     => '',
                'enabled'         => true,
            ];
        } else {
            $step = $result['step'] ?? '';
            $error = ($step !== '' ? '[' . $step . '] ' : '') . ($result['error'] ?? 'Unbekannter Fehler.');
        }
    }
}

$parentCategories = getAkeneoCategoriesList();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serie anlegen – Admin</title>
    <?php renderBaseStyles(); ?>
    <style>
        .container {
            max-width: 760px;
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
            margin-bottom: 24px;
            gap: 16px;
            flex-wrap: wrap;
        }
        .page-header h1 {
            margin: 0;
            font-size: 22px;
            color: var(--dark-gray);
        }
        .header-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .hint {
            font-size: 14px;
            color: #718096;
            line-height: 1.55;
            margin: 0 0 20px;
        }
        .hint code { font-size: 13px; }
        .form-card {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px 22px;
            background: #fafbfc;
        }
        .form-card h2 {
            margin: 0 0 16px;
            font-size: 16px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px 16px;
        }
        .form-field { display: flex; flex-direction: column; gap: 6px; }
        .form-field.full { grid-column: 1 / -1; }
        .form-field label {
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
        }
        .form-field input[type="text"],
        .form-field input[type="number"],
        .form-field select {
            padding: 8px 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
        }
        .form-field .field-hint {
            font-size: 12px;
            color: #a0aec0;
        }
        .checkbox-field {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        .form-actions {
            margin-top: 18px;
            display: flex;
            gap: 10px;
        }
        .notice {
            padding: 12px 14px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .notice-ok {
            background: #f0fff4;
            border: 1px solid #9ae6b4;
            color: #276749;
        }
        .notice-error {
            background: #fff5f5;
            border: 1px solid #feb2b2;
            color: #c53030;
        }
        .notice-warn {
            background: #fffff0;
            border: 1px solid #f6e05e;
            color: #744210;
        }
        @media (max-width: 640px) {
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php renderSiteHeader('admin-series'); ?>

<div class="container">
    <div class="page-header">
        <h1>Serie anlegen</h1>
        <div class="header-actions">
            <a href="pim_family_settings.php" class="btn btn-ghost">PIM-Familien</a>
            <a href="compare_links_settings.php" class="btn btn-ghost">Vergleichs-Links</a>
            <a href="index.php" class="btn btn-ghost">← Startseite</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="notice notice-ok"><?php echo htmlspecialchars($message); ?></div>
        <?php if ($result && !empty($result['code'])): ?>
            <div class="notice notice-ok">
                Identifier / Kategorie-Code:
                <code><?php echo htmlspecialchars($result['code']); ?></code>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="notice notice-error"><?php echo htmlspecialchars($error); ?></div>
        <?php if (!empty($result['productCreated']) && empty($result['categoryCreated'])): ?>
            <div class="notice notice-warn">
                Das Produkt wurde bereits angelegt, die Kategorie jedoch nicht.
                Bitte im PIM prüfen oder den Code ändern.
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <p class="hint">
        Legt ein Produkt der Familie <code><?php echo htmlspecialchars(PIM_SERIES_FAMILY); ?></code>
        und eine Kategorie mit <strong>identischem Code</strong> an (AMADA-Konvention).
        Zuerst wird das Produkt angelegt, danach die Kategorie — Maschinen verweisen später per
        <code>categories[]</code> auf diesen Code (Baujahr-Vergleich).
    </p>

    <div class="form-card">
        <h2>Neue Serie</h2>
        <form method="post" action="series_create.php">
            <div class="form-grid">
                <div class="form-field full">
                    <label for="code">Code / Identifier *</label>
                    <input type="text" id="code" name="code" required maxlength="120"
                           pattern="[a-zA-Z0-9][a-zA-Z0-9_\-]*"
                           placeholder="z. B. bendingtool_series_afh"
                           value="<?php echo htmlspecialchars($form['code']); ?>">
                    <span class="field-hint">Gleicher Wert für Produkt-Identifier und Kategorie-Code</span>
                </div>
                <div class="form-field full">
                    <label for="label">Kategorie-Bezeichnung *</label>
                    <input type="text" id="label" name="label" required maxlength="200"
                           placeholder="z. B. AFH Serie"
                           value="<?php echo htmlspecialchars($form['label']); ?>">
                </div>
                <div class="form-field full">
                    <label for="parent_category">Übergeordnete Kategorie *</label>
                    <select id="parent_category" name="parent_category" required>
                        <?php if (empty($parentCategories)): ?>
                            <option value="<?php echo htmlspecialchars(PIM_SERIES_CATEGORY_PARENT); ?>" selected>
                                <?php echo htmlspecialchars(PIM_SERIES_CATEGORY_PARENT); ?> (Standard)
                            </option>
                        <?php else: ?>
                            <?php foreach ($parentCategories as $cat):
                                $sel = ($form['parent_category'] === $cat['code']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo htmlspecialchars($cat['code']); ?>" <?php echo $sel; ?>>
                                    <?php echo htmlspecialchars($cat['label'] . ' (' . $cat['code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <span class="field-hint">Standard aus Config: <code>PIM_SERIES_CATEGORY_PARENT</code></span>
                </div>
                <div class="form-field">
                    <label for="product_name">Produktname (<?php echo htmlspecialchars(PIM_SERIES_PRODUCT_NAME_ATTR); ?>)</label>
                    <input type="text" id="product_name" name="product_name" maxlength="200"
                           value="<?php echo htmlspecialchars($form['product_name']); ?>">
                </div>
                <div class="form-field">
                    <label for="series_name">Serienname (<?php echo htmlspecialchars(PIM_SERIES_NAME_ATTR); ?>)</label>
                    <input type="text" id="series_name" name="series_name" maxlength="200"
                           value="<?php echo htmlspecialchars($form['series_name']); ?>">
                </div>
                <div class="form-field">
                    <label for="build_year">Baujahr (<?php echo htmlspecialchars(PIM_BUILD_YEAR_ATTR); ?>)</label>
                    <input type="number" id="build_year" name="build_year" min="1900" max="2100" step="1"
                           value="<?php echo htmlspecialchars($form['build_year']); ?>">
                </div>
                <div class="form-field">
                    <label for="built_until">Bis Baujahr (<?php echo htmlspecialchars(PIM_BUILT_UNTIL_ATTR); ?>)</label>
                    <input type="number" id="built_until" name="built_until" min="1900" max="2100" step="1"
                           value="<?php echo htmlspecialchars($form['built_until']); ?>">
                </div>
                <div class="form-field full">
                    <label class="checkbox-field">
                        <input type="checkbox" name="enabled" value="1"
                            <?php echo $form['enabled'] ? 'checked' : ''; ?>>
                        Produkt aktiviert
                    </label>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Serie anlegen</button>
            </div>
        </form>
    </div>
</div>

<?php renderSiteFooter(); ?>

</body>
</html>
