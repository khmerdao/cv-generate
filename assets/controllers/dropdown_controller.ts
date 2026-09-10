import { Controller } from '@hotwired/stimulus';

export default class extends Controller<HTMLElement> {
    static targets = ['menu'];
    declare readonly menuTarget: HTMLElement;

    private onOutside = (event: MouseEvent): void => {
        if (!this.element.contains(event.target as Node)) {
            this.menuTarget.hidden = true;
        }
    };

    connect(): void {
        document.addEventListener('click', this.onOutside);
    }

    disconnect(): void {
        document.removeEventListener('click', this.onOutside);
    }

    toggle(event: Event): void {
        event.stopPropagation();
        this.menuTarget.hidden = !this.menuTarget.hidden;
    }
}
