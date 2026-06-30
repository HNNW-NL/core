document.addEventListener("DOMContentLoaded", () => {
    const searchInput = document.getElementById("search-text");
    const searchButton = document.getElementById("search-button");
    const resetButton = document.getElementById("reset-button");
    const app = document.getElementById("projects-app");
    const container = app.querySelector(".projects-container");
    const currentPageSpan = document.getElementById("current-page");
    const totalPagesSpan = document.getElementById("total-pages");
    const paginationControls = document.getElementById("pagination-controls");
    const countStartSpan = document.getElementById("projects-count-start");
    const countEndSpan = document.getElementById("projects-count-end");
    const countTotalSpan = document.getElementById("projects-count-total");

    const allProjects = JSON.parse(app.dataset.projects || "[]");
    const searchQuery = app.dataset.search || "";

    const urlParams = new URLSearchParams(window.location.search);
    const perPageSelect = document.getElementById('per-page-select');
    let perPage = parseInt(urlParams.get('per_page')) || parseInt(localStorage.getItem('projects_per_page')) || 8;
    if (perPageSelect) perPageSelect.value = String(perPage);

    let currentPage = parseInt(urlParams.get("page")) || 1;

    if (searchQuery) {
        searchInput.value = searchQuery;
    }

    let totalPages = Math.max(1, Math.ceil(allProjects.length / perPage));

    if (currentPage > totalPages) {
        currentPage = totalPages;
    }

    // Card size handling (small | medium | large)
    const sizeSelect = document.getElementById("card-size-select");
    function applySize(size) {
        app.classList.remove('size-small', 'size-medium', 'size-large');
        const normalized = (size || 'medium').toString();
        app.classList.add('size-' + normalized);
        if (sizeSelect) sizeSelect.value = normalized;
        renderPage();
    }
    const urlSize = urlParams.get('size');
    const savedSize = urlSize || localStorage.getItem('projects_card_size') || 'medium';
    applySize(savedSize);
    if (sizeSelect) {
        sizeSelect.addEventListener('change', (e) => {
            const val = e.target.value || 'medium';
            localStorage.setItem('projects_card_size', val);
            const u = new URL(window.location);
            u.searchParams.set('size', val);
            window.history.replaceState({}, '', u);
            applySize(val);
        });
    }

    // per-page selector handling
    if (perPageSelect) {
        perPageSelect.addEventListener('change', (e) => {
            const val = parseInt(e.target.value) || 8;
            localStorage.setItem('projects_per_page', val);
            perPage = val;
            const u = new URL(window.location);
            u.searchParams.set('per_page', val);
            window.history.replaceState({}, '', u);
            totalPages = Math.max(1, Math.ceil(allProjects.length / perPage));
            if (currentPage > totalPages) currentPage = totalPages;
            renderPage();
        });
    }

    //  highlight search terms
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, function (s) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"})[s];
        });
    }

    function escapeRegExp(s) {
        return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function highlightText(text, query) {
        text = text || '';
        if (!query) return escapeHtml(text);
        const terms = query.trim().split(/\s+/).filter(Boolean).map(escapeRegExp);
        if (terms.length === 0) return escapeHtml(text);
        const pattern = new RegExp('(' + terms.join('|') + ')', 'i');
        const parts = text.split(pattern);
        return parts.map((part, idx) => {
            if (idx % 2 === 1) {
                return '<mark class="search-highlight">' + escapeHtml(part) + '</mark>';
            }
            return escapeHtml(part);
        }).join('');
    }

    // Render current page of projects
    function renderPage() {
        container.innerHTML = "";

        if (allProjects.length === 0) {
            container.innerHTML = "<div>No projects found.</div>";
            countStartSpan.textContent = 0;
            countEndSpan.textContent = 0;
            countTotalSpan.textContent = 0;
        } else {
            const offset = (currentPage - 1) * perPage;
            const pageItems = allProjects.slice(offset, offset + perPage);
            const displayStart = offset + 1;
            const displayEnd = offset + pageItems.length;

            countStartSpan.textContent = displayStart;
            countEndSpan.textContent = displayEnd;
            countTotalSpan.textContent = allProjects.length;

            pageItems.forEach(project => {
                const card = document.createElement("div");
                card.className = "project-card";
                card.dataset.slug = project.slug || '';

                const imageContainer = document.createElement("div");
                imageContainer.className = "project-card-image-container";
                const imageUrl = project.image || project.image_url || project.imageUrl || '';

                if (imageUrl) {
                    const img = document.createElement("img");
                    img.className = "project-card-image";
                    img.src = imageUrl;
                    img.alt = project.title ? `Image for ${project.title}` : 'Project image';
                    imageContainer.appendChild(img);
                } else {
                    const missingImage = document.createElement("div");
                    missingImage.className = "project-card-image-missing";
                    missingImage.textContent = "Geen foto gevonden.";
                    imageContainer.appendChild(missingImage);
                }

                const titleEl = document.createElement("div");
                titleEl.className = "project-title";
                const summaryEl = document.createElement("div");
                summaryEl.className = "project-summary";
                const slugEl = document.createElement("a");
                slugEl.className = "project-slug";
                slugEl.href = "/org/projects/" + encodeURIComponent(project.slug || '');

                const detailsEl = document.createElement("div");
                detailsEl.className = "project-card-details";

                const hq = (searchInput && (searchInput.value || '').trim()) || searchQuery || '';

                titleEl.innerHTML = highlightText(project.title || "Untitled", hq);
                summaryEl.innerHTML = highlightText(project.summary || "", hq);
                slugEl.innerHTML = highlightText(project.slug || "", hq);

                detailsEl.appendChild(titleEl);
                detailsEl.appendChild(summaryEl);
                detailsEl.appendChild(slugEl);

                card.appendChild(imageContainer);
                card.appendChild(detailsEl);

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

    // Reset filters
    if (resetButton) {
        resetButton.addEventListener("click", () => {
            localStorage.removeItem('projects_card_size');
            localStorage.removeItem('projects_per_page');
            const url = new URL(window.location);
            url.searchParams.delete('q');
            url.searchParams.delete('page');
            url.searchParams.delete('size');
            url.searchParams.delete('per_page');
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        });
    }

    // Search on Enter
    searchInput.addEventListener("keyup", (e) => {
        if (e.key === "Enter") {
            submitSearch();
        }
    });

    renderPage();
});
