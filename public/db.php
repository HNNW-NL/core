<?php

$host = "localhost";
$dbname = "hnnw_core";
$username = "postgres";
$password = "nourdin97834";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function logAudit($conn, $userId, $action) 
{
    $stmt = $conn->prepare(
        "INSERT INTO audit_logs (user_id, action) VALUES (?, ?)"
    );

    $stmt->bind_param("is", $userId, $action);
    $stmt->execute();
    $stmt->close();
}

logAudit($conn, $_SESSION['user_id'], 'Gebruiker gewijzigd');

function logSystem($conn, $level, $message)
{
    $stmt = $conn->prepare(
        "INSERT INTO system_logs (level, message) VALUES (?, ?)"
    );

    $stmt->bind_param("ss", $level, $message);
    $stmt->execute();
    $stmt->close();
}

logSystem($conn, 'ERROR', 'Database verbinding mislukt');

session_start();
require 'db.php';

/* Controleer admin rol */
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Geen toegang');
}

/* Audit logs */
$auditLogs = $conn->query("
    SELECT *
    FROM audit_logs
    ORDER BY created_at DESC
");

/* System logs */
$systemLogs = $conn->query("
    SELECT *
    FROM system_logs
    ORDER BY created_at DESC
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Logs</title>
</head>
<body>

<h2>Audit Logs</h2>

<table border="1">
    <tr>
        <th>ID</th>
        <th>User ID</th>
        <th>Actie</th>
        <th>Datum</th>
    </tr>

    <?php while($row = $auditLogs->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($row['id']) ?></td>
        <td><?= htmlspecialchars($row['user_id']) ?></td>
        <td><?= htmlspecialchars($row['action']) ?></td>
        <td><?= htmlspecialchars($row['created_at']) ?></td>
    </tr>
    <?php endwhile; ?>
</table>

<h2>System Logs</h2>

<table border="1">
    <tr>
        <th>ID</th>
        <th>Level</th>
        <th>Bericht</th>
        <th>Datum</th>
    </tr>

    <?php while($row = $systemLogs->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($row['id']) ?></td>
        <td><?= htmlspecialchars($row['level']) ?></td>
        <td><?= htmlspecialchars($row['message']) ?></td>
        <td><?= htmlspecialchars($row['created_at']) ?></td>
    </tr>
    <?php endwhile; ?>
</table>

</body>
</html>