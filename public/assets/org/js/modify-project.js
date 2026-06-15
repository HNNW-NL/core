document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form');
    if (!form) {
        return;
    }

    const projectIdField = document.getElementById('projectId');
    const currentNameField = document.getElementById('currentName');
    const currentSummaryField = document.getElementById('currentSummary');
    const currentVisibilityField = document.getElementById('currentVisibility');
    const nameField = document.getElementById('name');
    const summaryField = document.getElementById('summary');
    const descField = document.getElementById('description');
    const visibilityField = document.getElementById('visibility');
    const effectiveAtField = document.getElementById('effectiveAt');
    const startDateField = document.getElementById('startDate');
    const endDateField = document.getElementById('endDate');
    const capacityField = document.getElementById('capacity');
    const remotePossibleField = document.getElementById('remotePossible');
    const statusIdField = document.getElementById('statusId');
    const currentStartDateField = document.getElementById('currentStartDate');
    const currentEndDateField = document.getElementById('currentEndDate');
    const currentCapacityField = document.getElementById('currentCapacity');
    const currentRemotePossibleField = document.getElementById('currentRemotePossible');
    const currentStatusField = document.getElementById('currentStatus');
    const currentPublishedAtField = document.getElementById('currentPublishedAt');
    const unsavedBadge = document.getElementById('unsavedBadge');
    const deleteDialog = document.getElementById('deleteConfirmDialog');
    const deleteDialogForm = deleteDialog ? deleteDialog.querySelector('.delete-dialog__form') : null;
    const deleteConfirmInput = document.getElementById('deleteConfirmInput');
    const deleteConfirmHint = document.getElementById('deleteConfirmHint');
    const deleteConfirmButton = document.getElementById('deleteConfirmButton');
    const deleteButton = form.querySelector('button[name="intent"][value="delete"]');
    const actionButtons = form.querySelectorAll('button[name="intent"]');
    const timeField = document.getElementById('lastModifiedAt');

    const url = new URL(window.location.href);
    const idFromQuery = (url.searchParams.get('id') || '').trim();
    const statusFromQuery = (url.searchParams.get('status') || '').trim().toLowerCase();
    const messageFromQuery = (url.searchParams.get('message') || '').trim();

    if (projectIdField && idFromQuery !== '') {
        projectIdField.value = idFromQuery;
    }

    let activeIntent = 'modify';
    let deleteConfirmed = false;
    let message = form.querySelector('.form-status');

    if (!message) {
        message = document.createElement('p');
        message.className = 'form-status form-status--error';
        form.insertBefore(message, form.firstChild);
    }

    const trackedFields = [nameField, summaryField, descField, visibilityField, effectiveAtField, startDateField, endDateField, capacityField, statusIdField].filter(Boolean);
    const initialValues = new Map();
    let initialRemotePossible = false;
    trackedFields.forEach(function (field) {
        initialValues.set(field.id, field.value);
    });

    function hasUnsavedChanges() {
        const textFieldDirty = trackedFields.some(function (field) {
            return field.value !== initialValues.get(field.id);
        });
        const remoteDirty = !!(remotePossibleField && remotePossibleField.checked !== initialRemotePossible);
        return textFieldDirty || remoteDirty;
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
        message.hidden = false;
    }

    function showSuccess(text) {
        message.textContent = text;
        message.className = 'form-status form-status--success';
        message.hidden = false;
    }

    async function loadProjectList() {
        try {
            const response = await fetch('handler.php?fetch=list', {
                headers: {
                    Accept: 'application/json'
                }
            });
            const payload = await response.json();
            if (!response.ok || !payload.ok) {
                showError((payload && payload.message) ? payload.message : 'Could not load project list.');
                return;
            }

            const projects = Array.isArray(payload.projects) ? payload.projects : [];
            if (!projectIdField) {
                return;
            }

            projectIdField.innerHTML = '';
            projects.forEach(function (project) {
                const option = document.createElement('option');
                option.value = project.id;
                option.textContent = '#' + project.id + ' - ' + project.name;
                projectIdField.appendChild(option);
            });

            if (projects.length === 0) {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'No projects available';
                projectIdField.appendChild(option);
                return;
            }

            const chosenId = idFromQuery !== '' ? idFromQuery : projects[0].id;
            projectIdField.value = chosenId;
            await loadProject(chosenId);
        } catch (error) {
            showError('Could not load project list.');
        }
    }

    async function loadProject(projectId) {
        try {
            const response = await fetch('handler.php?fetch=1&id=' + encodeURIComponent(projectId), {
                headers: {
                    Accept: 'application/json'
                }
            });

            const payload = await response.json();
            if (!response.ok || !payload.ok) {
                showError((payload && payload.message) ? payload.message : 'Could not load project details.');
                return;
            }

            const project = payload.project || {};
            if (projectIdField) {
                projectIdField.value = project.id || projectId;
            }
            if (currentNameField) {
                currentNameField.value = project.name || '';
            }
            if (currentSummaryField) {
                currentSummaryField.value = project.summary || '';
            }
            if (currentVisibilityField) {
                currentVisibilityField.value = project.visibility || 'public';
            }
            if (nameField && !nameField.value) {
                nameField.value = project.name || '';
            }
            if (summaryField && !summaryField.value) {
                summaryField.value = project.summary || '';
            }
            if (descField && !descField.value) {
                descField.value = project.description || '';
            }
            if (visibilityField) {
                visibilityField.value = project.visibility || 'public';
            }
            if (effectiveAtField && project.effectiveAt) {
                effectiveAtField.value = project.effectiveAt;
            }

            const modifiedByField = document.getElementById('lastModifiedBy');
            if (modifiedByField && project.updatedBy) {
                modifiedByField.value = project.updatedBy;
            }

            if (currentStartDateField) {
                currentStartDateField.value = project.startDate || '';
            }
            if (currentEndDateField) {
                currentEndDateField.value = project.endDate || '';
            }
            if (currentCapacityField) {
                currentCapacityField.value = project.capacity !== null && project.capacity !== undefined ? project.capacity : '';
            }
            if (currentRemotePossibleField) {
                currentRemotePossibleField.value = project.remotePossible ? 'Yes' : 'No';
            }
            if (currentStatusField) {
                const statusLabels = { 1: 'Draft', 2: 'Active', 3: 'Closed' };
                currentStatusField.value = statusLabels[project.statusId] || 'Unknown';
            }
            if (currentPublishedAtField) {
                currentPublishedAtField.value = project.publishedAt || '';
            }
            if (startDateField && !startDateField.value) {
                startDateField.value = project.startDate || '';
            }
            if (endDateField && !endDateField.value) {
                endDateField.value = project.endDate || '';
            }
            if (capacityField && !capacityField.value) {
                capacityField.value = project.capacity !== null && project.capacity !== undefined ? project.capacity : '';
            }
            if (remotePossibleField) {
                remotePossibleField.checked = !!project.remotePossible;
            }
            if (statusIdField) {
                statusIdField.value = project.statusId || 1;
            }

            trackedFields.forEach(function (field) {
                initialValues.set(field.id, field.value);
                const group = getFieldGroup(field);
                if (group) {
                    group.classList.remove('is-dirty');
                }
            });

            initialRemotePossible = !!(remotePossibleField && remotePossibleField.checked);

            updateUnsavedBadge();
        } catch (error) {
            showError('Could not load project details.');
        }
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
        const entered = deleteConfirmInput.value.trim();
        const matches = entered === 'DELETE';
        deleteConfirmButton.disabled = !matches;

        if (!deleteConfirmHint) {
            return;
        }
        if (entered === '') {
            deleteConfirmHint.textContent = 'Type DELETE (all caps) to enable the button.';
        } else if (matches) {
            deleteConfirmHint.textContent = 'Confirmed. You can delete permanently.';
        } else {
            deleteConfirmHint.textContent = 'Must be exactly DELETE in all caps.';
        }
    }

    function openDeleteDialog() {
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

        const typed = window.prompt('Type DELETE to confirm permanent deletion:');
        if (typed === null) {
            return false;
        }
        if (typed.trim() !== 'DELETE') {
            showError('Deletion cancelled: confirmation did not match.');
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

    wireField(nameField, validateName);
    wireField(summaryField, validateSummary);
    wireField(descField, validateDescription);
    wireField(visibilityField, null);
    wireField(effectiveAtField, null);
    wireField(startDateField, null);
    wireField(endDateField, null);
    wireField(capacityField, null);
    wireField(statusIdField, null);

    if (remotePossibleField) {
        remotePossibleField.addEventListener('change', function () {
            updateUnsavedBadge();
        });
    }

    if (projectIdField) {
        projectIdField.addEventListener('change', function () {
            const selectedProjectId = projectIdField.value.trim();
            if (/^\d+$/.test(selectedProjectId)) {
                loadProject(selectedProjectId);
            }
        });
    }

    if (statusFromQuery && messageFromQuery) {
        if (statusFromQuery === 'error') {
            showError(messageFromQuery);
        } else {
            showSuccess(messageFromQuery);
        }

        url.searchParams.delete('status');
        url.searchParams.delete('message');
        window.history.replaceState({}, document.title, url.toString());
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

            const entered = deleteConfirmInput ? deleteConfirmInput.value.trim() : '';
            if (entered !== 'DELETE') {
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

            const entered = deleteConfirmInput ? deleteConfirmInput.value.trim() : '';
            if (entered !== 'DELETE') {
                deleteConfirmed = false;
                showError('Deletion cancelled: confirmation did not match.');
                setSubmittingState(false);
                return;
            }

            deleteConfirmed = true;
            form.requestSubmit(deleteButton || undefined);
        });
    }

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
        message.hidden = true;
        setSubmittingState(false);

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

        showSuccess('Validation passed. Submitting your changes...');
        setSubmittingState(true);
    });

    if (deleteButton) {
        deleteButton.classList.add('button-danger');
    }

    loadProjectList();
});
