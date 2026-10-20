(() => {
	const searchInput = document.querySelector('#project-search');
	const statusSelect = document.querySelector('#project-status');
	const sortSelect = document.querySelector('#project-sort');
	const activeOnlyToggle = document.querySelector('#project-active-only');
	const favoritesOnlyToggle = document.querySelector('#project-favorites-only');
	const exportButton = document.querySelector('#projects-export');
	const resetButton = document.querySelector('#projects-reset');
	const resultCount = document.querySelector('#projects-result-count');
	const filterStatus = document.querySelector('#projects-filter-status');
	const exportStatus = document.querySelector('#projects-export-status');
	const emptyMessage = document.querySelector('#projects-empty');
	const projectCards = Array.from(document.querySelectorAll('.project-card'));
	const projectsList = document.querySelector('#projects-list');
	const storageKey = 'account-centre.projects.filters';
	const favoritesStorageKey = 'account-centre.projects.favorites';
	let favoriteIds = new Set();

	if (!searchInput || !statusSelect || !sortSelect || !activeOnlyToggle || !favoritesOnlyToggle || !exportButton || !resetButton || !resultCount || !filterStatus || !exportStatus || !emptyMessage || !projectsList) {
		return;
	}

	const readSavedFilters = () => {
		try {
			const saved = JSON.parse(window.localStorage.getItem(storageKey) || '{}');
			if (typeof saved.query === 'string') searchInput.value = saved.query;
			if (['all', 'active', 'preparation', 'completed'].includes(saved.status)) statusSelect.value = saved.status;
			if (['name-asc', 'name-desc', 'status'].includes(saved.sort)) sortSelect.value = saved.sort;
			if (typeof saved.activeOnly === 'boolean') activeOnlyToggle.checked = saved.activeOnly;
			if (typeof saved.favoritesOnly === 'boolean') favoritesOnlyToggle.checked = saved.favoritesOnly;
		} catch {
			// Storage can be unavailable in private browsing or restricted contexts.
		}
	};

	const saveFilters = () => {
		try {
			window.localStorage.setItem(storageKey, JSON.stringify({
				query: searchInput.value,
				status: statusSelect.value,
				sort: sortSelect.value,
				activeOnly: activeOnlyToggle.checked,
				favoritesOnly: favoritesOnlyToggle.checked,
			}));
		} catch {
			// Filtering remains usable when storage is unavailable.
		}
	};

	const readSavedFavorites = () => {
		try {
			const saved = JSON.parse(window.localStorage.getItem(favoritesStorageKey) || '[]');
			if (Array.isArray(saved)) favoriteIds = new Set(saved.filter((id) => typeof id === 'string'));
		} catch {
			// Favorites remain usable for the current page when storage is unavailable.
		}
	};

	const saveFavorites = () => {
		try {
			window.localStorage.setItem(favoritesStorageKey, JSON.stringify([...favoriteIds]));
		} catch {
			// Favorites remain usable for the current page when storage is unavailable.
		}
	};

	const updateFavoriteButton = (card) => {
		const button = card.querySelector('.project-favorite');
		const isFavorite = favoriteIds.has(card.dataset.projectId);
		button.setAttribute('aria-pressed', String(isFavorite));
		button.textContent = isFavorite ? 'Bewaard' : 'Bewaar';
		button.setAttribute('aria-label', `${isFavorite ? 'Verwijder' : 'Bewaar'} ${card.querySelector('h3').textContent} ${isFavorite ? 'uit' : 'als'} favoriet`);
		card.classList.toggle('is-favorite', isFavorite);
	};

	const normalizeText = (value) => value
		.normalize('NFD')
		.replace(/[\u0300-\u036f]/g, '')
		.toLocaleLowerCase('nl');

	const updateProjects = () => {
		const query = normalizeText(searchInput.value.trim());
		const selectedStatus = statusSelect.value;
		const activeOnly = activeOnlyToggle.checked;
		const favoritesOnly = favoritesOnlyToggle.checked;
		const statusOrder = { active: 0, preparation: 1, completed: 2 };
		const sortedCards = [...projectCards].sort((first, second) => {
			if (sortSelect.value === 'status') {
				const statusDifference = statusOrder[first.dataset.status] - statusOrder[second.dataset.status];
				if (statusDifference !== 0) return statusDifference;
			}

			const nameOrder = first.querySelector('h3').textContent.localeCompare(second.querySelector('h3').textContent, 'nl');
			return sortSelect.value === 'name-desc' ? -nameOrder : nameOrder;
		});
		let visibleCount = 0;

		sortedCards.forEach((card) => {
			projectsList.append(card);
			const searchableText = card.querySelector('.account-card-content').textContent;
			const matchesQuery = normalizeText(searchableText).includes(query);
			const matchesStatus = selectedStatus === 'all' || card.dataset.status === selectedStatus;
			const matchesActiveOnly = !activeOnly || card.dataset.status === 'active';
			const matchesFavorite = !favoritesOnly || favoriteIds.has(card.dataset.projectId);
			const isVisible = matchesQuery && matchesStatus && matchesActiveOnly && matchesFavorite;

			card.hidden = !isVisible;
			visibleCount += Number(isVisible);
		});

		const filtersActive = searchInput.value.length > 0
			|| statusSelect.value !== 'all'
			|| sortSelect.value !== 'name-asc'
			|| activeOnlyToggle.checked
			|| favoritesOnlyToggle.checked;

		resultCount.textContent = `${visibleCount} van ${projectCards.length} projecten gevonden`;
		emptyMessage.hidden = visibleCount !== 0;
		exportButton.disabled = visibleCount === 0;
		emptyMessage.textContent = favoritesOnly && favoriteIds.size === 0
			? 'Je hebt nog geen favorieten. Bewaar een project met de knop Bewaar.'
			: 'Geen projecten gevonden. Pas je zoekopdracht of filters aan.';
		filterStatus.textContent = filtersActive
			? `Actieve filters: ${[
				searchInput.value ? 'zoekterm' : '',
				statusSelect.value !== 'all' ? 'status' : '',
				sortSelect.value !== 'name-asc' ? 'sortering' : '',
				activeOnlyToggle.checked ? 'alleen actief' : '',
				favoritesOnlyToggle.checked ? 'alleen favorieten' : '',
			].filter(Boolean).join(', ')}`
			: 'Geen filters actief';
		resetButton.disabled = !filtersActive;
		saveFilters();
	};

	searchInput.addEventListener('input', updateProjects);
	statusSelect.addEventListener('change', updateProjects);
	sortSelect.addEventListener('change', updateProjects);
	activeOnlyToggle.addEventListener('change', updateProjects);
	favoritesOnlyToggle.addEventListener('change', updateProjects);
	readSavedFilters();
	readSavedFavorites();

	projectCards.forEach((card) => {
		const button = card.querySelector('.project-favorite');
		updateFavoriteButton(card);
		button.addEventListener('click', () => {
			const projectId = card.dataset.projectId;
			if (favoriteIds.has(projectId)) {
				favoriteIds.delete(projectId);
			} else {
				favoriteIds.add(projectId);
			}
			updateFavoriteButton(card);
			saveFavorites();
			updateProjects();
		});
	});
	resetButton.addEventListener('click', () => {
		searchInput.value = '';
		statusSelect.value = 'all';
		sortSelect.value = 'name-asc';
		activeOnlyToggle.checked = false;
		favoritesOnlyToggle.checked = false;
		updateProjects();
		searchInput.focus();
	});
	exportButton.addEventListener('click', () => {
		const rows = projectCards
			.filter((card) => !card.hidden)
			.map((card) => [
				card.querySelector('h3').textContent.trim(),
				card.querySelector('.account-card-content p').textContent.trim(),
				card.querySelector('.project-status').textContent.trim(),
			]);
		const escapeCsv = (value) => `"${value.replaceAll('"', '""')}"`;
		const csv = ['Project,Omschrijving,Status', ...rows.map((row) => row.map(escapeCsv).join(','))].join('\r\n');
		const blob = new Blob(['\uFEFF', csv], { type: 'text/csv;charset=utf-8' });
		const downloadUrl = URL.createObjectURL(blob);
		const downloadLink = document.createElement('a');
		downloadLink.href = downloadUrl;
		downloadLink.download = `mijn-projecten-${new Date().toISOString().slice(0, 10)}.csv`;
		downloadLink.click();
		URL.revokeObjectURL(downloadUrl);
		exportStatus.textContent = `${rows.length} ${rows.length === 1 ? 'project' : 'projecten'} gedownload.`;
	});

	document.addEventListener('keydown', (event) => {
		const target = event.target;
		const isTyping = target instanceof HTMLElement
			&& (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));

		if (event.key === 'Escape' && searchInput.value.length > 0) {
			searchInput.value = '';
			updateProjects();
			searchInput.focus();
			return;
		}

		if (event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey && !isTyping) {
			event.preventDefault();
			searchInput.focus();
		}
	});

	updateProjects();
})();
