import { Controller } from '@hotwired/stimulus';

/*
 * The review modal of the suggestions screen: one for the page, filled from the
 * data-suggestion-* attributes of whatever opened it. Approve posts the form to the
 * suggestion's approve route with the value as edited, reject to its reject route.
 */
export default class extends Controller {
    static targets = [
        'form', 'reject', 'approve',
        'key', 'catalogue',
        'source', 'sourceLocale',
        'currentBlock', 'current', 'currentOrigin',
        'value', 'locale',
        'same',
        'inFlight', 'error', 'errorMessage',
        'mismatch', 'mismatchList',
        'plural', 'pluralList',
        'syntax', 'syntaxList',
    ];

    connect() {
        this.currentValue = null;
        this.fill = this.fill.bind(this);
        this.element.addEventListener('show.bs.modal', this.fill);
    }

    disconnect() {
        this.element.removeEventListener('show.bs.modal', this.fill);
    }

    fill(event) {
        const suggestion = event.relatedTarget?.dataset;
        if (!suggestion) {
            return;
        }

        this.formTarget.action = suggestion.approveUrl;
        this.rejectTarget.setAttribute('formaction', suggestion.rejectUrl);

        this.keyTarget.textContent = suggestion.suggestionKey;
        this.catalogueTarget.textContent = suggestion.suggestionCatalogue;
        this.sourceTarget.value = suggestion.suggestionSource;
        this.sourceLocaleTarget.textContent = suggestion.suggestionSourceLocale;
        this.valueTarget.value = suggestion.suggestionValue;
        this.localeTarget.textContent = suggestion.suggestionLocale;

        this.currentValue = suggestion.suggestionHasCurrent === '1' ? suggestion.suggestionCurrent : null;
        this.currentBlockTarget.classList.toggle('d-none', this.currentValue === null);
        this.currentTarget.value = this.currentValue ?? '';
        this.currentOriginTarget.textContent = suggestion.suggestionCurrentOrigin;

        // A reserved row carries the engine's "interrupted" error while its run still waits.
        // The target came after the template: a host's override of it may not have it.
        const inFlight = suggestion.suggestionInFlight === '1';
        if (this.hasInFlightTarget) {
            this.inFlightTarget.classList.toggle('d-none', !inFlight);
        }
        this.show(this.errorTarget, this.errorMessageTarget, inFlight ? '' : suggestion.suggestionError);
        this.show(this.mismatchTarget, this.mismatchListTarget, suggestion.suggestionMismatch);
        this.show(this.pluralTarget, this.pluralListTarget, suggestion.suggestionPlural);
        this.show(this.syntaxTarget, this.syntaxListTarget, suggestion.suggestionSyntax);

        this.refresh();
    }

    /*
     * Nothing to apply while the value is empty (errored rows arrive without one): the
     * server refuses it anyway, the disabled button says it up front. A value equal to the
     * current one is approved without writing an override: said as typed.
     */
    refresh() {
        this.approveTarget.disabled = this.valueTarget.value.trim() === '';
        this.sameTarget.classList.toggle('d-none', this.currentValue === null || this.valueTarget.value !== this.currentValue);
    }

    show(box, text, content) {
        box.classList.toggle('d-none', !content);
        text.textContent = content || '';
    }
}
