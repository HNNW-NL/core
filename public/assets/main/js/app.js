// menu-knop (hamburger) van de header: op mobiel klapt het menu open en dicht
const hamburger = document.getElementById('hamburger');
const navbar = document.getElementById('navbar');

// stoppen als de header er niet is, anders geeft de regel hieronder een fout
if (hamburger && navbar) {
    hamburger.addEventListener('click', () => {
        navbar.classList.toggle('active');
    });

    const navLinks = document.querySelectorAll('.nav-button');
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            navbar.classList.remove('active');
        });
    });
}
