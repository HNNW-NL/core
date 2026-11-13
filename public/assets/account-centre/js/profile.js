(() => {
    const avatar = document.querySelector('[data-profile-avatar]');
    if (avatar) {
        const hideBrokenAvatar = () => { avatar.hidden = true; };
        avatar.addEventListener('error', hideBrokenAvatar);
        if (avatar.complete && avatar.naturalWidth === 0) hideBrokenAvatar();
    }

    const dialog = document.getElementById('location-popup');
    const form = document.getElementById('profile-location-form');
    const openButton = document.querySelector('[data-location-open]');
    if (!dialog || !form || !openButton) return;

    const input = document.getElementById('new-location');
    const error = document.getElementById('profile-location-error');
    const saveButton = form.querySelector('button[type="submit"]');
    const clearError = () => { error.hidden = true; error.textContent = ''; };

    openButton.addEventListener('click', () => { clearError(); dialog.showModal(); });
    dialog.querySelector('[data-location-close]').addEventListener('click', () => dialog.close());
    input.addEventListener('input', clearError);
    dialog.addEventListener('close', () => openButton.focus());

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const location = input.value.trim();
        if (!location) {
            error.textContent = 'Vul een plaats in.';
            error.hidden = false;
            input.focus();
            return;
        }
        clearError();
        saveButton.disabled = true;
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ location }),
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Je locatie kon niet worden opgeslagen. Probeer het opnieuw.');
            }
            document.getElementById('current-location').textContent = result.location;
            input.value = result.location;
            dialog.close();
        } catch (failure) {
            error.textContent = failure instanceof TypeError || failure instanceof SyntaxError
                ? 'Je locatie kon niet worden opgeslagen. Controleer je verbinding en probeer het opnieuw.'
                : failure.message;
            error.hidden = false;
        } finally {
            saveButton.disabled = false;
        }
    });
})();
