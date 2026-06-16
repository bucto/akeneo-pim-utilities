<?php

/** Logo-Pfad relativ zum Webroot */
define('AMADA_LOGO_PATH', 'assets/amada-logo.svg');

/** Admin-Bereich nur wenn ADMIN_ENABLED=true gesetzt ist */
function isAdminEnabled(): bool {
    return filter_var(getenv('ADMIN_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN);
}

/** Blockiert Zugriff auf Admin-Seiten für normale Nutzer */
function requireAdmin(): void {
    if (!isAdminEnabled()) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Zugriff verweigert</title></head>';
        echo '<body style="font-family:sans-serif;padding:40px;color:#2d3748;">';
        echo '<h1>Zugriff verweigert</h1><p>Diese Seite ist nur für Administratoren verfügbar.</p>';
        echo '<p><a href="index.php">← Zur Startseite</a></p></body></html>';
        exit;
    }
}

/**
 * Gemeinsame CSS-Variablen und Basis-Styles für alle Seiten.
 */
function renderBaseStyles(): void {
    ?>
    <style>
        :root {
            --amada-red:  #e2001a;
            --dark-gray:  #2d3748;
            --light-bg:   #f7fafc;
            --border:     #cbd5e0;
            --hover-bg:   #edf2f7;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #edf2f7;
            color: var(--dark-gray);
            margin: 0;
            padding: 24px 20px 40px;
        }
        .site-header {
            max-width: 1400px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }
        .site-logo-link { display: block; line-height: 0; }
        .site-logo { height: 42px; width: auto; display: block; }
        .site-nav {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .nav-link {
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
            text-decoration: none;
            border: 1px solid var(--border);
            border-radius: 5px;
            padding: 7px 14px;
            background: #fff;
            transition: all 0.15s;
        }
        .nav-link:hover { background: var(--hover-bg); color: var(--dark-gray); }
        .nav-link.active {
            background: var(--dark-gray);
            color: #fff;
            border-color: var(--dark-gray);
        }
        .nav-link.admin {
            color: #742a2a;
            border-color: #fc8181;
            font-size: 12px;
        }
        .back-link {
            font-size: 13px;
            color: #718096;
            text-decoration: none;
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 5px 11px;
            background: #fff;
        }
        .back-link:hover { background: var(--hover-bg); }
        .page-content {
            max-width: 1400px;
            margin: 0 auto;
        }
    </style>
    <?php
}

/**
 * Seitenkopf mit Logo und Hauptnavigation.
 * @param string|null $active  'vergleich' | 'finder' | 'admin'
 */
function renderSiteHeader(?string $active = null): void {
    ?>
    <header class="site-header">
        <a href="index.php" class="site-logo-link" title="AMADA PIM Utilities">
            <img src="<?php echo AMADA_LOGO_PATH; ?>" alt="AMADA" class="site-logo">
        </a>
        <nav class="site-nav">
            <a href="produkt_vergleich.php"
               class="nav-link <?php echo $active === 'vergleich' ? 'active' : ''; ?>">
                Produkt-Vergleich
            </a>
            <a href="bendingtool_finder.php"
               class="nav-link <?php echo $active === 'finder' ? 'active' : ''; ?>">
                Werkzeugfinder
            </a>
            <?php if (isAdminEnabled()): ?>
                <a href="pim_family_settings.php"
                   class="nav-link admin <?php echo $active === 'admin' ? 'active' : ''; ?>">
                    Admin
                </a>
            <?php endif; ?>
        </nav>
    </header>
    <?php
}
