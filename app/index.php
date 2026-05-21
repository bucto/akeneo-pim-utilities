<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP-Dateien auflisten</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            width: 80%;
            margin: 0 auto;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-top: 50px;
        }
        h1 {
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f4f4f4;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        a {
            color: #3498db;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Wähle eine Liste</h1>

    <?php
    // Pfad zum Verzeichnis
    $directory = __DIR__;

    // PHP-Dateien im Verzeichnis finden und config.php ignorieren
    $files = array_filter(scandir($directory), function($file) {
        return pathinfo($file, PATHINFO_EXTENSION) === 'php' 
    && $file !== 'config.php' 
    && $file !== basename(__FILE__) 
    && $file !== 'Vergleich_Austattung.php' 
    && $file !== 'OptionOK.php' 
    && $file !== 'Vergleich_TechnischeDaten.php';
    });

    if (!empty($files)): ?>
        <table>
            <thead>
                <tr>
                    <th>Listen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $file): ?>
                    <tr>
                        <td><a href="<?php echo $file; ?>"><?php echo $file; ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Keine PHP-Dateien gefunden.</p>
    <?php endif; ?>
</div>

</body>
</html>
