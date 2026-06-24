document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form');
    if (!form) {
        return;
    }

    const projectIdField = document.getElementById('projectId');
    const organisationIdField = document.getElementById('organisationId');
    const nameField = document.getElementById('name');
    const summaryField = document.getElementById('summary');
    const descField = document.getElementById('description');
    const visibilityField = document.getElementById('visibility');
    const effectiveAtField = document.getElementById('effectiveAt');
    const startDateField = document.getElementById('startDate');
    const endDateField = document.getElementById('endDate');
    const capacityField = document.getElementById('capacity');
    const statusIdField = document.getElementById('statusId');
    const unsavedBadge = document.getElementById('unsavedBadge');
    const deleteDialog = document.getElementById('deleteConfirmDialog');
    const deleteDialogForm = deleteDialog ? deleteDialog.querySelector('.delete-dialog__form') : null;
    const deleteConfirmInput = document.getElementById('deleteConfirmInput');
    const deleteConfirmHint = document.getElementById('deleteConfirmHint');
    const deleteConfirmButton = document.getElementById('deleteConfirmButton');
    const deleteButton = form.querySelector('button[name="intent"][value="delete"]');
    const actionButtons = form.querySelectorAll('button[name="intent"]');
    const timeField = document.getElementById('lastModifiedAt');
    const embeddedProjectRaw = form.dataset.project || '{}';
    const embeddedProjectsRaw = form.dataset.projects || '[]';
    let embeddedProject = {};
    let embeddedProjects = [];
    try {
        embeddedProject = JSON.parse(embeddedProjectRaw);
    } catch (error) {
        embeddedProject = {};
    }
    try {
        embeddedProjects = JSON.parse(embeddedProjectsRaw);
    } catch (error) {
        embeddedProjects = [];
    }

    const url = new URL(window.location.href);
    const pathSegments = url.pathname.split('/').filter(Boolean);
    const idFromPath = pathSegments.length > 0 ? (pathSegments[pathSegments.length - 1] || '').trim() : '';
    const statusFromQuery = (url.searchParams.get('status') || '').trim().toLowerCase();
    const messageFromQuery = (url.searchParams.get('message') || '').trim();

    if (projectIdField && idFromPath !== '') {
        projectIdField.value = idFromPath;
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
    trackedFields.forEach(function (field) {
        initialValues.set(field.id, field.value);
    });

    function hasUnsavedChanges() {
        const textFieldDirty = trackedFields.some(function (field) {
            return field.value !== initialValues.get(field.id);
        });
        return textFieldDirty;
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

    function hasOrganisationContext() {
        return !!(organisationIdField && organisationIdField.value.trim() !== '');
    }

    function enforceOrganisationContext(showMessage) {
        if (!hasOrganisationContext()) {
            if (showMessage) {
                showError('Missing organisation context. Re-open this page with an organisation_id query parameter.');
            }
            return false;
        }

        return true;
    }

    function loadProjectList() {
        if (!projectIdField) {
            loadProject(idFromPath || embeddedProject.id || '');
            return;
        }

        const list = Array.isArray(embeddedProjects)
            ? embeddedProjects.filter(function (project) {
                return project && typeof project.id === 'string' && project.id.trim() !== '';
            })
            : [];

        if (list.length === 0 && embeddedProject && embeddedProject.id) {
            list.push({
                id: (embeddedProject.id || '').toString(),
                title: embeddedProject.title || embeddedProject.name || 'Project',
            });
        }

        const fallbackProjectId = list.length > 0 ? (list[0].id || '').toString() : '';
        const projectId = (embeddedProject.id || idFromPath || fallbackProjectId || '').toString();
        projectIdField.innerHTML = '';

        if (list.length === 0) {
            const emptyOption = document.createElement('option');
            emptyOption.value = '';
            emptyOption.textContent = 'Current project';
            projectIdField.appendChild(emptyOption);
        } else {
            list.forEach(function (project) {
                const option = document.createElement('option');
                option.value = (project.id || '').toString();
                option.textContent = '#' + option.value + ' - ' + (project.title || 'Project');
                projectIdField.appendChild(option);
            });
        }

        projectIdField.value = projectId;
        loadProject(projectId);
    }

    function loadProject(projectId) {
        const foundProject = Array.isArray(embeddedProjects)
            ? embeddedProjects.find(function (item) {
                return item && item.id && item.id.toString() === projectId.toString();
            })
            : null;
        const project = foundProject || embeddedProject || {};
        if (projectIdField) {
            projectIdField.value = (project.id || projectId || '').toString();
        }
        if (organisationIdField) {
            organisationIdField.value = project.organisationId || project.organisation_id || organisationIdField.value || '';
        }
        if (nameField) {
            nameField.value = project.title || project.name || '';
        }
        if (summaryField) {
            summaryField.value = project.summary || '';
        }
        if (descField) {
            descField.value = project.description || '';
        }
        if (visibilityField) {
            visibilityField.value = project.visibility || 'public';
        }
        if (effectiveAtField) {
            effectiveAtField.value = project.effectiveAt || '';
        }

        const modifiedByField = document.getElementById('lastModifiedBy');
        if (modifiedByField) {
            modifiedByField.value = project.lastModifiedBy || project.updatedBy || '';
        }

        if (startDateField) {
            startDateField.value = project.startDate || '';
        }
        if (endDateField) {
            endDateField.value = project.endDate || '';
        }
        if (capacityField) {
            capacityField.value = project.capacity !== null && project.capacity !== undefined ? project.capacity : '';
        }
        if (statusIdField) {
            const resolvedStatus = (project.statusName || project.status_name || project.status || 'draft').toString().trim().toLowerCase();
            statusIdField.value = resolvedStatus;
        }

        trackedFields.forEach(function (field) {
            initialValues.set(field.id, field.value);
            const group = getFieldGroup(field);
            if (group) {
                group.classList.remove('is-dirty');
            }
        });

        updateUnsavedBadge();
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
        if (projectId === '') {
            setFieldState(projectIdField, false, 'Project ID is required.');
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

    function validateStatus() {
        if (!statusIdField) {
            return true;
        }
        const validStatuses = ['draft', 'published', 'archived'];
        const status = statusIdField.value.trim().toLowerCase();
        if (!validStatuses.includes(status)) {
            setFieldState(statusIdField, false, 'Please select a valid status.');
            return false;
        }
        setFieldState(statusIdField, true, 'Looks good.');
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
    wireField(statusIdField, validateStatus);

    if (projectIdField) {
        projectIdField.addEventListener('change', function () {
            const selectedProjectId = projectIdField.value.trim();
            if (selectedProjectId !== '' && selectedProjectId !== idFromPath) {
                const nextUrl = new URL(window.location.href);
                const parts = nextUrl.pathname.split('/').filter(Boolean);
                if (parts.length > 0) {
                    parts[parts.length - 1] = selectedProjectId;
                    nextUrl.pathname = '/' + parts.join('/');
                    window.location.href = nextUrl.toString();
                    return;
                }
            }

            if (selectedProjectId !== '') {
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

    let timeFieldInterval = null;
    if (timeField) {
        const updateTime = function () {
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
        };
        updateTime();
        timeFieldInterval = setInterval(updateTime, 1000);
        window.addEventListener('beforeunload', function () {
            if (timeFieldInterval !== null) {
                clearInterval(timeFieldInterval);
            }
        });
    }

    form.addEventListener('submit', function (event) {
        message.textContent = '';
        message.hidden = true;

        if (!enforceOrganisationContext(true)) {
            event.preventDefault();
            return;
        }

        // Determine the intent from either activeIntent or the submitter button value
        const intent = event.submitter?.value || activeIntent || 'modify';

        if (intent === 'delete') {
            if (!deleteConfirmed) {
                event.preventDefault();
                openDeleteDialog();
                setSubmittingState(false);
                return;
            }

            deleteConfirmed = false;
            showSuccess('Deleting project...');
            setSubmittingState(true);
            // Add hidden intent input for delete
            const intentInput = document.createElement('input');
            intentInput.type = 'hidden';
            intentInput.name = 'intent';
            intentInput.value = 'delete';
            form.appendChild(intentInput);
            return;
        }

        if (!validateProjectId()) {
            showError('Please select a project ID.');
            event.preventDefault();
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

        if (!validateStatus()) {
            showError('Please select a valid status.');
            event.preventDefault();
            return;
        }

        showSuccess('Validation passed. Submitting your changes...');
        setSubmittingState(true);
        
        // Create a hidden input to capture the intent
        const intentInput = document.createElement('input');
        intentInput.type = 'hidden';
        intentInput.name = 'intent';
        intentInput.value = event.submitter?.value || activeIntent || 'modify';
        form.appendChild(intentInput);
        
        // Now submit the form
        form.submit();
    });

    loadProjectList();
});
