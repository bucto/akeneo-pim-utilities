<?php


// Konfiguration
$servername = "localhost";
$username = "PIM_Converter";
$password = "fWRV5mPpVnNnGxEH";
$dbname = "PIM_Converter";
$htaccessFile = 'generated_htaccess.txt';

// Funktion zur Bereinigung von Strings für URLs
function sanitizeStringForUrl($string) {
    $string = strtolower($string);
    $string = str_replace([' ', '_'], '-', $string); // Ersetze Leerzeichen und Unterstriche
    $string = preg_replace('/[^a-z0-9-]/', '', $string);
    return $string;
}

// Handler für die .htaccess-Generierung
if (isset($_GET['generate'])) {
    try {
        $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = "SELECT `serie_NAME`, `subdomain`, `gmbh_website_ID` FROM `MASTER_Serien` WHERE `gmbh_website_ID` IS NOT NULL AND `subdomain` != '' ORDER BY `serie_NAME`";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $htaccessContent = "";
        foreach ($results as $row) {
            $serie_name = $row['serie_NAME'];
            $subdomain = sanitizeStringForUrl($row['subdomain']);
            $id = $row['gmbh_website_ID'];

            $htaccessContent .= "## *****************************\n";
            $htaccessContent .= "## Weiterleitung " . $serie_name . "\n";
            $htaccessContent .= "## *****************************\n";
            $htaccessContent .= "RewriteCond %{HTTP_HOST}%{REQUEST_URI} ^" . $subdomain . "\.amada-gmbh\.com(\/?)$\n";
            $htaccessContent .= "RewriteRule (.*) https://www.amada.de/index.php?id=" . $id . "&L=0 [R=302,L]\n\n";

            $htaccessContent .= "RewriteCond %{HTTP_HOST} ^(www\.)?" . $subdomain . "\.amada\.de$ [NC]\n";
            $htaccessContent .= "RewriteRule ^.*$ https://www.amada.de/index.php?id=" . $id . "&L=1 [R=302,L]\n\n";

            $htaccessContent .= "RewriteCond %{HTTP_HOST}%{REQUEST_URI} ^" . $subdomain . "\.amada\.nl(\/?)$\n";
            $htaccessContent .= "RewriteRule (.*) https://www.amada.nl/index.php?id=" . $id . "&L=2 [R=302,L]\n\n";
        }

        // Header für den Download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $htaccessFile . '"');
        echo $htaccessContent;
        exit;

    } catch (PDOException $e) {
        die("Fehler bei der Datenbankverbindung: " . $e->getMessage());
    }
}

// Daten für die Ansicht abrufen
$results = [];
$error = null;
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "SELECT `serie_NAME`, `subdomain`, `gmbh_website_ID` FROM `MASTER_Serien` WHERE `gmbh_website_ID` IS NOT NULL AND `subdomain` != '' ORDER BY `serie_NAME`";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Fehler bei der Datenbankverbindung: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subdomain-Übersicht & .htaccess-Generator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .container {
            max-width: 900px;
        }
        tr:nth-child(even) {
            background-color: #f3f4f6;
        }
    </style>
</head>
<body class="bg-gray-100 p-8 font-sans">
    <div class="container mx-auto bg-white p-8 rounded-lg shadow-md">
        <h1 class="text-3xl font-bold mb-6 text-center text-gray-800">Subdomain-Verwaltung</h1>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Fehler!</strong>
                <span class="block sm:inline"><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <div class="flex flex-col md:flex-row md:items-center justify-between mb-6">
            <div class="flex-grow mb-4 md:mb-0">
                <input type="text" id="searchInput" placeholder="Nach Serie oder Subdomain suchen..." class="w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 transition duration-150 ease-in-out">
            </div>
            <a href="?generate=1" class="md:ml-4 px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-150 ease-in-out text-center">
                .htaccess-Datei generieren
            </a>
        </div>

        <div class="overflow-x-auto rounded-lg shadow-inner">
            <table class="min-w-full divide-y divide-gray-200" id="dataTable">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Serie</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subdomain</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Website ID</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
    <?php if (count($results) > 0): ?>
        <?php foreach ($results as $row): ?>
            <tr>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['serie_NAME']); ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
    <?php
        $subdomain_value = htmlspecialchars($row['subdomain']);
        // Link für amada.de mit DE-Flagge
        echo "🇩🇪 <a href='https://" . $subdomain_value . ".amada.de' target='_blank' class='text-blue-600 hover:underline'>" . $subdomain_value . ".amada.de</a><br>";
        // Link für amada.nl mit NL-Flagge
        echo "🇳🇱 <a href='https://" . $subdomain_value . ".amada.nl' target='_blank' class='text-blue-600 hover:underline'>" . $subdomain_value . ".amada.nl</a><br>";
        // Link für amada-gmbh.com mit EN-Flagge
        echo "🇺🇸 <a href='https://" . $subdomain_value . ".amada-gmbh.com' target='_blank' class='text-blue-600 hover:underline'>" . $subdomain_value . ".amada-gmbh.com</a>";
    ?>
</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($row['gmbh_website_ID']); ?></td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">Keine Einträge gefunden.</td>
        </tr>
    <?php endif; ?>
</tbody>
            </table>
        </div>
    </div>
    
    <script>
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.getElementById('dataTable').getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                let cells = rows[i].getElementsByTagName('td');
                let found = false;
                for (let j = 0; j < cells.length; j++) {
                    if (cells[j]) {
                        if (cells[j].textContent.toLowerCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                rows[i].style.display = found ? "" : "none";
            }
        });
    </script>
</body>
</html>