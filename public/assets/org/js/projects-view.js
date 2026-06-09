document.addEventListener("DOMContentLoaded", () => {
    const searchInput = document.getElementById("search-text");
    const searchButton = document.getElementById("search-button");
    const app = document.getElementById("projects-app");
    const container = app.querySelector(".projects-container");
    const currentPageSpan = document.getElementById("current-page");
    const totalPagesSpan = document.getElementById("total-pages");
    const paginationControls = document.getElementById("pagination-controls");

    const allProjects = JSON.parse(app.dataset.projects || "[]");
    const searchQuery = app.dataset.search || "";
    const perPage = 8;

    const urlParams = new URLSearchParams(window.location.search);
    let currentPage = parseInt(urlParams.get("page")) || 1;

    if (searchQuery) {
        searchInput.value = searchQuery;
    }

    const totalPages = Math.max(1, Math.ceil(allProjects.length / perPage));

    if (currentPage > totalPages) {
        currentPage = totalPages;
    }

    // Render current page of projects
    function renderPage() {
        container.innerHTML = "";

        if (allProjects.length === 0) {
            container.innerHTML = "<div>No projects found.</div>";
        } else {
            const offset = (currentPage - 1) * perPage;
            const pageItems = allProjects.slice(offset, offset + perPage);

            pageItems.forEach(project => {
                const card = document.createElement("div");
                card.className = "project-card";
                card.dataset.slug = project.slug;
                card.textContent = project.title || "Untitled";
                container.appendChild(card);
            });
        }

        currentPageSpan.textContent = currentPage;
        totalPagesSpan.textContent = totalPages;

        paginationControls.innerHTML = "";

        // Prev button
        if (currentPage > 1) {
            const prevLink = document.createElement("a");
            prevLink.href = "#";
            prevLink.textContent = "Prev";
            prevLink.addEventListener("click", (e) => {
                e.preventDefault();
                goToPage(currentPage - 1);
            });
            paginationControls.appendChild(prevLink);
        } else {
            const span = document.createElement("span");
            span.className = "disabled";
            span.textContent = "Prev";
            paginationControls.appendChild(span);
        }

        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (i === currentPage) {
                const strong = document.createElement("strong");
                strong.textContent = i;
                paginationControls.appendChild(strong);
            } else {
                const link = document.createElement("a");
                link.href = "#";
                link.textContent = i;
                link.addEventListener("click", (e) => {
                    e.preventDefault();
                    goToPage(i);
                });
                paginationControls.appendChild(link);
            }
        }

        // Next button
        if (currentPage < totalPages) {
            const nextLink = document.createElement("a");
            nextLink.href = "#";
            nextLink.textContent = "Next";
            nextLink.addEventListener("click", (e) => {
                e.preventDefault();
                goToPage(currentPage + 1);
            });
            paginationControls.appendChild(nextLink);
        } else {
            const span = document.createElement("span");
            span.className = "disabled";
            span.textContent = "Next";
            paginationControls.appendChild(span);
        }
    }

    // Navigate to page (updates URL and re-renders)
    function goToPage(page) {
        currentPage = page;
        const url = new URL(window.location);
        url.searchParams.set("page", page);
        window.history.pushState({}, "", url);
        renderPage();
    }

    // Submit search
    function submitSearch() {
        const q = (searchInput.value || "").trim();
        const url = new URL(window.location);
        if (q) {
            url.searchParams.set("q", q);
        } else {
            url.searchParams.delete("q");
        }
        url.searchParams.set("page", "1");
        window.location.href = url;
    }

    // Search button click
    searchButton.addEventListener("click", submitSearch);

    // Search on Enter
    searchInput.addEventListener("keyup", (e) => {
        if (e.key === "Enter") {
            submitSearch();
        }
    });

    renderPage();
});
