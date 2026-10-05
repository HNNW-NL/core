// logs-pagina (/admin/logs): haalt de audit- en system-logs op en zet ze in de tabellen
fetch('/admin/api/logs/audit')
    .then(response => response.json())
    .then(logs => {
        const table = document.getElementById('auditLogsTable');
        // stoppen als de tabel niet op de pagina staat
        if (!table) {
            return;
        }
        table.innerHTML = '';

        if (logs.length === 0) {
            table.innerHTML = '<tr><td colspan="7">Geen audit logs gevonden.</td></tr>';
            return;
        }

        logs.forEach(log => {
            table.innerHTML += `
                <tr>
                    <td>${log.createdAt ?? ''}</td>
                    <td>${log.actorUsername ?? ''}</td>
                    <td>${log.actorEmail ?? ''}</td>
                    <td>${log.action ?? ''}</td>
                    <td>${log.entityType ?? ''}</td>
                    <td>${log.entityId ?? ''}</td>
                    <td>${log.requestIp ?? ''}</td>
                </tr>
            `;
        });
    });

fetch('/admin/api/logs/system')
    .then(response => response.json())
    .then(logs => {
        const table = document.getElementById('systemLogsTable');
        // stoppen als de tabel niet op de pagina staat
        if (!table) {
            return;
        }
        table.innerHTML = '';

        if (logs.length === 0) {
            table.innerHTML = '<tr><td colspan="7">Geen system logs gevonden.</td></tr>';
            return;
        }

        logs.forEach(log => {
            table.innerHTML += `
                <tr>
                    <td>${log.createdAt ?? ''}</td>
                    <td>${log.code ?? ''}</td>
                    <td>${log.level ?? ''}</td>
                    <td>${log.message ?? ''}</td>
                    <td>${log.route ?? ''}</td>
                    <td>${log.method ?? ''}</td>
                    <td>${log.requestIp ?? ''}</td>
                </tr>
            `;
        });
    });
