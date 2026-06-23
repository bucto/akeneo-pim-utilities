<?php include('common.php'); ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMADA PIM Utilities</title>
    <?php renderBaseStyles(); ?>
    <style>
        .home-wrap {
            max-width: 1200px;
            margin: 0 auto;
        }
        .home-hero {
            text-align: center;
            margin-bottom: 32px;
        }
        .home-hero .site-logo {
            height: 56px;
            margin: 0 auto 20px;
        }
        .home-hero h1 {
            font-size: 26px;
            margin: 0 0 8px;
            color: #1a202c;
            font-weight: 700;
        }
        .home-hero p {
            margin: 0;
            color: #718096;
            font-size: 15px;
        }
        .module-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 640px) {
            .module-grid { grid-template-columns: 1fr; }
        }
        .module-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 28px 24px;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s;
            display: block;
        }
        .module-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border-color: #a0aec0;
        }
        .module-icon {
            font-size: 32px;
            margin-bottom: 12px;
            display: block;
        }
        .module-card h2 {
            font-size: 18px;
            margin: 0 0 10px;
            color: var(--dark-gray);
        }
        .module-card p {
            margin: 0;
            font-size: 14px;
            color: #718096;
            line-height: 1.5;
        }
        .module-card .cta {
            display: inline-block;
            margin-top: 16px;
            font-size: 13px;
            font-weight: 600;
            color: var(--amada-red);
        }
        .admin-footer {
            margin-top: 28px;
            text-align: center;
        }
        .admin-footer a {
            font-size: 12px;
            color: #a0aec0;
            text-decoration: none;
        }
        .admin-footer a:hover { color: #718096; }
    </style>
</head>
<body>

<div class="home-wrap">
    <div class="home-hero">
        <a href="index.php" class="site-logo-link">
            <img src="<?php echo AMADA_LOGO_PATH; ?>" alt="AMADA" class="site-logo">
        </a>
        <h1>PIM Utilities</h1>
        <p>Werkzeuge für Produktvergleich und Abkant-Werkzeugsuche auf Basis des AMADA PIM</p>
    </div>

    <div class="module-grid">
        <a href="produkt_vergleich.php" class="module-card">
            <span class="module-icon">📊</span>
            <h2>Produkt-Vergleich</h2>
            <p>Mehrere Produkte einer Familie auswählen und technische Daten oder Ausstattung nebeneinander vergleichen.</p>
            <span class="cta">Vergleich starten →</span>
        </a>

        <a href="bendingtool_finder.php" class="module-card">
            <span class="module-icon">🔍</span>
            <h2>Werkzeugfinder</h2>
            <p>Abkant-Werkzeugmodelle nach V-Öffnung und Winkel filtern — schnell das passende Werkzeug finden.</p>
            <span class="cta">Werkzeuge suchen →</span>
        </a>
    </div>

    <?php if (isAdminEnabled()): ?>
    <div class="admin-footer">
        <a href="pim_family_settings.php">Admin: PIM-Familien</a>
        &nbsp;·&nbsp;
        <a href="compare_links_settings.php">Vergleichs-Links</a>
        &nbsp;·&nbsp;
        <a href="series_create.php">Serie anlegen</a>
    </div>
    <?php endif; ?>
</div>

<?php renderSiteFooter(); ?>

</body>
</html>
