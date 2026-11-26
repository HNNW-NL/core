function initializeNavigation() {
    const hamburger = document.getElementById('hamburger');
    const navbar = document.getElementById('navbar');

    if (!hamburger || !navbar) {
        return;
    }

    const setExpanded = (expanded) => {
        navbar.classList.toggle('active', expanded);
        hamburger.setAttribute('aria-expanded', String(expanded));
    };

    hamburger.addEventListener('click', () => {
        setExpanded(!navbar.classList.contains('active'));
    });

    navbar.addEventListener('click', (event) => {
        if (event.target.closest('.nav-button')) {
            setExpanded(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (
            event.key === 'Escape' &&
            navbar.classList.contains('active')
        ) {
            setExpanded(false);
            hamburger.focus();
        }
    });

    document.addEventListener('click', (event) => {
        if (
            !navbar.contains(event.target) &&
            !hamburger.contains(event.target)
        ) {
            setExpanded(false);
        }
    });
}

initializeNavigation();
