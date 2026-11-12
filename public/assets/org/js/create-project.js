document.addEventListener("DOMContentLoaded", function () {

    // Formulier en opslaan knop
    const form = document.querySelector(".project-form");
    const button = document.getElementById("submit-btn");

    // Projectnaam
    const title = document.getElementById("name");
    const titleCounter = document.getElementById("title-counter");

    // Samenvatting
    const summary = document.getElementById("summary");
    const summaryCounter = document.getElementById("summary-counter");

    // Beschrijving
    const description = document.getElementById("description");
    const descriptionCounter = document.getElementById("description-counter");

    // Projectleider
    const projectLeader = document.getElementById("project_leader");

    // Datums
    const startDate = document.getElementById("start_date");
    const endDate = document.getElementById("end_date");


    // Focus automatisch op het naamveld
    if (title) {
        title.focus();
    }


    // Character counter functie
    function updateCounter(input, counter) {

        if (input && counter) {

            counter.textContent = input.value.length + " characters";

            input.addEventListener("input", function () {
                counter.textContent = input.value.length + " characters";
            });

        }

    }


    // Character counters instellen
    updateCounter(title, titleCounter);
    updateCounter(summary, summaryCounter);
    updateCounter(description, descriptionCounter);


    // Einddatum mag niet voor de startdatum liggen
    function validateDates() {

        if (!startDate || !endDate) {
            return;
        }

        // Pas de minimale einddatum aan
        endDate.min = startDate.value;

        // Controleer de gekozen datums
        if (
            startDate.value &&
            endDate.value &&
            endDate.value < startDate.value
        ) {

            endDate.setCustomValidity(
                "De einddatum mag niet voor de startdatum liggen."
            );

        } else {

            endDate.setCustomValidity("");

        }

    }


    // Datumcontrole uitvoeren bij wijzigingen
    if (startDate && endDate) {

        startDate.addEventListener("change", validateDates);

        endDate.addEventListener("change", validateDates);

        validateDates();

    }


    // Formulier versturen
    if (form && button) {

        form.addEventListener("submit", function () {

            // Extra controle op projectleider
            if (projectLeader && !projectLeader.value) {
                return;
            }

            // Controleer datums
            validateDates();

            // Loading feedback
            button.textContent = "Project wordt opgeslagen...";

            button.disabled = true;

            button.classList.add("loading");

            // Formulier wordt normaal naar de backend verstuurd.
            // De browser voert de standaard formulierverzending uit.

        });

    }

});
