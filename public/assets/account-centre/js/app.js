document.addEventListener('DOMContentLoaded', function() {
    const currentCheckbox = document.getElementById('exp-is-current');
    const endDateInput = document.getElementById('exp-end-date');

    if (currentCheckbox && endDateInput) {
        currentCheckbox.addEventListener('change', function() {
            endDateInput.disabled = this.checked;
            if (this.checked) {
                endDateInput.value = '';
            }
        });

        if (currentCheckbox.checked) {
            endDateInput.disabled = true;
        }
    }
});
