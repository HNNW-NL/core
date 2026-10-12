(() => {
	const searchInput = document.querySelector('#project-search');
	const statusSelect = document.querySelector('#project-status');
	const sortSelect = document.querySelector('#project-sort');
	const resetButton = document.querySelector('#projects-reset');
	const resultCount = document.querySelector('#projects-result-count');
	const emptyMessage = document.querySelector('#projects-empty');
	const projectCards = Array.from(document.querySelectorAll('.project-card'));
	const projectsList = document.querySelector('#projects-list');
	const storageKey = 'account-centre.projects.filters';

	if (!searchInput || !statusSelect || !sortSelect || !resetButton || !resultCount || !emptyMessage || !projectsList) {
		return;
	}

	const readSavedFilters = () => {
		try {
			const saved = JSON.parse(window.localStorage.getItem(storageKey) || '{}');
			if (typeof saved.query === 'string') searchInput.value = saved.query;
			if (['all', 'active', 'preparation', 'completed'].includes(saved.status)) statusSelect.value = saved.status;
			if (['name-asc', 'name-desc', 'status'].includes(saved.sort)) sortSelect.value = saved.sort;
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
			}));
		} catch {
			// Filtering remains usable when storage is unavailable.
		}
	};

	const updateProjects = () => {
		const query = searchInput.value.trim().toLocaleLowerCase();
		const selectedStatus = statusSelect.value;
		const statusOrder = { active: 0, preparation: 1, completed: 2 };
		const sortedCards = [...projectCards].sort((first, second) => {
			if (sortSelect.value === 'status') {
				return statusOrder[first.dataset.status] - statusOrder[second.dataset.status];
			}

			const nameOrder = first.querySelector('h3').textContent.localeCompare(second.querySelector('h3').textContent, 'nl');
			return sortSelect.value === 'name-desc' ? -nameOrder : nameOrder;
		});
		let visibleCount = 0;

		sortedCards.forEach((card) => {
			projectsList.append(card);
			const matchesQuery = card.textContent.toLocaleLowerCase().includes(query);
			const matchesStatus = selectedStatus === 'all' || card.dataset.status === selectedStatus;
			const isVisible = matchesQuery && matchesStatus;

			card.hidden = !isVisible;
			visibleCount += Number(isVisible);
		});

		resultCount.textContent = `${visibleCount} ${visibleCount === 1 ? 'project' : 'projecten'} gevonden`;
		emptyMessage.hidden = visibleCount !== 0;
		saveFilters();
	};

	searchInput.addEventListener('input', updateProjects);
	statusSelect.addEventListener('change', updateProjects);
	sortSelect.addEventListener('change', updateProjects);
	resetButton.addEventListener('click', () => {
		searchInput.value = '';
		statusSelect.value = 'all';
		sortSelect.value = 'name-asc';
		updateProjects();
		searchInput.focus();
	});

	document.addEventListener('keydown', (event) => {
		const target = event.target;
		const isTyping = target instanceof HTMLElement
			&& (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));

		if (event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey && !isTyping) {
			event.preventDefault();
			searchInput.focus();
		}
	});

	readSavedFilters();
	updateProjects();
})();
