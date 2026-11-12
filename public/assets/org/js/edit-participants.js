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

// OR06-A: bevestigingsdialoog voor het accepteren / afwijzen van aanvragen.
document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('review-dialog');

    if (!dialog || typeof dialog.showModal !== 'function') {
        return;
    }

    const form = document.getElementById('review-dialog-form');
    const title = document.getElementById('review-dialog-title');
    const text = document.getElementById('review-dialog-text');
    const reasonWrapper = document.getElementById('review-dialog-reason');
    const reasonInput = document.getElementById('review-dialog-reason-input');
    const confirmButton = document.getElementById('review-dialog-confirm');
    const cancelButton = document.getElementById('review-dialog-cancel');
    const tokenInput = form.querySelector('input[name="_token"]');

    const openDialog = (button) => {
        const isReject = button.dataset.review === 'reject';
        const name = button.dataset.name;

        form.action = button.dataset.action;
        tokenInput.value = button.dataset.token;

        title.textContent = isReject ? 'Aanvraag afwijzen' : 'Aanvraag accepteren';
        text.textContent = isReject
            ? `Weet je zeker dat je de aanvraag van ${name} wilt afwijzen?`
            : `Weet je zeker dat je ${name} wilt accepteren als deelnemer?`;

        reasonWrapper.hidden = !isReject;
        reasonInput.required = isReject;
        reasonInput.disabled = !isReject; // niet meesturen bij accepteren
        reasonInput.value = '';

        confirmButton.textContent = isReject ? 'Afwijzen bevestigen' : 'Accepteren bevestigen';
        confirmButton.className = 'btn ' + (isReject ? 'btn--reject' : 'btn--accept');

        dialog.showModal();

        if (isReject) {
            reasonInput.focus();
        }
    };

    document.querySelectorAll('[data-review]').forEach((button) => {
        button.addEventListener('click', () => openDialog(button));
    });

    cancelButton.addEventListener('click', () => dialog.close());

    // Dubbelklikken op "bevestigen" mag maar één verzoek opleveren.
    form.addEventListener('submit', (event) => {
        if (form.dataset.submitting === '1') {
            event.preventDefault();
            return;
        }

        if (!reasonInput.disabled && reasonInput.value.trim() === '') {
            event.preventDefault();
            reasonInput.setCustomValidity('Geef een korte reden op.');
            reasonInput.reportValidity();
            return;
        }

        form.dataset.submitting = '1';
        confirmButton.disabled = true;
    });

    reasonInput.addEventListener('input', () => reasonInput.setCustomValidity(''));
});