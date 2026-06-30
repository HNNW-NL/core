document.addEventListener('DOMContentLoaded', function () {
    document.body.classList.add('page-ready');

    const form = document.querySelector('form[action*="/org/projects/modify/"]');
    if (!form) {
        return;
    }

    const statusBanner = form.querySelector('.form-status');
    const deleteButton = form.querySelector('button[name="intent"][value="delete"]');
    const actionButtons = form.querySelectorAll('button[name="intent"]');
    const deleteDialog = document.getElementById('deleteConfirmDialog');
    const deleteInput = document.getElementById('deleteConfirmInput');
    const deleteHint = document.getElementById('deleteConfirmHint');
    const deleteConfirmButton = document.getElementById('deleteConfirmButton');
    let deleteConfirmed = false;

    function setStatus(kind, text) {
        if (!statusBanner) {
            return;
        }
        statusBanner.className = 'form-status ' + (kind === 'error' ? 'form-status--error' : 'form-status--success');
        statusBanner.textContent = text;
        statusBanner.hidden = false;
    }

    function ensureIntent(intent) {
        let input = form.querySelector('input[name="intent"][type="hidden"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'intent';
            form.appendChild(input);
        }
        input.value = intent;
    }

    function setSubmitting(isSubmitting) {
        actionButtons.forEach(function (button) {
            if (!button.dataset.originalLabel) button.dataset.originalLabel = button.textContent;
            button.disabled = isSubmitting;
            button.textContent = isSubmitting ? (button.value === 'delete' ? 'Deleting...' : 'Updating...') : button.dataset.originalLabel;
        });
    }

    if (deleteInput) {
        deleteInput.addEventListener('input', function () {
            const ok = deleteInput.value.trim() === 'DELETE';
            if (deleteConfirmButton) deleteConfirmButton.disabled = !ok;
            if (deleteHint) deleteHint.textContent = ok ? 'Confirmed. You can delete permanently.' : 'Type DELETE (all caps) to enable the button.';
        });
    }

    deleteDialog?.addEventListener('close', function () {
        if (deleteDialog.returnValue !== 'confirm' || (deleteInput && deleteInput.value.trim() !== 'DELETE')) {
            deleteConfirmed = false;
            setSubmitting(false);
            return;
        }
        deleteConfirmed = true;
        form.requestSubmit(deleteButton || undefined);
    });

    form.addEventListener('submit', function (event) {
        if (statusBanner) {
            statusBanner.hidden = true;
            statusBanner.textContent = '';
        }

        const intent = event.submitter?.value || 'modify';
        ensureIntent(intent);

        if (intent === 'delete') {
            if (!deleteConfirmed) {
                event.preventDefault();
                if (deleteDialog?.showModal) {
                    if (deleteInput) deleteInput.value = '';
                    if (deleteConfirmButton) deleteConfirmButton.disabled = true;
                    deleteDialog.showModal();
                } else {
                    const typed = window.prompt('Type DELETE to confirm permanent deletion:');
                    if ((typed || '').trim() === 'DELETE') {
                        deleteConfirmed = true;
                        form.requestSubmit(deleteButton || undefined);
                    } else {
                        setStatus('error', 'Deletion cancelled.');
                    }
                }
                return;
            }
            deleteConfirmed = false;
            setStatus('success', 'Deleting project...');
            setSubmitting(true);
            return;
        }

        setSubmitting(true);
    });
});
