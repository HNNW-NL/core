document.addEventListener('DOMContentLoaded', function () {
    const projectDeleteDialog = document.getElementById('projectDeleteDialog');
    if (projectDeleteDialog) {
        document.querySelector('[data-open-project-delete]')?.addEventListener('click', function () {
            projectDeleteDialog.querySelector('form').reset();
            projectDeleteDialog.showModal();
        });
        projectDeleteDialog.querySelector('[data-close-project-delete]')?.addEventListener('click', function () {
            projectDeleteDialog.close();
        });
    }

    // Only attach this behavior when the modify-project form is actually rendered.
    const form = document.querySelector('[data-modify-project-form]');
    if (!form) {
        return;
    }

    const saveButton = form.querySelector('button[type="submit"]');
    const saveLabel = saveButton.textContent;
    const startDateInput = form.querySelector('#startDate');
    const endDateInput = form.querySelector('#endDate');
    const initialSnapshot = new URLSearchParams(new FormData(form)).toString();
    let isSubmitting = false;

    function syncDateConstraints() {
        const startDate = startDateInput.value;
        const endDate = endDateInput.value;
        endDateInput.min = startDate;
        const invalidRange = startDate && endDate && endDate < startDate;
        endDateInput.setCustomValidity(invalidRange ? 'End date must be on or after start date.' : '');
    }

    for (const input of [startDateInput, endDateInput]) {
        input.addEventListener('input', syncDateConstraints);
        input.addEventListener('change', syncDateConstraints);
    }
    syncDateConstraints();

    window.addEventListener('beforeunload', function (event) {
        if (!isSubmitting && new URLSearchParams(new FormData(form)).toString() !== initialSnapshot) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    form.addEventListener('submit', function (event) {
        if (isSubmitting) {
            event.preventDefault();
            return;
        }

        syncDateConstraints();
        if (!form.reportValidity()) {
            event.preventDefault();
            return;
        }

        isSubmitting = true;
        saveButton.disabled = true;
        saveButton.textContent = 'Updating...';
    });

    window.addEventListener('pageshow', function () {
        isSubmitting = false;
        saveButton.disabled = false;
        saveButton.textContent = saveLabel;
        syncDateConstraints();
    });
});
