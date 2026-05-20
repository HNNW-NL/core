document.addEventListener("DOMContentLoaded", () => {
    const searchInput = document.getElementById("search-text");
    const searchButton = document.getElementById("search-button");
    const projectCards = document.querySelectorAll(".project-card");

    function searchProjects() {
        const searchValue = searchInput.value.toLowerCase().trim();

        projectCards.forEach(card => {
            const projectName = card.textContent.toLowerCase();

            if (projectName.includes(searchValue)) {
                card.style.display = "flex";
            } else {
                card.style.display = "none";
            }
        });
    }

    // Search when button is clicked
    searchButton.addEventListener("click", searchProjects);

    // Live search while typing
    searchInput.addEventListener("keyup", searchProjects);
});