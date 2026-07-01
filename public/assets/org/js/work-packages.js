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
});
