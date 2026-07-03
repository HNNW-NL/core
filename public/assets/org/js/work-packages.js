document.addEventListener('DOMContentLoaded', function () {
    const tabButtons = document.querySelectorAll('[data-work-package-view]');
    const views = document.querySelectorAll('.work-packages-view');

    tabButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const targetView = document.getElementById(button.dataset.workPackageView);

            if (!targetView) {
                return;
            }

            tabButtons.forEach(function (item) {
                item.setAttribute('aria-pressed', 'false');
            });

            views.forEach(function (view) {
                view.classList.remove('active');
            });

            button.setAttribute('aria-pressed', 'true');
            targetView.classList.add('active');
        });
    });

    const searchInput = document.querySelector('.work-packages-search-input');
    const cardGrid = document.querySelector('.work-package-card-grid');
    const workPackageCards = document.querySelectorAll('.work-package-card');

    if (!searchInput || !cardGrid || workPackageCards.length === 0) {
        return;
    }

    const emptySearchState = document.createElement('div');
    emptySearchState.className = 'work-package-list-empty-state work-package-search-empty-state';
    emptySearchState.hidden = true;
    emptySearchState.innerHTML = '<strong>Geen resultaten</strong><p>Er is geen werkpakket of taak gevonden met deze zoekterm.</p>';
    cardGrid.appendChild(emptySearchState);

    function normalize(value) {
        return value.toLowerCase().trim();
    }

    function filterWorkPackages() {
        const query = normalize(searchInput.value);
        let visibleCardCount = 0;

        workPackageCards.forEach(function (card) {
            const taskRows = card.querySelectorAll('.work-package-task-row');
            const packageText = normalize(card.querySelector('.work-package-card-header')?.textContent || '');
            const descriptionText = normalize(card.querySelector('p')?.textContent || '');
            const packageMatches = packageText.includes(query) || descriptionText.includes(query);
            let matchingTaskCount = 0;

            if (query === '') {
                card.hidden = false;
                taskRows.forEach(function (taskRow) {
                    taskRow.hidden = false;
                });
                visibleCardCount++;
                return;
            }

            taskRows.forEach(function (taskRow) {
                const taskMatches = normalize(taskRow.textContent).includes(query);
                taskRow.hidden = !packageMatches && !taskMatches;

                if (taskMatches) {
                    matchingTaskCount++;
                }
            });

            const shouldShowCard = packageMatches || matchingTaskCount > 0;
            card.hidden = !shouldShowCard;

            if (shouldShowCard) {
                visibleCardCount++;
            }
        });

        emptySearchState.hidden = visibleCardCount > 0;
    }

    searchInput.addEventListener('input', filterWorkPackages);
});
