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

/**
 * SKU-Liste für Vergleichs-Links normalisieren (kommagetrennt).
 * Akzeptiert auch eingefügte URLs mit skus=-Parameter.
 */
function normalizeCompareLinkSkus(string $input): string {
    $input = trim($input);
    if ($input === '') {
        return '';
    }

    if (preg_match('/[?&]skus=([^&]+)/', $input, $match)) {
        $input = urldecode($match[1]);
    }

    $parts = preg_split('/[\s,;]+/', $input, -1, PREG_SPLIT_NO_EMPTY);
    $skus  = [];
    foreach ($parts as $part) {
        $sku = trim($part);
        if ($sku !== '') {
            $skus[] = $sku;
        }
    }

    return implode(',', $skus);
}

/** URL zur Vergleichsseite mit SKU-Liste. */
function buildCompareSkusUrl(string $targetPage, string $skus): string {
    return $targetPage . '?' . http_build_query(['skus' => $skus]);
}

/**
 * Schnellauswahl-Links für den Produkt-Vergleich (eine Familie).
 *
 * @return array<int, array{id: int, name: string, skus: string, family_code: string, sort_order: int}>
 */
function getCompareLinksForFamily(string $familyCode): array {
    $pdo = getDbConnection();
    if (!$pdo || $familyCode === '') {
        return [];
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT id, name, skus, family_code, sort_order
             FROM pim_compare_links
             WHERE family_code = :family
             ORDER BY sort_order, name, id'
        );
        $stmt->execute([':family' => $familyCode]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Alle Schnellauswahl-Links (Admin).
 */
function getAllCompareLinks(): array {
    $pdo = getDbConnection();
    if (!$pdo) {
        return [];
    }

    try {
        $stmt = $pdo->query(
            'SELECT id, name, skus, family_code, sort_order, updated_at
             FROM pim_compare_links
             ORDER BY family_code, sort_order, name, id'
        );
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getCompareLinkById(int $id): ?array {
    $pdo = getDbConnection();
    if (!$pdo || $id <= 0) {
        return null;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT id, name, skus, family_code, sort_order
             FROM pim_compare_links WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

function saveCompareLink(?int $id, string $name, string $skus, string $familyCode, int $sortOrder = 0): bool {
    $pdo = getDbConnection();
    if (!$pdo) {
        return false;
    }

    $name       = trim($name);
    $skus       = normalizeCompareLinkSkus($skus);
    $familyCode = trim($familyCode);
    $sortOrder  = max(0, $sortOrder);

    if ($name === '' || $skus === '' || $familyCode === '') {
        return false;
    }

    try {
        if ($id !== null && $id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE pim_compare_links
                 SET name = :name, skus = :skus, family_code = :family, sort_order = :sort
                 WHERE id = :id'
            );
            $stmt->execute([
                ':name'   => $name,
                ':skus'   => $skus,
                ':family' => $familyCode,
                ':sort'   => $sortOrder,
                ':id'     => $id,
            ]);
            return $stmt->rowCount() > 0;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO pim_compare_links (name, skus, family_code, sort_order)
             VALUES (:name, :skus, :family, :sort)'
        );
        $stmt->execute([
            ':name'   => $name,
            ':skus'   => $skus,
            ':family' => $familyCode,
            ':sort'   => $sortOrder,
        ]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function deleteCompareLink(int $id): bool {
    $pdo = getDbConnection();
    if (!$pdo || $id <= 0) {
        return false;
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM pim_compare_links WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}
