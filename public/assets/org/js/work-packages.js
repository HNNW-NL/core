document.addEventListener('DOMContentLoaded', function () {
    const tabButtons = document.querySelectorAll('[data-wp-view]');
    const views = document.querySelectorAll('.wp-view');

    tabButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const targetView = document.getElementById(button.dataset.wpView);

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