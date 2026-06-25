    document.addEventListener('DOMContentLoaded', function () {
            const tabButtons = document.querySelectorAll('[data-wp-view]');
            const views = document.querySelectorAll('.wp-view');

            tabButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    tabButtons.forEach(function (item) {
                        item.setAttribute('aria-pressed', 'false');
                    });

                    views.forEach(function (view) {
                        view.classList.remove('active');
                    });

                    button.setAttribute('aria-pressed', 'true');
                    document.getElementById(button.dataset.wpView).classList.add('active');
                });
            });
        });