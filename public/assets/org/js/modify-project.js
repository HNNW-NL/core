document.addEventListener('DOMContentLoaded', function () {
    // Only attach this behavior when the modify-project form is actually rendered.
    const form = document.querySelector('[data-modify-project-form]');
    if (!form) {
        return;
    }

    const deleteConfirmKeyword = 'DELETE';
    const submittingDeleteLabel = 'Deleting...';
    const submittingModifyLabel = 'Updating...';

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

    function setForcedIntent(value) {
        let forcedIntentInput = form.querySelector('input[data-forced-intent="true"]');

        if (!value) {
            if (forcedIntentInput) {
                forcedIntentInput.remove();
            }
            return;
        }

        if (!forcedIntentInput) {
            forcedIntentInput = document.createElement('input');
            forcedIntentInput.type = 'hidden';
            forcedIntentInput.name = 'intent';
            forcedIntentInput.setAttribute('data-forced-intent', 'true');
            form.appendChild(forcedIntentInput);
        }

        forcedIntentInput.value = value;
    }

    function isDirty() {
        const current = new FormData(form);
        const keys = new Set([...initialSnapshot.keys(), ...current.keys()]);

        // Compare the original form data with the current form data to detect unsaved edits.
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
        // Update a single banner instead of rendering separate success and error messages.
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

        // Keep the end date from going before the start date and clear the warning when valid again.
        const startValue = startDateInput.value;
        endDateInput.min = startValue || '';

        if (startValue && endDateInput.value && endDateInput.value < startValue) {
            endDateInput.setCustomValidity('End date must be on or after start date.');
        } else {
            endDateInput.setCustomValidity('');
        }
    }

    function setSubmitting(submittingState) {
        const submitting = !!submittingState;
        isSubmitting = submitting;
        // Lock all intent buttons during submit so the form cannot be sent twice.
        actionButtons.forEach(function (button) {
            if (!button.dataset.originalLabel) button.dataset.originalLabel = button.textContent;
            button.disabled = submitting;
            button.textContent = submitting ? (button.value === 'delete' ? submittingDeleteLabel : submittingModifyLabel) : button.dataset.originalLabel;
        });
    }

    window.addEventListener('beforeunload', function (event) {
        // Warn about unsaved work only when the user is leaving with edits that were not submitted.
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
            const ok = deleteInput.value.trim() === deleteConfirmKeyword;
            if (deleteConfirmButton) deleteConfirmButton.disabled = !ok;
            if (deleteHint) deleteHint.textContent = ok ? 'Confirmed. You can delete permanently.' : 'Type DELETE (all caps) to enable the button.';
        });
    }

    deleteDialog?.addEventListener('close', function () {
        // Proceed only when the modal was confirmed and the user typed the exact required word.
        if (deleteDialog.returnValue !== 'confirm' || (deleteInput && deleteInput.value.trim() !== deleteConfirmKeyword)) {
            deleteConfirmed = false;
            setForcedIntent(null);
            setSubmitting(false);
            return;
        }

        deleteConfirmed = true;
        setForcedIntent('delete');
        form.requestSubmit(deleteButton || undefined);
    });

    form.addEventListener('submit', function (event) {
        if (statusBanner) {
            statusBanner.hidden = true;
            statusBanner.textContent = '';
        }

        const forcedIntent = form.querySelector('input[data-forced-intent="true"]')?.value;
        const intent = event.submitter?.value || forcedIntent || 'modify';

        if (intent === 'delete') {
            // The first delete click opens the dialog; the second confirmed submit actually deletes.
            if (!deleteConfirmed) {
                event.preventDefault();
                setForcedIntent(null);
                if (deleteDialog?.showModal) {
                    if (deleteInput) deleteInput.value = '';
                    if (deleteConfirmButton) deleteConfirmButton.disabled = true;
                    deleteDialog.showModal();
                    if (deleteInput) deleteInput.focus();
                } else {
                    const typed = window.prompt('Type DELETE to confirm permanent deletion:');
                    if ((typed || '').trim() === deleteConfirmKeyword) {
                        deleteConfirmed = true;
                        setForcedIntent('delete');
                        form.requestSubmit(deleteButton || undefined);
                    } else {
                        setForcedIntent(null);
                        setStatus('error', 'Deletion cancelled.');
                    }
                }
                return;
            }
            // Reset the confirmation flag so the next delete action must be verified again.
            deleteConfirmed = false;
            setStatus('success', 'Deleting project...');
            setSubmitting(true);
            return;
        }

        setForcedIntent(null);
        syncDateConstraints();

        setSubmitting(true);
    });
});
