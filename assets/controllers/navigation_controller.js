import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['toggle', 'menu'];

    connect() { this.setExpanded(false); }

    setExpanded(expanded) {
        this.menuTarget.classList.toggle('active', expanded);
        this.toggleTarget.setAttribute('aria-expanded', String(expanded));
    }

    toggle() { this.setExpanded(!this.menuTarget.classList.contains('active')); }

    escape() {
        if (!this.menuTarget.classList.contains('active')) return;
        this.setExpanded(false);
        this.toggleTarget.focus();
    }

    outside(event) {
        if (!this.element.contains(event.target)) this.setExpanded(false);
    }

    follow(event) {
        if (event.target.closest('a')) this.setExpanded(false);
    }

    resize() {
        if (window.matchMedia('(width > 64rem)').matches) {
            this.setExpanded(false);
        }
    }
}
