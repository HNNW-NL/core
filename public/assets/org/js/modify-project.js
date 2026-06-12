document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form');
    if (!form) {
        return;
    }

    const projectIdField = document.getElementById('projectId');
    const nameField = document.getElementById('name');
    const summaryField = document.getElementById('summary');
    const descField = document.getElementById('description');
    const visibilityField = document.getElementById('visibility');
    const effectiveAtField = document.getElementById('effectiveAt');
    const fileField = document.getElementById('projectFile');
    const clearFileButton = document.getElementById('clearFileSelection');
    const unsavedBadge = document.getElementById('unsavedBadge');
    const deleteDialog = document.getElementById('deleteConfirmDialog');
    const deleteDialogForm = deleteDialog ? deleteDialog.querySelector('.delete-dialog__form') : null;
    const deleteConfirmInput = document.getElementById('deleteConfirmInput');
    const deleteConfirmHint = document.getElementById('deleteConfirmHint');
    const deleteConfirmButton = document.getElementById('deleteConfirmButton');
    const deleteButton = form.querySelector('button[name="intent"][value="delete"]');
    const actionButtons = form.querySelectorAll('button[name="intent"]');
    const timeField = document.getElementById('lastModifiedAt');

    let activeIntent = 'modify';
    let deleteConfirmed = false;
    let message = form.querySelector('.form-status');

    if (!message) {
        message = document.createElement('p');
        message.className = 'form-status form-status--error';
        form.insertBefore(message, form.firstChild);
    }

    let fileMeta = form.querySelector('.file-meta');
    if (!fileMeta && fileField) {
        fileMeta = document.createElement('p');
        fileMeta.className = 'file-meta';
        fileMeta.setAttribute('aria-live', 'polite');
        fileField.insertAdjacentElement('afterend', fileMeta);
    }

    const allowedFileTypes = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg', 'zip', 'js', 'ts', 'py', 'java', 'html', 'css', 'json', 'md'];
    const maxFileSize = 10 * 1024 * 1024;

    const trackedFields = [nameField, summaryField, descField, visibilityField, effectiveAtField].filter(Boolean);
    const initialValues = new Map();
    trackedFields.forEach(function (field) {
        initialValues.set(field.id, field.value);
    });

    function hasUnsavedChanges() {
        const textFieldDirty = trackedFields.some(function (field) {
            return field.value !== initialValues.get(field.id);
        });
        const fileDirty = !!(fileField && fileField.files.length > 0);
        return textFieldDirty || fileDirty;
    }

    function updateUnsavedBadge() {
        if (!unsavedBadge) {
            return;
        }
        const dirty = hasUnsavedChanges();
        unsavedBadge.hidden = !dirty;
        unsavedBadge.textContent = dirty ? 'Unsaved changes' : 'All changes saved';
    }

    actionButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            activeIntent = button.value;
        });
    });

    function showError(text) {
        message.textContent = text;
        message.className = 'form-status form-status--error';
    }

    function showSuccess(text) {
        message.textContent = text;
        message.className = 'form-status form-status--success';
    }

    function getFieldGroup(field) {
        if (!field) {
            return null;
        }
        return field.closest('div');
    }

    function getOrCreateFeedback(field) {
        const group = getFieldGroup(field);
        if (!group) {
            return null;
        }
        let feedback = group.querySelector('.field-feedback');
        if (!feedback) {
            feedback = document.createElement('p');
            feedback.className = 'field-feedback';
            feedback.setAttribute('aria-live', 'polite');
            group.appendChild(feedback);
        }
        return feedback;
    }

    function setFieldState(field, isValid, text) {
        const group = getFieldGroup(field);
        const feedback = getOrCreateFeedback(field);
        if (!group || !feedback) {
            return;
        }

        group.classList.remove('is-valid', 'is-invalid');
        if (isValid === true) {
            group.classList.add('is-valid');
            feedback.classList.remove('field-feedback--error');
            feedback.classList.add('field-feedback--success');
        } else if (isValid === false) {
            group.classList.add('is-invalid');
            feedback.classList.remove('field-feedback--success');
            feedback.classList.add('field-feedback--error');
        } else {
            feedback.classList.remove('field-feedback--success', 'field-feedback--error');
        }

        feedback.textContent = text || '';
    }

    function formatFileSize(bytes) {
        if (bytes < 1024) {
            return bytes + ' B';
        }
        if (bytes < 1024 * 1024) {
            return (bytes / 1024).toFixed(1) + ' KB';
        }
        return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    }

    function validateProjectId() {
        const projectId = projectIdField ? projectIdField.value.trim() : '';
        if (!/^\d+$/.test(projectId)) {
            setFieldState(projectIdField, false, 'Project ID must be numeric.');
            return false;
        }
        setFieldState(projectIdField, true, 'Looks good.');
        return true;
    }

    function validateName() {
        if (!nameField || nameField.value.trim() === '') {
            setFieldState(nameField, false, 'Please enter a project name.');
            return false;
        }
        setFieldState(nameField, true, 'Looks good.');
        return true;
    }

    function validateSummary() {
        if (!summaryField || summaryField.value.trim().length < 5) {
            setFieldState(summaryField, false, 'Summary must be at least 5 characters.');
            return false;
        }
        setFieldState(summaryField, true, 'Looks good.');
        return true;
    }

    function validateDescription() {
        if (!descField || descField.value.trim().length < 10) {
            setFieldState(descField, false, 'Description must be at least 10 characters.');
            return false;
        }
        setFieldState(descField, true, 'Looks good.');
        return true;
    }

    function validateFile() {
        if (!fileField || fileField.files.length === 0) {
            if (clearFileButton) {
                clearFileButton.hidden = true;
            }
            if (fileMeta) {
                fileMeta.textContent = 'No replacement file selected.';
                fileMeta.className = 'file-meta';
            }
            return true;
        }

        if (clearFileButton) {
            clearFileButton.hidden = false;
        }

        const file = fileField.files[0];
        const ext = file.name.includes('.') ? file.name.split('.').pop().toLowerCase() : '';
        if (!allowedFileTypes.includes(ext)) {
            if (fileMeta) {
                fileMeta.textContent = 'Unsupported file type. Choose a listed format.';
                fileMeta.className = 'file-meta file-meta--error';
            }
            return false;
        }

        if (file.size > maxFileSize) {
            if (fileMeta) {
                fileMeta.textContent = 'File is too large. Max size is 10 MB.';
                fileMeta.className = 'file-meta file-meta--error';
            }
            return false;
        }

        if (fileMeta) {
            fileMeta.textContent = 'Selected: ' + file.name + ' (' + formatFileSize(file.size) + ').';
            fileMeta.className = 'file-meta file-meta--success';
        }
        return true;
    }

    function updateDirtyState(field) {
        if (!field || !initialValues.has(field.id)) {
            return;
        }
        const group = getFieldGroup(field);
        if (!group) {
            return;
        }

        const hasChanged = field.value !== initialValues.get(field.id);
        group.classList.toggle('is-dirty', hasChanged);
        updateUnsavedBadge();
    }

    function refreshDeleteConfirmationState() {
        if (!deleteConfirmInput || !deleteConfirmButton) {
            return;
        }
        const expected = projectIdField ? projectIdField.value.trim() : '';
        const entered = deleteConfirmInput.value.trim();
        const matches = expected !== '' && entered === expected;
        deleteConfirmButton.disabled = !matches;

        if (!deleteConfirmHint) {
            return;
        }
        if (entered === '') {
            deleteConfirmHint.textContent = 'The value must match the Project ID field above.';
        } else if (matches) {
            deleteConfirmHint.textContent = 'Match confirmed. You can delete permanently.';
        } else {
            deleteConfirmHint.textContent = 'Value does not match the current Project ID.';
        }
    }

    function openDeleteDialog() {
        const expected = projectIdField ? projectIdField.value.trim() : '';
        if (!/^\d+$/.test(expected)) {
            showError('Enter a valid numeric project ID before deleting.');
            return false;
        }

        if (deleteDialog && typeof deleteDialog.showModal === 'function') {
            if (deleteConfirmInput) {
                deleteConfirmInput.value = '';
            }
            refreshDeleteConfirmationState();
            deleteDialog.showModal();
            if (deleteConfirmInput) {
                deleteConfirmInput.focus();
            }
            return true;
        }

        const typed = window.prompt('Type Project ID ' + expected + ' to confirm deletion:');
        if (typed === null) {
            return false;
        }
        if (typed.trim() !== expected) {
            showError('Deletion cancelled: Project ID did not match.');
            return false;
        }
        deleteConfirmed = true;
        form.requestSubmit(deleteButton || undefined);
        return true;
    }

    function setSubmittingState(isSubmitting) {
        actionButtons.forEach(function (button) {
            if (!button.dataset.originalLabel) {
                button.dataset.originalLabel = button.textContent;
            }
            if (isSubmitting) {
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.textContent = button.value === 'delete' ? 'Deleting...' : 'Updating...';
            } else {
                button.disabled = false;
                button.removeAttribute('aria-busy');
                button.textContent = button.dataset.originalLabel;
            }
        });
    }

    function wireField(field, validator) {
        if (!field) {
            return;
        }

        field.addEventListener('input', function () {
            if (typeof validator === 'function') {
                validator();
            }
            updateDirtyState(field);
        });

        field.addEventListener('blur', function () {
            if (typeof validator === 'function') {
                validator();
            }
            updateDirtyState(field);
        });
    }

    wireField(projectIdField, validateProjectId);
    wireField(nameField, validateName);
    wireField(summaryField, validateSummary);
    wireField(descField, validateDescription);
    wireField(visibilityField, null);
    wireField(effectiveAtField, null);

    if (fileField) {
        fileField.addEventListener('change', function () {
            validateFile();
            const group = getFieldGroup(fileField);
            if (group) {
                group.classList.toggle('is-dirty', !!(fileField && fileField.files.length > 0));
            }
            updateUnsavedBadge();
        });
    }

    if (clearFileButton && fileField) {
        clearFileButton.addEventListener('click', function () {
            fileField.value = '';
            const group = getFieldGroup(fileField);
            if (group) {
                group.classList.remove('is-dirty');
            }
            validateFile();
            updateUnsavedBadge();
            fileField.focus();
        });
    }

    if (deleteConfirmInput) {
        deleteConfirmInput.addEventListener('input', refreshDeleteConfirmationState);
    }

    if (deleteDialogForm) {
        deleteDialogForm.addEventListener('submit', function (event) {
            if (!event.submitter || event.submitter.value !== 'confirm') {
                deleteConfirmed = false;
                return;
            }

            const expected = projectIdField ? projectIdField.value.trim() : '';
            const entered = deleteConfirmInput ? deleteConfirmInput.value.trim() : '';
            if (entered !== expected) {
                event.preventDefault();
                refreshDeleteConfirmationState();
                return;
            }
        });
    }

    if (deleteDialog) {
        deleteDialog.addEventListener('close', function () {
            if (deleteDialog.returnValue !== 'confirm') {
                deleteConfirmed = false;
                setSubmittingState(false);
                return;
            }

            const expected = projectIdField ? projectIdField.value.trim() : '';
            const entered = deleteConfirmInput ? deleteConfirmInput.value.trim() : '';
            if (entered !== expected) {
                deleteConfirmed = false;
                showError('Deletion cancelled: Project ID did not match.');
                setSubmittingState(false);
                return;
            }

            deleteConfirmed = true;
            form.requestSubmit(deleteButton || undefined);
        });
    }

    validateFile();
    updateUnsavedBadge();

    window.requestAnimationFrame(function () {
        document.body.classList.add('page-ready');
    });

    if (timeField) {
        setInterval(function () {
            const now = new Date();
            const pad = function (value) {
                return String(value).padStart(2, '0');
            };
            timeField.value =
                now.getFullYear() +
                '-' +
                pad(now.getMonth() + 1) +
                '-' +
                pad(now.getDate()) +
                ' ' +
                pad(now.getHours()) +
                ':' +
                pad(now.getMinutes()) +
                ':' +
                pad(now.getSeconds());
        }, 1000);
    }

    form.addEventListener('submit', function (event) {
        message.textContent = '';
        setSubmittingState(false);

        if (!validateProjectId()) {
            showError('Enter a valid numeric project ID.');
            event.preventDefault();
            return;
        }

        if (activeIntent === 'delete') {
            if (!deleteConfirmed) {
                event.preventDefault();
                openDeleteDialog();
                setSubmittingState(false);
                return;
            }

            deleteConfirmed = false;
            showSuccess('Deleting project...');
            setSubmittingState(true);
            return;
        }

        if (!validateName()) {
            showError('Please enter a project name.');
            event.preventDefault();
            return;
        }

        if (!validateSummary()) {
            showError('Summary is too short. Minimum 5 characters.');
            event.preventDefault();
            return;
        }

        if (!validateDescription()) {
            showError('Description is too short. Minimum 10 characters.');
            event.preventDefault();
            return;
        }

        if (!validateFile()) {
            showError('Please choose a valid file (supported type, max 10 MB).');
            event.preventDefault();
            return;
        }

        showSuccess('Validation passed. Submitting your changes...');
        setSubmittingState(true);
    });

    if (deleteButton) {
        deleteButton.classList.add('button-danger');
    }
});
