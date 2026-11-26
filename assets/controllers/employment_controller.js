import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['current', 'endDate'];

    connect() {
        this.update();
    }

    update() {
        const current = this.currentTarget.checked;

        this.endDateTarget.disabled = current;

        if (current) {
            this.endDateTarget.value = '';
        }
    }
}
