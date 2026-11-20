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

    for (const id of ['e-list', 'edu-list', 'l-list', 's-list']) {
        const list = profile.querySelector(`#${id}`);
        const items = list ? Array.from(list.children).filter(item => item.tagName === 'ARTICLE') : [];
        if (items.length <= 5) continue;

        const existing = profile.querySelector(`[data-profile-toggle="${id}"]`);
        const button = existing || document.createElement('button');
        if (!existing) button.type = 'button';
        const footerClass = {'e-list': 'e-footer', 'edu-list': 'edu-footer', 'l-list': 'l-footer', 's-list': 's-footer'}[id];
        button.className = `profile-show-all ${footerClass}`;
        button.setAttribute('aria-controls', id);
        const update = expanded => {
            items.forEach((item, index) => { item.hidden = !expanded && index >= 5; });
            button.setAttribute('aria-expanded', String(expanded));
            (button.querySelector('p') || button).textContent = expanded ? 'Minder weergeven' : `Alle ${items.length} weergeven`;
        };
        button.addEventListener('click', event => {
            event.preventDefault();
            update(button.getAttribute('aria-expanded') !== 'true');
        });
        if (!existing) list.after(button);
        update(false);
    }
})();
