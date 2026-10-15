(() => {
	const searchInput = document.querySelector('#project-search');
	const statusSelect = document.querySelector('#project-status');
	const sortSelect = document.querySelector('#project-sort');
	const activeOnlyToggle = document.querySelector('#project-active-only');
	const showAllButton = document.querySelector('#projects-show-all');
	const resetButton = document.querySelector('#projects-reset');
	const resultCount = document.querySelector('#projects-result-count');
	const filterStatus = document.querySelector('#projects-filter-status');
	const emptyMessage = document.querySelector('#projects-empty');
	const projectCards = Array.from(document.querySelectorAll('.project-card'));
	const projectsList = document.querySelector('#projects-list');
	const storageKey = 'account-centre.projects.filters';

	if (!searchInput || !statusSelect || !sortSelect || !activeOnlyToggle || !showAllButton || !resetButton || !resultCount || !filterStatus || !emptyMessage || !projectsList) {
		return;
	}

	const readSavedFilters = () => {
		try {
			const saved = JSON.parse(window.localStorage.getItem(storageKey) || '{}');
			if (typeof saved.query === 'string') searchInput.value = saved.query;
			if (['all', 'active', 'preparation', 'completed'].includes(saved.status)) statusSelect.value = saved.status;
			if (['name-asc', 'name-desc', 'status'].includes(saved.sort)) sortSelect.value = saved.sort;
			if (typeof saved.activeOnly === 'boolean') activeOnlyToggle.checked = saved.activeOnly;
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
			}));
		} catch {
			// Filtering remains usable when storage is unavailable.
		}
	};

	const normalizeText = (value) => value
		.normalize('NFD')
		.replace(/[\u0300-\u036f]/g, '')
		.toLocaleLowerCase('nl');

	const updateProjects = () => {
		const query = normalizeText(searchInput.value.trim());
		const selectedStatus = statusSelect.value;
		const activeOnly = activeOnlyToggle.checked;
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
			const matchesQuery = normalizeText(card.textContent).includes(query);
			const matchesStatus = selectedStatus === 'all' || card.dataset.status === selectedStatus;
			const matchesActiveOnly = !activeOnly || card.dataset.status === 'active';
			const isVisible = matchesQuery && matchesStatus && matchesActiveOnly;

			card.hidden = !isVisible;
			visibleCount += Number(isVisible);
		});

		const filtersActive = searchInput.value.length > 0
			|| statusSelect.value !== 'all'
			|| sortSelect.value !== 'name-asc'
			|| activeOnlyToggle.checked;

		resultCount.textContent = `${visibleCount} ${visibleCount === 1 ? 'project' : 'projecten'} gevonden`;
		emptyMessage.hidden = visibleCount !== 0;
		filterStatus.textContent = filtersActive
			? `Actieve filters: ${searchInput.value ? 'zoekterm' : ''}${searchInput.value && (statusSelect.value !== 'all' || sortSelect.value !== 'name-asc' || activeOnlyToggle.checked) ? ', ' : ''}${statusSelect.value !== 'all' ? 'status' : ''}${statusSelect.value !== 'all' && (sortSelect.value !== 'name-asc' || activeOnlyToggle.checked) ? ', ' : ''}${sortSelect.value !== 'name-asc' ? 'sortering' : ''}${sortSelect.value !== 'name-asc' && activeOnlyToggle.checked ? ', ' : ''}${activeOnlyToggle.checked ? 'alleen actief' : ''}`
			: 'Geen filters actief';
		resetButton.disabled = !filtersActive;
		showAllButton.disabled = !filtersActive;
		saveFilters();
	};

	searchInput.addEventListener('input', updateProjects);
	statusSelect.addEventListener('change', updateProjects);
	sortSelect.addEventListener('change', updateProjects);
	activeOnlyToggle.addEventListener('change', updateProjects);
	showAllButton.addEventListener('click', () => {
		searchInput.value = '';
		statusSelect.value = 'all';
		sortSelect.value = 'name-asc';
		activeOnlyToggle.checked = false;
		updateProjects();
		searchInput.focus();
	});
	resetButton.addEventListener('click', () => {
		searchInput.value = '';
		statusSelect.value = 'all';
		sortSelect.value = 'name-asc';
		activeOnlyToggle.checked = false;
		updateProjects();
		searchInput.focus();
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

	readSavedFilters();
	updateProjects();
})();
