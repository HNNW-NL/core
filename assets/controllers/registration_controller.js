import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'fullName',
        'username',
        'email',
        'password',
        'confirmation',
        'terms',
        'meter',
        'strength',
        'submit',
        'errors'
    ];

    connect() {
        this.updateStrength();
        this.validate(false);
    }

    update() {
        this.updateStrength();
        this.validate(true);
    }

    updateStrength() {
        const password = this.passwordTarget.value;

        const score = [
            password.length >= 8,
            /[A-Z]/.test(password),
            /[0-9]/.test(password),
            /[^A-Za-z0-9]/.test(password)
        ].filter(Boolean).length;

        const labels = [
            'Zeer zwak',
            'Zwak',
            'Redelijk',
            'Goed',
            'Sterk'
        ];

        this.meterTarget.value = score;
        this.strengthTarget.dataset.score = score;
        this.strengthTarget.textContent = password ? labels[score] : '';
    }

    validate(showErrors = true) {
        const fields = [
            [
                this.fullNameTarget,
                this.fullNameTarget.value.trim().length > 0
                && this.fullNameTarget.checkValidity(),
                'Vul een geldige naam in.'
            ],
            [
                this.usernameTarget,
                this.usernameTarget.value.trim().length >= 3
                && this.usernameTarget.checkValidity(),
                'Vul een geldige gebruikersnaam in.'
            ],
            [
                this.emailTarget,
                this.emailTarget.checkValidity(),
                'Vul een geldig e-mailadres in.'
            ],
            [
                this.passwordTarget,
                this.passwordTarget.value.length >= 8,
                'Gebruik minimaal 8 tekens.'
            ],
            [
                this.confirmationTarget,
                this.confirmationTarget.value.length >= 8
                && this.confirmationTarget.value === this.passwordTarget.value,
                'Wachtwoorden komen niet overeen.'
            ],
            [
                this.termsTarget,
                this.termsTarget.checked,
                'Accepteer de voorwaarden.'
            ]
        ];

        let valid = true;

        for (const [field, fieldValid, message] of fields) {
            if (!fieldValid) {
                valid = false;
            }

            if (showErrors) {
                this.setFieldError(
                    field,
                    fieldValid ? '' : message
                );
            }
        }

        this.submitTarget.disabled = !valid;
        this.submitTarget.classList.toggle('btn-primary', valid);

        return valid;
    }

    setFieldError(field, message) {
        field.setAttribute('aria-invalid', String(Boolean(message)));

        const error = document.getElementById(`${field.id}-error`);

        if (!error) {
            return;
        }

        error.textContent = message;
        error.classList.toggle('visually-hidden', !message);
    }

    submit(event) {
        if (this.validate(true)) {
            return;
        }

        event.preventDefault();

        this.errorsTarget.textContent = 'Herstel de fouten hieronder.';
        this.errorsTarget.classList.remove('visually-hidden');
        this.errorsTarget.style.display = 'block';
        this.errorsTarget.focus();
    }
}
