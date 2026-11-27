(() => {
    const profile = document.querySelector('.profile-page #profile');
    if (!profile) return;

    for (const action of profile.querySelectorAll('[aria-disabled="true"]')) {
        action.addEventListener('click', event => event.preventDefault());
    }

    for (const image of profile.querySelectorAll('figure[data-profile-fallback] > img')) {
        const fallback = () => {
            if (!image.isConnected) return;
            const initial = document.createElement('span');
            initial.textContent = image.parentElement.dataset.profileFallback;
            image.replaceWith(initial);
        };
        image.addEventListener('error', fallback, {once: true});
        if (image.complete && image.naturalWidth === 0) fallback();
    }

})();
