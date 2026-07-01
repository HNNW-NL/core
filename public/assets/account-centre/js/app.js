document.addEventListener('DOMContentLoaded', function() {
    const isCurrentCheckbox = document.getElementById('exp-is-current');
    const endDateInput = document.getElementById('exp-end-date');

    if (isCurrentCheckbox && endDateInput) {
        isCurrentCheckbox.addEventListener('change', function() {
            if (this.checked) {
                endDateInput.value = '';
                endDateInput.disabled = true;
                endDateInput.removeAttribute('required');
            } else {
                endDateInput.disabled = false;
            }
        });
    }
});
