<?php
include('api_helper.php');

// Zustand bestimmen - Standardmäßig auf 'active' setzen
$selectedFamily = isset($_GET['family']) ? $_GET['family'] : null;
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'active'; // 'active' ist nun Standard!

// Schritt 1: Familien laden
$families = getAkeneoFamilies();

// Schritt 2: Wenn Familie gewählt, Produkte holen
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
    <title>AMADA Produkt-Vergleich</title>
    <style>
        :root {
            --amada-red: #e2001a;
            --dark-gray: #2d3748;
            --light-bg: #f7fafc;
            --border-color: #cbd5e0;
        }
        body {
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #edf2f7;
            color: var(--dark-gray);
            margin: 0;
            padding: 40px 20px;
        }
        .container {
            max-width: 700px;
            background: #ffffff;
            margin: 0 auto;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05), 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 {
            font-size: 24px;
            color: #1a202c;
            margin-top: 0;
            margin-bottom: 25px;
            border-bottom: 3px solid var(--amada-red);
            padding-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        h2 {
            font-size: 18px;
            color: #2d3748;
            margin-top: 25px;
            margin-bottom: 15px;
        }
        label.step-label {
            font-weight: 600;
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
        }
        .family-select {
            width: 100%;
            padding: 12px;
            font-size: 15px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            background-color: #fff;
            outline: none;
            transition: border-color 0.2s;
        }
        .family-select:focus {
            border-color: #4a5568;
        }
        .filter-box {
            margin: 20px 0 15px 0;
            padding: 12px;
            background: var(--light-bg);
            border: 1px solid var(--border-color);
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .filter-title {
            font-size: 14px;
            font-weight: 600;
            color: #718096;
        }
        .filter-btn {
            padding: 6px 14px;
            background: #fff;
            border: 1px solid var(--border-color);
            cursor: pointer;
            text-decoration: none;
            color: #4a5568;
            font-size: 13px;
            border-radius: 4px;
            transition: all 0.2s;
        }
        .filter-btn:hover {
            background: #f7fafc;
        }
        .filter-btn.active {
            background: var(--dark-gray);
            color: #fff;
            border-color: var(--dark-gray);
            font-weight: 600;
        }
        .checkbox-list {
            max-height: 350px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            padding: 5px;
            background: #ffffff;
            margin-bottom: 25px;
        }
        .checkbox-list label {
            display: block;
            padding: 10px 12px;
            border-bottom: 1px solid #edf2f7;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.1s;
        }
        .checkbox-list label:last-child {
            border-bottom: none;
        }
        .checkbox-list label:hover {
            background: #f7fafc;
        }
        .checkbox-list input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.1);
            vertical-align: middle;
        }
        .checkbox-list label.disabled {
            background: #f8fafc;
        }
        .disabled-text {
            color: #a0aec0;
            font-style: italic;
        }
        .btn-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .submit-btn {
            padding: 14px;
            font-size: 14px;
            font-weight: 600;
            color: white;
            background-color: var(--amada-red);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: background-color 0.2s;
        }
        .submit-btn:hover {
            background-color: #b80014;
        }
        .submit-btn.secondary {
            background-color: var(--dark-gray);
        }
        .submit-btn.secondary:hover {
            background-color: #1a202c;
        }
        p.no-data {
            color: #718096;
            padding: 15px;
            margin: 0;
            font-style: italic;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>AMADA Produkt-Vergleich</h1>

    <label for="familyDropdown" class="step-label">Schritt 1: Welche PIM Familie willst Du vergleichen?</label>
    <select id="familyDropdown" class="family-select" onchange="location = this.value;">
        <option value="index.php?status=<?php echo $filterStatus; ?>">-- Bitte Produktfamilie wählen --</option>
        <?php foreach ($families as $family): 
            $label = isset($family['labels']['de_DE']) ? $family['labels']['de_DE'] : $family['code'];
            $selected = ($selectedFamily === $family['code']) ? 'selected' : '';
        ?>
            <option value="index.php?family=<?php echo $family['code']; ?>&status=<?php echo $filterStatus; ?>" <?php echo $selected; ?>>
                <?php echo htmlspecialchars($label . " (" . $family['code'] . ")"); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <?php if ($selectedFamily): ?>
        <h2>Schritt 2: Produkte der Familie werden gelistet</h2>

        <div class="filter-box">
            <span class="filter-title">Status:</span>
            <a href="index.php?family=<?php echo $selectedFamily; ?>&status=active" class="filter-btn <?php echo $filterStatus === 'active' ? 'active' : ''; ?>">Nur Aktive</a>
            <a href="index.php?family=<?php echo $selectedFamily; ?>&status=disabled" class="filter-btn <?php echo $filterStatus === 'disabled' ? 'active' : ''; ?>">Nur Deaktivierte</a>
            <a href="index.php?family=<?php echo $selectedFamily; ?>&status=all" class="filter-btn <?php echo $filterStatus === 'all' ? 'active' : ''; ?>">Alle anzeigen</a>
        </div>

        <form id="skuForm" action="#" method="get">
            <div class="checkbox-list">
                <?php
                $hasItems = false;

                // 1. Aktive ausgeben
                if ($filterStatus === 'all' || $filterStatus === 'active') {
                    foreach ($products['active'] as $product) {
                        $hasItems = true;
                        echo "<label>";
                        echo "<input type='checkbox' class='sku-checkbox' name='skus[]' value='" . htmlspecialchars($product['identifier']) . "'> " . htmlspecialchars($product['identifier']);
                        echo "</label>";
                    }
                }

                // 2. Deaktivierte ausgeben
                if ($filterStatus === 'all' || $filterStatus === 'disabled') {
                    foreach ($products['disabled'] as $product) {
                        $hasItems = true;
                        echo "<label class='disabled'>";
                        echo "<input type='checkbox' class='sku-checkbox' name='skus[]' value='" . htmlspecialchars($product['identifier']) . "'> ";
                        echo "<span class='disabled-text'>" . htmlspecialchars($product['identifier']) . " (Deaktiviert)</span>";
                        echo "</label>";
                    }
                }

                if (!$hasItems) {
                    echo "<p class='no-data'>Keine Produkte mit diesem Status-Filter gefunden.</p>";
                }
                ?>
            </div>
            
            <div class="btn-group">
                <input type="submit" value="Ausstattung vergleichen" class="submit-btn" formaction="Vergleich_Austattung.php">
                <input type="submit" value="Tech. Daten vergleichen" class="submit-btn secondary" formaction="Vergleich_TechnischeDaten.php">
            </div>
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
    } else {
        alert("Bitte wähle mindestens ein Produkt aus.");
        return false;
    }
}
</script>

</body>
</html>