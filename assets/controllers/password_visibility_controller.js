import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'button'];

    connect() {
        this.update();
    }

    toggle() {
        this.inputTarget.type =
            this.inputTarget.type === 'password' ? 'text' : 'password';

        this.update();
    }

    update() {
        const visible = this.inputTarget.type === 'text';

        this.buttonTarget.textContent = visible ? 'Verberg' : 'Toon';
        this.buttonTarget.setAttribute('aria-pressed', String(visible));
    }
}
