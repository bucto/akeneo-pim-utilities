<?php
include('api_helper.php');

// Variablen für den Zustand
$selectedFamily = isset($_GET['family']) ? $_GET['family'] : null;
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'all'; // all, active, disabled

// Schritt 1: Hol dir die Familien, wenn noch keine gewählt wurde
$families = getAkeneoFamilies();

// Schritt 2: Wenn eine Familie gewählt wurde, hol die Produkte
$products = [];
if ($selectedFamily) {
    $products = getAkeneoProductsByFamily($selectedFamily);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PIM Maschinen-Vergleich</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .filter-box { margin: 15px 0; padding: 10px; background: #eee; border-radius: 5px; }
        .filter-btn { padding: 5px 15px; margin-right: 5px; background: #fff; border: 1px solid #ccc; cursor: pointer; text-decoration: none; color: #333; border-radius: 3px; }
        .filter-btn.active { background: #3498db; color: #fff; border-color: #3498db; }
        .family-select { width: 100%; padding: 10px; font-size: 16px; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="container">
    <h1>AMADA Produkt-Vergleich</h1>

    <label for="familyDropdown" style="font-weight: bold; display: block; margin-bottom: 5px;">Schritt 1: Welche PIM Familie willst Du vergleichen?</label>
    <select id="familyDropdown" class="family-select" onchange="location = this.value;">
        <option value="index.php">-- Bitte Produktfamilie wählen --</option>
        <?php foreach ($families as $family): 
            $label = isset($family['labels']['de_DE']) ? $family['labels']['de_DE'] : $family['code'];
            $selected = ($selectedFamily === $family['code']) ? 'selected' : '';
        ?>
            <option value="index.php?family=<?php echo $family['code']; ?>" <?php echo $selected; ?>>
                <?php echo htmlspecialchars($label . " (" . $family['code'] . ")"); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <?php if ($selectedFamily): ?>
        <h2>Schritt 2: Produkte der Familie werden gelistet</h2>

        <div class="filter-box">
            <span>Status filtern:</span>
            <a href="index.php?family=<?php echo $selectedFamily; ?>&status=all" class="filter-btn <?php echo $filterStatus === 'all' ? 'active' : ''; ?>">Alle anzeigen</a>
            <a href="index.php?family=<?php echo $selectedFamily; ?>&status=active" class="filter-btn <?php echo $filterStatus === 'active' ? 'active' : ''; ?>">Nur Aktive</a>
            <a href="index.php?family=<?php echo $selectedFamily; ?>&status=disabled" class="filter-btn <?php echo $filterStatus === 'disabled' ? 'active' : ''; ?>">Nur Deaktivierte</a>
        </div>

        <form id="skuForm" action="#" method="get">
            <div class="checkbox-list">
                <?php
                $hasItems = false;

                // 1. Aktive anzeigen (wenn 'all' oder 'active' gewählt)
                if ($filterStatus === 'all' || $filterStatus === 'active') {
                    foreach ($products['active'] as $product) {
                        $hasItems = true;
                        echo "<label>";
                        echo "<input type='checkbox' class='sku-checkbox' name='skus[]' value='" . htmlspecialchars($product['identifier']) . "'> " . htmlspecialchars($product['identifier']);
                        echo "</label>";
                    }
                }

                // 2. Deaktivierte anzeigen (wenn 'all' oder 'disabled' gewählt)
                if ($filterStatus === 'all' || $filterStatus === 'disabled') {
                    foreach ($products['disabled'] as $product) {
                        $hasItems = true;
                        echo "<label class='disabled'>";
                        echo "<input type='checkbox' class='sku-checkbox disabled-checkbox' name='skus[]' value='" . htmlspecialchars($product['identifier']) . "'> ";
                        echo "<span class='disabled-text'>" . htmlspecialchars($product['identifier']) . " (Deaktiviert)</span>";
                        echo "</label>";
                    }
                }

                if (!$hasItems) {
                    echo "<p>Keine Produkte mit diesem Status-Filter gefunden.</p>";
                }
                ?>
            </div>
            
            <input type="submit" value="Vergleich der Ausstattung" name="action" formaction="Vergleich_Austattung.php">
            <input type="submit" value="Vergleich der Technische Daten" name="action" formaction="Vergleich_TechnischeDaten.php">
        </form>
    <?php endif; ?>

</div>

<script>
document.getElementById('skuForm').onsubmit = function(event) {
    var selectedSKUs = [];
    var checkboxes = document.querySelectorAll('.sku-checkbox:checked');
    checkboxes.forEach(function(checkbox) {
        selectedSKUs.push(checkbox.value);
    });

    if (selectedSKUs.length > 0) {
        var skuString = selectedSKUs.join(',');
        var hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'skus';
        hiddenInput.value = skuString;
        document.getElementById('skuForm').appendChild(hiddenInput);
    }
}
</script>

</body>
</html>