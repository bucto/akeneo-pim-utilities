<?php
// Den zentralen Helfer einbinden (dieser lädt automatisch auch config.php)
include('api_helper.php');

// Produktfamilie definieren
$familyCode = 'fiber_laser_cutting_machine';

// Die zentrale Funktion aufrufen (Suchtyp ist 'family')
$products = getAkeneoProducts('family', $familyCode);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SKU Auswahl</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<div class="container">
    <h1>Wähle das gewünschte aus</h1>
    <form id="skuForm" action="#" method="get">
        <div class="checkbox-list">
            <?php
            // Aktive Produkte ausgeben (kommen vorsortiert aus dem api_helper)
            foreach ($products['active'] as $product) {
                echo "<label><input type='checkbox' class='sku-checkbox' name='skus[]' value='" . htmlspecialchars($product['identifier']) . "'> " . htmlspecialchars($product['identifier']) . "</label>";
            }

            // Deaktivierte Produkte ausgeben (kommen ebenfalls vorsortiert)
            foreach ($products['disabled'] as $product) {
                echo "<label class='disabled'><input type='checkbox' class='sku-checkbox disabled-checkbox' name='skus[]' value='" . htmlspecialchars($product['identifier']) . "'> <span class='disabled-text'>" . htmlspecialchars($product['identifier']) . " (Deaktiviert)</span></label>";
            }

            if (empty($products['active']) && empty($products['disabled'])) {
                echo "<p>Keine Produkte in der angegebenen Familie gefunden.</p>";
            }
            ?>
        </div>
        <input type="submit" value="Vergleich der Ausstattung" name="action" formaction="Vergleich_Austattung.php">
        <input type="submit" value="Vergleich der Technische Daten" name="action" formaction="Vergleich_TechnischeDaten.php">
    </form>
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
</div>
</body>
</html>