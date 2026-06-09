<!DOCTYPE html>
<html>
<head>
    <title>Account Bewerken</title>

    <link rel="stylesheet"
        href="/assets/org/css/accounts.css">
</head>

<body>

<h1>Account aanpassen</h1>

<form id="accountForm"
    method="POST"
    action="/org/accounts/update/<?= $account['id']; ?>">

    <label>Naam</label>
    <input
        type="text"
        name="name"
        value="<?= htmlspecialchars($account['name']); ?>"
        required
    >

    <label>Email</label>
    <input
        type="email"
        name="email"
        value="<?= htmlspecialchars($account['email']); ?>"
        required
    >

    <label>Rol</label>
    <select name="role">

        <option value="user"
            <?= $account['role'] === 'user'
            ? 'selected' : ''; ?>>
            User
        </option>

        <option value="admin"
            <?= $account['role'] === 'admin'
            ? 'selected' : ''; ?>>
            Admin
        </option>

    </select>

    <label>
        <input
            type="checkbox"
            name="active"
            <?= $account['active']
                ? 'checked'
                : ''; ?>
        >
        Actief
    </label>

    <button type="submit">
        Opslaan
    </button>
</form>

<script src="/assets/org/js/accounts.js"></script>

</body>
</html>