<?php
include_once('config.php');

/**
 * Gibt eine PDO-Datenbankverbindung zurück (Singleton).
 * Gibt null zurück wenn die DB nicht erreichbar ist, damit die App trotzdem funktioniert.
 */
function getDbConnection(): ?PDO {
    static $pdo = null;
    static $failed = false;

    if ($failed) return null;
    if ($pdo !== null) return $pdo;

    try {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 3,
        ]);
    } catch (PDOException $e) {
        $failed = true;
        $pdo    = null;
    }

    return $pdo;
}

/**
 * Prüft, ob in der DB mindestens eine Familien-Zuweisung vorhanden ist.
 * Wenn nein, werden in den Tabs alle Familien angezeigt (Fallback).
 */
function hasAnyFamilyConfig(): bool {
    $pdo = getDbConnection();
    if (!$pdo) return false;

    try {
        $stmt = $pdo->query(
            "SELECT 1 FROM pim_family_config
             WHERE excluded = 0
               AND (for_products = 1 OR for_automation = 1 OR for_accessories = 1
                    OR for_punching_tools = 1 OR for_bending_tools = 1)
             LIMIT 1"
        );
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Lädt alle konfigurierten Familien für einen Kontext.
 *
 * @param string $context  'products' | 'automation' | 'accessories' | 'punching_tools' | 'bending_tools'
 * @return array|null  Array von ['family_code'=>..., 'label'=>...] oder null
 */
function getConfiguredFamiliesForContext(string $context): ?array {
    $pdo = getDbConnection();
    if (!$pdo) return null;

    $column = match($context) {
        'automation'     => 'for_automation',
        'accessories'    => 'for_accessories',
        'punching_tools' => 'for_punching_tools',
        'bending_tools'  => 'for_bending_tools',
        default          => 'for_products',
    };

    try {
        $stmt = $pdo->query(
            "SELECT family_code, label FROM pim_family_config
             WHERE excluded = 0 AND {$column} = 1
             ORDER BY label, family_code"
        );
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Lädt alle Familien-Konfigurationszeilen für die Einstellungsseite.
 */
function getAllFamilyConfig(): array {
    $pdo = getDbConnection();
    if (!$pdo) return [];

    try {
        $stmt = $pdo->query(
            "SELECT family_code, label,
                    for_products, for_automation, for_accessories,
                    for_punching_tools, for_bending_tools,
                    excluded
             FROM pim_family_config
             ORDER BY label, family_code"
        );
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Speichert (INSERT oder UPDATE) eine einzelne Familien-Zeile.
 */
function upsertFamilyConfig(
    string $code,
    string $label,
    bool $forProducts,
    bool $forAutomation,
    bool $forAccessories,
    bool $forPunchingTools,
    bool $forBendingTools,
    bool $excluded
): void {
    $pdo = getDbConnection();
    if (!$pdo) return;

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO pim_family_config
                (family_code, label, for_products, for_automation, for_accessories,
                 for_punching_tools, for_bending_tools, excluded)
             VALUES
                (:code, :label, :fp, :fa, :fac, :fpt, :fbt, :ex)
             ON DUPLICATE KEY UPDATE
                label              = VALUES(label),
                for_products       = VALUES(for_products),
                for_automation     = VALUES(for_automation),
                for_accessories    = VALUES(for_accessories),
                for_punching_tools = VALUES(for_punching_tools),
                for_bending_tools  = VALUES(for_bending_tools),
                excluded           = VALUES(excluded)"
        );
        $stmt->execute([
            ':code'  => $code,
            ':label' => $label,
            ':fp'    => $forProducts      ? 1 : 0,
            ':fa'    => $forAutomation    ? 1 : 0,
            ':fac'   => $forAccessories   ? 1 : 0,
            ':fpt'   => $forPunchingTools ? 1 : 0,
            ':fbt'   => $forBendingTools  ? 1 : 0,
            ':ex'    => $excluded         ? 1 : 0,
        ]);
    } catch (PDOException $e) {
        // Fehler stillschweigend ignorieren, Seite bleibt funktionsfähig
    }
}
