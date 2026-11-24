async function loadLogs(url, tableId, fields) {
    const table = document.getElementById(tableId);

    if (!table) {
        return;
    }

    try {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const logs = await response.json();

        table.replaceChildren();

        if (!Array.isArray(logs) || logs.length === 0) {
            const row = table.insertRow();
            const cell = row.insertCell();

            cell.colSpan = fields.length;
            cell.textContent = 'Geen logs gevonden.';

            return;
        }

        for (const log of logs) {
            const row = table.insertRow();

            for (const field of fields) {
                const cell = row.insertCell();
                cell.textContent = String(log[field] ?? '');
            }
        }
    } catch (error) {
        console.error('Failed to load logs:', error);

        table.replaceChildren();

        const row = table.insertRow();
        const cell = row.insertCell();

        cell.colSpan = fields.length;
        cell.textContent = 'Logs konden niet worden geladen.';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadLogs(
        '/admin/api/logs/audit',
        'auditLogsTable',
        ['createdAt', 'actorUsername', 'action', 'entityType', 'entityId', 'requestIp']
    );

    loadLogs(
        '/admin/api/logs/system',
        'systemLogsTable',
        ['createdAt', 'code', 'level', 'message', 'route', 'method', 'requestIp']
    );
});
