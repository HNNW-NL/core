<!DOCTYPE html>
<html>
<head>
    <title>Accounts</title>

    <link rel="stylesheet"
        href="/assets/org/css/accounts.css">
</head>

<body>

<h1>Accounts beheren</h1>

<table class="accounts-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Naam</th>
            <th>Email</th>
            <th>Rol</th>
            <th>Status</th>
            <th>Actie</th>
        </tr>
    </thead>

    <tbody>
    <?php foreach ($accounts as $account): ?>

        <tr>
            <td><?= $account['id']; ?></td>

            <td>
                <?= htmlspecialchars(
                    $account['name']
                ); ?>
            </td>

            <td>
                <?= htmlspecialchars(
                    $account['email']
                ); ?>
            </td>

            <td><?= $account['role']; ?></td>

            <td>
                <?= $account['active']
                    ? 'Actief'
                    : 'Inactief'; ?>
            </td>

            <td>
                <a class="edit-btn"
                    href="/org/accounts/edit/<?= $account['id']; ?>">
                    Bewerken
                </a>
            </td>
        </tr>

    <?php endforeach; ?>
    </tbody>
</table>

<script src="/assets/org/js/accounts.js"></script>

</body>
</html>