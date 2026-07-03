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

    // Parse project data from server-rendered JSON
    const allProjects = JSON.parse(app.dataset.projects || "[]");
    const searchQuery = app.dataset.search || "";

    // Initialize state from URL params and localStorage
    const urlParams = new URLSearchParams(window.location.search);
    const perPageSelect = document.getElementById('per-page-select');
    let perPage = parseInt(urlParams.get('per_page')) || parseInt(localStorage.getItem('projects_per_page')) || 8;
    if (perPageSelect) perPageSelect.value = String(perPage);

    let currentPage = parseInt(urlParams.get("page")) || 1;

    // Pre-fill search input if query was provided
    if (searchQuery) {
        searchInput.value = searchQuery;
    }

    // Calculate total pages based on items and per-page count
    let totalPages = Math.max(1, Math.ceil(allProjects.length / perPage));

    // Clamp current page to valid range
    if (currentPage > totalPages) {
        currentPage = totalPages;
    }

    // Card size handling (small | medium | large)
    // Updates CSS class on container to resize cards via media queries
    const sizeSelect = document.getElementById("card-size-select");
    function applySize(size) {
        app.classList.remove('size-small', 'size-medium', 'size-large');
        const normalized = (size || 'medium').toString();
        app.classList.add('size-' + normalized);
        if (sizeSelect) sizeSelect.value = normalized;
        renderPage();
    }
    // Load size from URL or localStorage, apply defaults
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

    // Per-page selector: controls how many items to show per page
    // Re-paginates and re-renders when changed
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

    // Helper: safely escape HTML special characters to prevent XSS
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, function (s) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"})[s];
        });
    }

    // Helper: escape special regex characters for safe pattern matching
    function escapeRegExp(s) {
        return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    // Highlight search terms in text by wrapping matches in <mark> tags
    // Performs case-insensitive search and handles multiple terms
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

    /**
     * Render the current page of projects
     * - Clears and repopulates the container with paginated cards
     * - Updates count display
     * - Regenerates pagination controls
     */
    function renderPage() {
        container.innerHTML = "";

        if (allProjects.length === 0) {
            // No projects to display
            container.innerHTML = "<div>No projects found.</div>";
            countStartSpan.textContent = 0;
            countEndSpan.textContent = 0;
            countTotalSpan.textContent = 0;
        } else {
            // Calculate slice bounds for current page
            const offset = (currentPage - 1) * perPage;
            const pageItems = allProjects.slice(offset, offset + perPage);
            const displayStart = offset + 1;
            const displayEnd = offset + pageItems.length;

            // Update count display
            countStartSpan.textContent = displayStart;
            countEndSpan.textContent = displayEnd;
            countTotalSpan.textContent = allProjects.length;

            // Render each project as a card
            pageItems.forEach(project => {
                const card = document.createElement("div");
                card.className = "project-card";
                card.dataset.slug = project.slug || '';
                card.style.cursor = "pointer";

                // Clicking card navigates to project detail page
                card.addEventListener("click", () => {
                    if (project.slug) {
                        window.location.href = "/org/projects/" + encodeURIComponent(project.slug);
                    }
                });

                // Create and populate image container (or show placeholder)
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

                // Create text content elements
                const titleEl = document.createElement("div");
                titleEl.className = "project-title";
                const summaryEl = document.createElement("div");
                summaryEl.className = "project-summary";
                const slugEl = document.createElement("a");
                slugEl.className = "project-slug";
                slugEl.href = "/org/projects/" + encodeURIComponent(project.slug || '');

                // Stop card click when slug link is clicked
                slugEl.addEventListener("click", (e) => {
                    e.stopPropagation();
                });

                // Wrapper for text content (maintains layout with image background)
                const detailsEl = document.createElement("div");
                detailsEl.className = "project-card-details";

                // Get active search query and apply highlighting
                const hq = (searchInput && (searchInput.value || '').trim()) || searchQuery || '';

                titleEl.innerHTML = highlightText(project.title || "Untitled", hq);
                summaryEl.innerHTML = highlightText(project.summary || "", hq);
                slugEl.innerHTML = highlightText(project.slug || "", hq);

                // Build card structure
                detailsEl.appendChild(titleEl);
                detailsEl.appendChild(summaryEl);
                detailsEl.appendChild(slugEl);

                card.appendChild(imageContainer);
                card.appendChild(detailsEl);

                container.appendChild(card);
            });
        }

        // Update pagination display
        currentPageSpan.textContent = currentPage;
        totalPagesSpan.textContent = totalPages;

        paginationControls.innerHTML = "";

        // Previous button
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

        // Page number buttons
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

    /**
     * Navigate to a specific page
     * Updates URL, re-renders cards, and smoothly scrolls to top
     */
    function goToPage(page) {
        currentPage = page;
        const url = new URL(window.location);
        url.searchParams.set("page", page);
        window.history.pushState({}, "", url);
        renderPage();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    /**
     * Submit search form
     * Saves search query to URL and reloads page with new results
     */
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

    // Search button click handler
    searchButton.addEventListener("click", submitSearch);

    /**
     * Reset all filters and preferences
     * Clears search, page, size, per_page, and localStorage
     * Reloads page with defaults
     */
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

    // Allow Enter key to submit search
    searchInput.addEventListener("keyup", (e) => {
        if (e.key === "Enter") {
            submitSearch();
        }
    });

    // Initial render
    renderPage();
});
