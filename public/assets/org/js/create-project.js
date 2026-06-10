document.addEventListener("DOMContentLoaded", function () {

    const title = document.getElementById("name");
    const titleCounter = document.getElementById("title-counter");

    if (title && titleCounter) {
        title.addEventListener("input", function () {
            titleCounter.textContent = title.value.length + " characters";
        });
    }
    
    const summary = document.getElementById("summary");
    const summaryCounter = document.getElementById("summary-counter");

    if (summary && summaryCounter) {
        summary.addEventListener("input", function () {
            summaryCounter.textContent = summary.value.length + " characters";
        });
    }

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

            button.textContent = "Creating...";
            button.disabled = true;
            button.classList.add("loading");

            setTimeout(() => {
                button.textContent = "Create project";
                button.disabled = false;

                form.submit();
            }, 1500);

        });
    }

});