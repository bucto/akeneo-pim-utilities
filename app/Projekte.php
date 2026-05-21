<?php
// Datenbankverbindungsparameter
$servername = "localhost";
$username = "JobLog";
$password = "ZJZMFuTIc16AG1VK";
$dbname = "JobLog";

// Verbindung zur Datenbank herstellen
$conn = new mysqli($servername, $username, $password, $dbname);

// Verbindung überprüfen
if ($conn->connect_error) {
    die("Verbindung fehlgeschlagen: " . $conn->connect_error);
}

// --- NEUE ZEILE HINZUGEFÜGT ---
// Zeichenkodierung für die Verbindung auf UTF-8 setzen
$conn->set_charset("utf8mb4");

// SQL-Abfrage vorbereiten (Filterfunktion)
$sql = "SELECT id, name, description, host, status, last_run FROM Overview";
$filter = isset($_GET['filter']) ? $conn->real_escape_string($_GET['filter']) : '';

if (!empty($filter)) {
    $sql .= " WHERE name LIKE '%$filter%' OR description LIKE '%$filter%' OR host LIKE '%$filter%' OR status LIKE '%$filter%'";
}

$result = $conn->query($sql);

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projektübersicht</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 20px; }
        .container { max-width: 1000px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { text-align: center; color: #333; }
        .filter-form { margin-bottom: 20px; text-align: center; }
        .filter-form input[type="text"] { padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 60%; max-width: 400px; }
        .filter-form button { padding: 8px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .filter-form button:hover { background-color: #0056b3; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; color: #555; }
        tr:hover { background-color: #f9f9f9; }
        .status-ok { color: green; font-weight: bold; }
        .status-pending { color: orange; font-weight: bold; }
        .status-error { color: red; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <h1>Projektübersicht</h1>

    <div class="filter-form">
        <form action="projekte.php" method="GET">
            <input type="text" name="filter" placeholder="Projekte filtern..." value="<?php echo htmlspecialchars($filter); ?>">
            <button type="submit">Filtern</button>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Beschreibung</th>
                <th>Host</th>
                <th>Status</th>
                <th>Zuletzt ausgeführt</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $status_class = '';
                    switch($row['status']) {
                        case 'OK':
                            $status_class = 'status-ok';
                            break;
                        case 'Pending':
                            $status_class = 'status-pending';
                            break;
                        case 'ERROR':
                            $status_class = 'status-error';
                            break;
                    }
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row["id"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["name"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["description"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["host"]) . "</td>";
                    echo "<td class='$status_class'>" . htmlspecialchars($row["status"]) . "</td>";
                    echo "<td>" . date("d.m.Y H:i", strtotime($row["last_run"])) . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='6'>Keine Projekte gefunden.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<?php
$conn->close();
?>

</body>
</html>