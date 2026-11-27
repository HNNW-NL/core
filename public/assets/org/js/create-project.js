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

});
