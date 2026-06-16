<?php

/** Logo-Pfad relativ zum Webroot */
define('AMADA_LOGO_PATH', 'assets/amada-logo.svg');

/** Footer-Metadaten (über .env überschreibbar) */
define('APP_AUTHOR', getenv('APP_AUTHOR') ?: 'Thomas Bücken');
define('APP_REPO',   getenv('APP_REPO')   ?: 'https://github.com/bucto/akeneo-pim-utilities');

/**
 * Git-Revisionsnummer: APP_REVISION → REVISION-Datei → git (Entwicklung) → dev
 */
function getAppRevision(): string {
    static $revision = null;
    if ($revision !== null) {
        return $revision;
    }

    $fromEnv = getenv('APP_REVISION');
    if (is_string($fromEnv) && $fromEnv !== '') {
        return $revision = $fromEnv;
    }

    $revFile = __DIR__ . '/REVISION';
    if (is_readable($revFile)) {
        $fromFile = trim((string)file_get_contents($revFile));
        if ($fromFile !== '') {
            return $revision = $fromFile;
        }
    }

    $repoRoot = dirname(__DIR__);
    if (is_dir($repoRoot . '/.git')) {
        $hash = @shell_exec('git -C ' . escapeshellarg($repoRoot) . ' rev-parse --short HEAD 2>/dev/null');
        if (is_string($hash)) {
            $hash = trim($hash);
            if ($hash !== '') {
                return $revision = $hash;
            }
        }
    }

    return $revision = 'dev';
}

/** Anzeigename des Repository-Links ohne Schema und .git-Suffix */
function getAppRepoLabel(): string {
    $repo = preg_replace('#^https?://#', '', APP_REPO);
    return preg_replace('#\.git$#', '', rtrim($repo, '/'));
}

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
        .site-footer {
            max-width: 1400px;
            margin: 28px auto 0;
            padding-top: 16px;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
            font-size: 12px;
            color: #718096;
        }
        .site-footer a {
            color: #4a5568;
            text-decoration: none;
        }
        .site-footer a:hover {
            text-decoration: underline;
            color: var(--dark-gray);
        }
        .site-footer .sep {
            color: #cbd5e0;
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

/**
 * Seitenfuß mit Revisionsnummer, Autor und Repository-Link.
 */
function renderSiteFooter(): void {
    ?>
    <footer class="site-footer">
        <span>Rev. <?php echo htmlspecialchars(getAppRevision()); ?></span>
        <span class="sep">·</span>
        <span><?php echo htmlspecialchars(APP_AUTHOR); ?></span>
        <span class="sep">·</span>
        <a href="<?php echo htmlspecialchars(APP_REPO); ?>" target="_blank" rel="noopener noreferrer">
            <?php echo htmlspecialchars(getAppRepoLabel()); ?>
        </a>
    </footer>
    <?php
}
