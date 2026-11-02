(() => {
	const projectsPage = document.querySelector('#account-centre-container.projects-page');
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
	const emptyMessageText = document.querySelector('#projects-empty-text');
	const emptyResetButton = document.querySelector('#projects-empty-reset');
	const loadingMessage = document.querySelector('#projects-loading');
	const unavailableMessage = document.querySelector('#projects-unavailable');
	const retryButton = document.querySelector('#projects-retry');
	const favoritesCount = document.querySelector('#project-favorites-count');
	const projectsList = document.querySelector('#projects-list');
	const storageKey = 'account-centre.projects.filters';
	const favoritesStorageKey = 'account-centre.projects.favorites';
	let projectCards = [];
	let projectsLoaded = false;
	let favoriteIds = new Set();

	if (!projectsPage || !searchInput || !statusSelect || !sortSelect || !activeOnlyToggle || !favoritesOnlyToggle || !exportButton || !resetButton || !resultCount || !filterStatus || !exportStatus || !emptyMessage || !emptyMessageText || !emptyResetButton || !loadingMessage || !unavailableMessage || !retryButton || !favoritesCount || !projectsList) {
		return;
	}

	const projectsUrl = projectsPage.dataset.projectsUrl;

	const readSavedFilters = () => {
		try {
			const saved = JSON.parse(window.localStorage.getItem(storageKey) || '{}');
			if (typeof saved.query === 'string') searchInput.value = saved.query;
			if (['all', 'active', 'preparation', 'completed', 'other'].includes(saved.status)) statusSelect.value = saved.status;
			if (['name-asc', 'name-desc', 'status', 'favorites-first'].includes(saved.sort)) sortSelect.value = saved.sort;
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

	const getProjectStatus = (project) => {
		const status = project.status && typeof project.status === 'object'
			? project.status.name
			: project.status;
		const label = typeof status === 'string' && status.trim() ? status.trim() : 'Overig';
		const normalizedStatus = normalizeText(label);

		if (['active', 'actief', 'ongoing', 'lopend'].includes(normalizedStatus)) {
			return { value: 'active', label };
		}
		if (['preparation', 'in voorbereiding', 'planned', 'gepland'].includes(normalizedStatus)) {
			return { value: 'preparation', label };
		}
		if (['completed', 'complete', 'afgerond', 'voltooid'].includes(normalizedStatus)) {
			return { value: 'completed', label };
		}

		return { value: 'other', label };
	};

	const createProjectCard = (project) => {
		const projectId = project.id ?? project.slug;
		const title = typeof project.title === 'string' ? project.title.trim() : '';
		if ((typeof projectId !== 'string' && typeof projectId !== 'number') || !title) return null;

		const summary = typeof project.summary === 'string'
			? project.summary
			: typeof project.description === 'string' ? project.description : '';
		const status = getProjectStatus(project);
		const card = document.createElement('article');
		card.className = 'account-card project-card';
		card.dataset.projectId = String(projectId);
		card.dataset.status = status.value;

		const content = document.createElement('div');
		content.className = 'account-card-content';
		const heading = document.createElement('h3');
		heading.textContent = title;
		const description = document.createElement('p');
		description.textContent = summary;
		content.append(heading, description);

		const statusButton = document.createElement('button');
		statusButton.type = 'button';
		statusButton.className = `project-status status-${status.value}`;
		statusButton.dataset.filterStatus = status.value;
		statusButton.title = `Toon alle projecten met status: ${status.label}`;
		statusButton.textContent = status.label;

		const favoriteButton = document.createElement('button');
		favoriteButton.type = 'button';
		favoriteButton.className = 'project-favorite';
		favoriteButton.setAttribute('aria-pressed', 'false');
		favoriteButton.textContent = 'Bewaar';

		card.append(content, statusButton, favoriteButton);
		return card;
	};

	const bindProjectCardEvents = () => {
		projectCards.forEach((card) => {
			const statusButton = card.querySelector('[data-filter-status]');
			statusButton.addEventListener('click', () => {
				statusSelect.value = statusButton.dataset.filterStatus;
				activeOnlyToggle.checked = false;
				favoritesOnlyToggle.checked = false;
				updateProjects();
				statusSelect.focus();
			});

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
	};

	const loadProjects = async () => {
		projectsLoaded = false;
		loadingMessage.hidden = false;
		unavailableMessage.hidden = true;
		emptyMessage.hidden = true;
		projectsList.hidden = true;
		resultCount.textContent = 'Je projecten worden geladen...';

		try {
			const response = await fetch(projectsUrl, {
				method: 'GET',
				credentials: 'same-origin',
				cache: 'no-store',
				headers: { Accept: 'application/json' },
			});
			if (!response.ok) throw new Error(`Projectdata ophalen mislukt (${response.status}).`);

			const payload = await response.json();
			const projects = Array.isArray(payload) ? payload : payload?.projects ?? payload?.items;
			if (!Array.isArray(projects)) throw new Error('Ongeldig antwoord van de projectservice.');

			projectCards = projects
				.filter((project) => project && project.isParticipant === true)
				.map(createProjectCard)
				.filter(Boolean);
			projectsList.replaceChildren(...projectCards);
			projectsLoaded = true;
			loadingMessage.hidden = true;
			projectsList.hidden = projectCards.length === 0;
			bindProjectCardEvents();
			updateProjects();
		} catch {
			loadingMessage.hidden = true;
			unavailableMessage.hidden = false;
			projectsList.hidden = true;
			emptyMessage.hidden = true;
			resultCount.textContent = '';
			exportButton.disabled = true;
			resetButton.disabled = true;
			filterStatus.textContent = '';
		}
	};

	const updateStatusCounts = (query, activeOnly, favoritesOnly) => {
		const availableCards = projectCards.filter((card) => {
			const searchableText = card.querySelector('.account-card-content').textContent;
			return normalizeText(searchableText).includes(query)
				&& (!activeOnly || card.dataset.status === 'active')
				&& (!favoritesOnly || favoriteIds.has(card.dataset.projectId));
		});

		statusSelect.querySelectorAll('option').forEach((option) => {
			const count = option.value === 'all'
				? availableCards.length
				: availableCards.filter((card) => card.dataset.status === option.value).length;
			option.textContent = `${option.dataset.label} (${count})`;
		});
	};

	const updateProjects = () => {
		if (!projectsLoaded) return;

		const query = normalizeText(searchInput.value.trim());
		const selectedStatus = statusSelect.value;
		const activeOnly = activeOnlyToggle.checked;
		const favoritesOnly = favoritesOnlyToggle.checked;
		updateStatusCounts(query, activeOnly, favoritesOnly);
		const statusOrder = { active: 0, preparation: 1, completed: 2, other: 3 };
		const sortedCards = [...projectCards].sort((first, second) => {
			if (sortSelect.value === 'favorites-first') {
				const favoriteDifference = Number(favoriteIds.has(second.dataset.projectId)) - Number(favoriteIds.has(first.dataset.projectId));
				if (favoriteDifference !== 0) return favoriteDifference;
			}

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
		favoritesCount.textContent = String(projectCards.filter((card) => favoriteIds.has(card.dataset.projectId)).length);
		emptyMessageText.textContent = projectCards.length === 0
			? 'Er zijn geen projecten gevonden waaraan je account deelneemt.'
			: favoritesOnly && favoriteIds.size === 0
				? 'Je hebt nog geen favorieten. Bewaar een project met de knop Bewaar.'
				: 'Geen projecten gevonden. Pas je zoekopdracht of filters aan.';
		emptyResetButton.hidden = !filtersActive || projectCards.length === 0;
		filterStatus.textContent = filtersActive
			? `Actieve filters: ${[
				searchInput.value ? 'zoekterm' : '',
				statusSelect.value !== 'all' ? 'status' : '',
				sortSelect.value === 'favorites-first' ? 'favorieten eerst' : sortSelect.value !== 'name-asc' ? 'sortering' : '',
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

	retryButton.addEventListener('click', loadProjects);
	resetButton.addEventListener('click', () => {
		searchInput.value = '';
		statusSelect.value = 'all';
		sortSelect.value = 'name-asc';
		activeOnlyToggle.checked = false;
		favoritesOnlyToggle.checked = false;
		updateProjects();
		searchInput.focus();
	});
	emptyResetButton.addEventListener('click', () => resetButton.click());
	exportButton.addEventListener('click', () => {
		const rows = Array.from(projectsList.querySelectorAll('.project-card'))
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
		window.setTimeout(() => URL.revokeObjectURL(downloadUrl), 1000);
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

	loadProjects();
})();
