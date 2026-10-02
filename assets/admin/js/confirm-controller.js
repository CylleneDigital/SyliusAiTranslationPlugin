import { Controller } from '@hotwired/stimulus';

/* Asks before a form is submitted: the message is the server's, already translated. */
export default class extends Controller {
    static values = { message: String };

    confirm(event) {
        if (!window.confirm(this.messageValue)) {
            event.preventDefault();
        }
    }
}
