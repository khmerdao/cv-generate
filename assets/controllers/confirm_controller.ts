import { Controller } from '@hotwired/stimulus';

export default class extends Controller<HTMLFormElement> {
    static values = { message: String };
    declare readonly messageValue: string;

    ask(event: Event): void {
        if (!window.confirm(this.messageValue || 'Confirm?')) {
            event.preventDefault();
        }
    }
}
