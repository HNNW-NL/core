(() => {
	const searchInput = document.querySelector('#project-search');
	const statusSelect = document.querySelector('#project-status');
	const resetButton = document.querySelector('#projects-reset');
	const resultCount = document.querySelector('#projects-result-count');
	const emptyMessage = document.querySelector('#projects-empty');
	const projectCards = Array.from(document.querySelectorAll('.project-card'));

	if (!searchInput || !statusSelect || !resetButton || !resultCount || !emptyMessage) {
		return;
	}

	const updateProjects = () => {
		const query = searchInput.value.trim().toLocaleLowerCase();
		const selectedStatus = statusSelect.value;
		let visibleCount = 0;

		projectCards.forEach((card) => {
			const matchesQuery = card.textContent.toLocaleLowerCase().includes(query);
			const matchesStatus = selectedStatus === 'all' || card.dataset.status === selectedStatus;
			const isVisible = matchesQuery && matchesStatus;

			card.hidden = !isVisible;
			visibleCount += Number(isVisible);
		});

		resultCount.textContent = `${visibleCount} ${visibleCount === 1 ? 'project' : 'projecten'} gevonden`;
		emptyMessage.hidden = visibleCount !== 0;
	};

	searchInput.addEventListener('input', updateProjects);
	statusSelect.addEventListener('change', updateProjects);
	resetButton.addEventListener('click', () => {
		searchInput.value = '';
		statusSelect.value = 'all';
		updateProjects();
		searchInput.focus();
	});

	updateProjects();
})();
