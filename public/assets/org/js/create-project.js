document.addEventListener("DOMContentLoaded", function () {

    // Focus automatisch op het naamveld bij het laden van de pagina
    const nameInput = document.getElementById("name");

    if (nameInput) {
        nameInput.focus();
    }

    // Character counter voor projectnaam
    const title = document.getElementById("name");
    const titleCounter = document.getElementById("title-counter");

    if (title && titleCounter) {
        title.addEventListener("input", function () {
            titleCounter.textContent = title.value.length + " characters";
        });
    }

    // Character counter voor summary
    const summary = document.getElementById("summary");
    const summaryCounter = document.getElementById("summary-counter");

    if (summary && summaryCounter) {
        summary.addEventListener("input", function () {
            summaryCounter.textContent = summary.value.length + " characters";
        });
    }

    // Character counter voor description
    const description = document.getElementById("description");
    const descriptionCounter = document.getElementById("description-counter");

    if (description && descriptionCounter) {
        description.addEventListener("input", function () {
            descriptionCounter.textContent = description.value.length + " characters";
        });
    }

    const button = document.getElementById("submit-btn");
    const form = document.querySelector("form");

    if (form && button) {
        form.addEventListener("submit", function (e) {

            e.preventDefault();

            // UI feedback tijdens het aanmaken van het project
            button.textContent = "Creating project...";
            button.disabled = true;
            button.classList.add("loading");

            setTimeout(() => {
                // Reset knop na loading delay (1.5 seconden)
                button.textContent = "Create project";
                button.disabled = false;

                form.submit();
            }, 1500); // 1.5 seconden loading delay
        });
    }

});