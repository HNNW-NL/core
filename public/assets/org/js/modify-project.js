document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-modify-project-form]');
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
    const startDateInput = form.querySelector('#startDate');
    const endDateInput = form.querySelector('#endDate');
    const initialSnapshot = new FormData(form);
    let deleteConfirmed = false;
    let isSubmitting = false;

    function isDirty() {
        const current = new FormData(form);
        const keys = new Set([...initialSnapshot.keys(), ...current.keys()]);

        for (const key of keys) {
            const before = initialSnapshot.getAll(key).map(String);
            const after = current.getAll(key).map(String);

            if (before.length !== after.length) {
                return true;
            }

            for (let i = 0; i < before.length; i += 1) {
                if (before[i] !== after[i]) {
                    return true;
                }
            }
        }

        return false;
    }

    function setStatus(kind, text) {
        if (!statusBanner) {
            return;
        }
        statusBanner.className = 'form-status ' + (kind === 'error' ? 'form-status--error' : 'form-status--success');
        statusBanner.setAttribute('role', kind === 'error' ? 'alert' : 'status');
        statusBanner.setAttribute('aria-live', kind === 'error' ? 'assertive' : 'polite');
        statusBanner.textContent = text;
        statusBanner.hidden = false;
    }

    function syncDateConstraints() {
        if (!startDateInput || !endDateInput) {
            return;
        }

        const startValue = startDateInput.value;
        endDateInput.min = startValue || '';

        if (startValue && endDateInput.value && endDateInput.value < startValue) {
            endDateInput.setCustomValidity('End date must be on or after start date.');
        } else {
            endDateInput.setCustomValidity('');
        }
    }

    function setSubmitting(isSubmitting) {
        const submitting = !!isSubmitting;
        isSubmitting = submitting;
        actionButtons.forEach(function (button) {
            if (!button.dataset.originalLabel) button.dataset.originalLabel = button.textContent;
            button.disabled = submitting;
            button.textContent = submitting ? (button.value === 'delete' ? 'Deleting...' : 'Updating...') : button.dataset.originalLabel;
        });
    }

    window.addEventListener('beforeunload', function (event) {
        if (isSubmitting || !isDirty()) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    });

    syncDateConstraints();
    startDateInput?.addEventListener('change', syncDateConstraints);
    startDateInput?.addEventListener('input', syncDateConstraints);
    endDateInput?.addEventListener('change', syncDateConstraints);
    endDateInput?.addEventListener('input', syncDateConstraints);

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

        if (intent === 'delete') {
            if (!deleteConfirmed) {
                event.preventDefault();
                if (deleteDialog?.showModal) {
                    if (deleteInput) deleteInput.value = '';
                    if (deleteConfirmButton) deleteConfirmButton.disabled = true;
                    deleteDialog.showModal();
                    if (deleteInput) deleteInput.focus();
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

        syncDateConstraints();

        setSubmitting(true);
    });
});
