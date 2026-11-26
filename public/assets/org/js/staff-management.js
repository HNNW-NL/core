document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('staff-search');
    const staffCards = document.querySelectorAll('.staff-card');
    const noResults = document.getElementById('staff-no-results');

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const searchTerm = searchInput.value.toLowerCase().trim();
            let visibleCount = 0;

            staffCards.forEach(function (card) {
                const staffText = [
                    card.querySelector('.staff-name')?.textContent || '',
                    card.querySelector('.staff-email')?.textContent || '',
                    card.querySelector('.staff-role')?.textContent || ''
                ].join(' ').toLowerCase();

                const matches = staffText.includes(searchTerm);
                card.style.display = matches ? '' : 'none';

                if (matches) {
                    visibleCount++;
                }
            });

            if (noResults) {
                noResults.hidden = visibleCount > 0;
            }
        });
    }

    const addButton = document.getElementById('staff-add-button');
    const addForm = document.getElementById('staff-add-form');
    const cancelButton = document.getElementById('staff-add-cancel');

    document.querySelectorAll('.staff-delete-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm('Wil je deze medewerker verwijderen?')) {
                event.preventDefault();
            }
        });
    });

    if (addButton && addForm) {
        addButton.addEventListener('click', function () {
            addForm.hidden = false;
            addButton.hidden = true;
            document.getElementById('staff-add-email')?.focus();
        });
    }

    if (cancelButton && addForm && addButton) {
        cancelButton.addEventListener('click', function () {
            addForm.hidden = true;
            addButton.hidden = false;
        });
    }
});
