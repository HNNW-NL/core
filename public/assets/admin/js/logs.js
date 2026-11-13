async function loadLogs(url, tableId, fields) {
    const table = document.getElementById(tableId);
    if (!table) return;
    try {
        const response = await fetch(url, {credentials: 'same-origin'});
        if (!response.ok) throw new Error('HTTP ' + response.status);
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
                row.insertCell().textContent = String(log[field] ?? '');
            }
        }
    } catch (error) {
        table.replaceChildren();
        const cell = table.insertRow().insertCell();
        cell.colSpan = fields.length;
        cell.textContent = 'Logs konden niet worden geladen.';
    }
}
document.addEventListener('DOMContentLoaded', () => {
    loadLogs('{{ path('admin.api.logs.audit') }}', 'auditLogsTable',
        ['createdAt','actorUsername','actorEmail','action','entityType','entityId','requestIp']);
    loadLogs('{{ path('admin.api.logs.system') }}', 'systemLogsTable',
        ['createdAt','code','level','message','route','method','requestIp']);
});
