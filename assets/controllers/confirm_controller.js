import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        message: {
            type: String,
            default: 'Weet je zeker dat je deze actie wilt uitvoeren?'
        }
    };

    submit(event) {
        if (!window.confirm(this.messageValue)) {
            event.preventDefault();
        }
    }
}
