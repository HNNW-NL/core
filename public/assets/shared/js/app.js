// thema van de site: auto (volgt het systeem), light of dark
// de keuze staat in localStorage onder 'hnnw-theme'; het resultaat komt op body[data-theme]
(function () {
    const OPSLAG_SLEUTEL = 'hnnw-theme';
    const systeem = window.matchMedia('(prefers-color-scheme: dark)');

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

    // zet het thema op body en markeert de actieve knop
    function pasToe(keuze) {
        document.body.dataset.theme = echtThema(keuze);

        const knoppen = document.querySelectorAll('.theme-toggle [data-theme-mode]');
        knoppen.forEach(function (knop) {
            const actief = knop.dataset.themeMode === keuze;
            knop.setAttribute('aria-pressed', actief ? 'true' : 'false');
        });
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

    // de knoppen zitten in de header, die is er pas als de hele pagina geladen is
    document.addEventListener('DOMContentLoaded', function () {
        const knoppen = document.querySelectorAll('.theme-toggle [data-theme-mode]');
        knoppen.forEach(function (knop) {
            knop.addEventListener('click', function () {
                const keuze = knop.dataset.themeMode;
                try {
                    window.localStorage.setItem(OPSLAG_SLEUTEL, keuze);
                } catch (fout) {
                    // geen opslag mogelijk (bijv. privé-modus), het thema wisselt dan alleen voor deze pagina
                }
                pasToe(keuze);
            });
        });
        pasToe(bewaardeKeuze());
    });
})();
