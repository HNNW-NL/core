// thema van de site: auto (volgt het systeem), light of dark
// de keuze staat in localStorage onder 'hnnw-theme'; het resultaat komt op body[data-theme]
(function () {
    const OPSLAG_SLEUTEL = 'hnnw-theme';
    const systeem = window.matchMedia('(prefers-color-scheme: dark)');

    // de keuze die nu actief is; zo werkt het rondgaan ook als localStorage niet mag
    let huidigeKeuze = 'auto';

    // leest de bewaarde keuze; alles wat geen geldige waarde is wordt auto
    function bewaardeKeuze() {
        let keuze = null;
        try {
            keuze = window.localStorage.getItem(OPSLAG_SLEUTEL);
        } catch (fout) {
            keuze = null;
        }
        if (keuze === 'light' || keuze === 'dark') {
            return keuze;
        }
        return 'auto';
    }

    // auto wordt light of dark op basis van het systeem
    function echtThema(keuze) {
        if (keuze === 'auto') {
            return systeem.matches ? 'dark' : 'light';
        }
        return keuze;
    }

    // zet het thema op body en werkt icoon, tekst en title van de knop bij (zoals op hnnw.nl)
    function pasToe(keuze) {
        huidigeKeuze = keuze;
        document.body.dataset.theme = echtThema(keuze);

        const knop = document.querySelector('.theme-toggle');
        if (!knop) {
            return;
        }

        let icoon = '⟳';
        let tekst = 'Auto';
        let titel = 'Automatisch (volgt systeem)';
        if (keuze === 'light') {
            icoon = '☀️';
            tekst = 'Licht';
            titel = 'Lichte modus';
        }
        if (keuze === 'dark') {
            icoon = '🌙';
            tekst = 'Donker';
            titel = 'Donkere modus';
        }

        knop.querySelector('.theme-icon').textContent = icoon;
        knop.querySelector('.theme-label').textContent = tekst;
        knop.title = titel;
        knop.setAttribute('aria-label', titel + '. Klik om te wisselen');
    }

    // dit script staat direct na <body>, dus body bestaat al en het thema staat voor de pagina tekent
    if (document.body) {
        pasToe(bewaardeKeuze());
    }

    // systeem wisselt van licht naar donker (of andersom) terwijl de keuze auto is
    systeem.addEventListener('change', function () {
        if (bewaardeKeuze() === 'auto') {
            pasToe('auto');
        }
    });

    // de knop zit in de header, die is er pas als de hele pagina geladen is
    // elke klik gaat één stap verder: auto, dan licht, dan donker, dan weer auto
    document.addEventListener('DOMContentLoaded', function () {
        const knop = document.querySelector('.theme-toggle');
        if (knop) {
            knop.addEventListener('click', function () {
                let keuze = 'auto';
                if (huidigeKeuze === 'auto') {
                    keuze = 'light';
                } else if (huidigeKeuze === 'light') {
                    keuze = 'dark';
                }
                try {
                    window.localStorage.setItem(OPSLAG_SLEUTEL, keuze);
                } catch (fout) {
                    // geen opslag mogelijk (bijv. privé-modus), het thema wisselt dan alleen voor deze pagina
                }
                pasToe(keuze);
            });
        }
        pasToe(bewaardeKeuze());
    });
})();
