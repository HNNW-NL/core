document.addEventListener("DOMContentLoaded", () => {
    const searchInput = document.getElementById("search-text");
    const searchButton = document.getElementById("search-button");
    // Perform server-side search by navigating to ?page=1&q=...
    function submitSearch() {
        const q = encodeURIComponent((searchInput.value || '').trim());
        const url = q ? `?page=1&q=${q}` : `?page=1`;
        window.location.href = url;
    }

    // Search when button is clicked
    searchButton.addEventListener("click", submitSearch);

    // Submit on Enter key
    searchInput.addEventListener("keyup", (e) => {
        if (e.key === 'Enter') {
            submitSearch();
        }
    });
});