document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('edit-participants-table');
    const headerRow = table.querySelector('thead tr');
    let draggedColumnIndex = null;

    
    const initDraggableHeaders = () => {
        const headers = table.querySelectorAll('th');

        headers.forEach((header) => {
            header.addEventListener('dragstart', (e) => {
                draggedColumnIndex = Array.from(header.parentNode.children).indexOf(header);
                e.dataTransfer.effectAllowed = 'move';
                header.classList.add('dragging-header');

                e.dataTransfer.setData('text/plain', draggedColumnIndex);
            });

            header.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                header.classList.add('drop-target');
            });

            header.addEventListener('dragleave', () => {
                header.classList.remove('drop-target');
            });

            header.addEventListener('drop', (e) => {
                e.preventDefault();
                header.classList.remove('drop-target');

                const targetColumnIndex = Array.from(header.parentNode.children).indexOf(header);

                if (draggedColumnIndex !== null && draggedColumnIndex !== targetColumnIndex) {
                    moveTableColumn(table, draggedColumnIndex, targetColumnIndex);
                }
            });

            header.addEventListener('dragend', () => {
                headers.forEach(h => h.classList.remove('dragging-header', 'drop-target'));
                draggedColumnIndex = null;
            });
        });
    };

    function moveTableColumn(table, fromIdx, toIdx) {
        const rows = Array.from(table.rows);

        rows.forEach(row => {
            const cells = row.cells;
            const cellToMove = cells[fromIdx];
            const targetCell = cells[toIdx];

            if (!cellToMove || !targetCell) return;

            if (fromIdx < toIdx) {
                row.insertBefore(cellToMove, targetCell.nextElementSibling);
            } else {
                row.insertBefore(cellToMove, targetCell);
            }
        });
    }

    initDraggableHeaders();
});
