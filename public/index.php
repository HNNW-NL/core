<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};

$totalUsers = $pdo->query("
    SELECT COUNT(*) 
    FROM users
")->fetchColumn();

$totalAccounts = $pdo->query("
    SELECT COUNT(*) 
    FROM accounts
")->fetchColumn();

$totalProfiles = $pdo->query("
    SELECT COUNT(*) 
    FROM profiles
")->fetchColumn();

$logsStmt = $pdo->prepare("
    SELECT id, action, created_at
    FROM logs
    ORDER BY created_at DESC
    LIMIT 10
");

$logsStmt->execute();

$logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

$accountsStmt = $pdo->prepare("
    SELECT id, account_name, updated_at
    FROM accounts
    ORDER BY updated_at DESC
    LIMIT 5
");

$accountsStmt->execute();

$accounts = $accountsStmt->fetchAll(PDO::FETCH_ASSOC);
$profilesStmt = $pdo->prepare("
    SELECT id, first_name, last_name, updated_at
    FROM profiles
    ORDER BY updated_at DESC
    LIMIT 5
");

$profilesStmt->execute();

$profiles = $profilesStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>

    <style>

        body{
            font-family: Arial;
            margin:40px;
            background:#f5f5f5;
        }

        .cards{
            display:flex;
            gap:20px;
            margin-bottom:30px;
        }

        .card{
            background:white;
            padding:20px;
            border-radius:8px;
            width:200px;
            box-shadow:0 2px 5px rgba(0,0,0,0.1);
        }

        .section{
            background:white;
            margin-bottom:20px;
            padding:20px;
            border-radius:8px;
        }

    </style>

</head>

<body>

<h1>Dashboard</h1>

<div class="cards">

    <div class="card">
        <h3>Gebruikers</h3>
        <p><?= $totalUsers ?></p>
    </div>

    <div class="card">
        <h3>Accounts</h3>

        <p><?= $totalAccounts ?></p>
    </div>

    <div class="card">
        <h3>Profielen</h3>
        <p><?= $totalProfiles ?></p>
    </div>

</div>

<div class="section">

    <h2>Nieuwste Logs</h2>

    <ul>

        <?php foreach($logs as $log): ?>

            <li>
                <?= htmlspecialchars($log['action']) ?>
                -
                <?= $log['created_at'] ?>
            </li>

        <?php endforeach; ?>

    </ul>

</div>

<div class="section">

    <h2>Laatst bijgewerkte accounts</h2>

    <ul>

        <?php foreach($accounts as $account): ?>

            <li>
                <?= htmlspecialchars($account['account_name']) ?>
                -
                <?= $account['updated_at'] ?>
            </li>

        <?php endforeach; ?>

    </ul>

</div>
</body>
</html>