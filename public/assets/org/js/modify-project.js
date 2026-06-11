document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form');
    if (!form) {
        return;
    }

    const projectIdField = document.getElementById('projectId');
    const nameField = document.getElementById('name');
    const summaryField = document.getElementById('summary');
    const descField = document.getElementById('description');
    const fileField = document.getElementById('projectFile');
    const deleteButton = form.querySelector('button[name="intent"][value="delete"]');
    const actionButtons = form.querySelectorAll('button[name="intent"]');
    const timeField = document.getElementById('lastModifiedAt');

    let activeIntent = 'modify';
    let message = form.querySelector('.form-status');

    if (!message) {
        message = document.createElement('p');
        message.className = 'form-status form-status--error';
        form.insertBefore(message, form.firstChild);
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

        const projectId = projectIdField ? projectIdField.value.trim() : '';
        if (!/^\d+$/.test(projectId)) {
            showError('Enter a valid numeric project ID.');
            event.preventDefault();
            return;
        }

        if (activeIntent === 'delete') {
            if (!window.confirm('Delete this project permanently?')) {
                event.preventDefault();
            }
            return;
        }

        if (!nameField || nameField.value.trim() === '') {
            showError('Please enter a project name.');
            event.preventDefault();
            return;
        }

        if (!summaryField || summaryField.value.trim().length < 5) {
            showError('Summary is too short. Minimum 5 characters.');
            event.preventDefault();
            return;
        }

        if (!descField || descField.value.trim().length < 10) {
            showError('Description is too short. Minimum 10 characters.');
            event.preventDefault();
            return;
        }

        if (fileField && fileField.files.length > 0) {
            const file = fileField.files[0];
            const maxSize = 10 * 1024 * 1024;
            const allowed = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg', 'zip', 'js', 'ts', 'py', 'java', 'html', 'css', 'json', 'md'];
            const ext = file.name.includes('.') ? file.name.split('.').pop().toLowerCase() : '';

            if (!allowed.includes(ext)) {
                showError('Please choose a valid file type.');
                event.preventDefault();
                return;
            }

            if (file.size > maxSize) {
                showError('File is too large. Maximum size is 10MB.');
                event.preventDefault();
                return;
            }
        }
    });

    if (deleteButton) {
        deleteButton.classList.add('button-danger');
    }
});
