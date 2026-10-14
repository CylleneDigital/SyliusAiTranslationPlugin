import { Controller } from '@hotwired/stimulus';

/*
 * The generation form of the suggestions screen: the cost estimate, fetched for the
 * coordinates as they stand, and the confirmation asked before a run on every catalogue.
 */
export default class extends Controller {
    static targets = ['form', 'catalogue', 'estimateButton', 'estimateResult', 'allTrigger'];

    static values = {
        catalogueCount: Number,
        estimateUrl: String,
        estimateError: String,
    };

    connect() {
        this.estimateRequest = 0;
        this.submitted = false;
        window.addEventListener('pageshow', this.restored);
    }

    disconnect() {
        window.removeEventListener('pageshow', this.restored);
        clearTimeout(this.release);
    }

    // "Back" after a run restores the page as it was left, flag included, without
    // reconnecting the controller: the form would swallow every later click.
    restored = (event) => {
        if (event.persisted) {
            this.submitted = false;
        }
    };

    /*
     * A blank catalogue fans one billed generation out to every catalogue: ask for an
     * explicit confirmation, which names how many are concerned. The modal's own button
     * re-submits the form and is let through - recognised as the submitter: Sylius moves an
     * open modal out of this controller's element, so no action of ours reaches it there.
     */
    guard(event) {
        const confirmed = event.submitter?.hasAttribute('data-generation-confirmed') ?? false;

        // A second click while the page reloads would queue the same billed runs again -
        // checked first, so that the confirmation below cannot be asked for a second time.
        if (this.submitted) {
            event.preventDefault();

            return;
        }

        if (!confirmed && this.catalogueTarget.value === '' && this.catalogueCountValue > 1) {
            event.preventDefault();
            this.allTriggerTarget.click();

            return;
        }

        this.submitted = true;
        // A navigation that never comes (Escape, Stop, a request that fails) leaves the page
        // as it is: without this the button would do nothing until a reload. A long minute,
        // not a few seconds: a slow answer must not let a second click queue the run again.
        this.release = setTimeout(() => {
            this.submitted = false;
        }, 60000);
    }

    /*
     * An estimate is that of the coordinates it was asked for: any of them changing (the
     * provider, a locale, the catalogue, a switch) makes the figures on screen someone else's.
     */
    clearEstimate() {
        this.estimateRequest += 1;
        this.estimateResultTarget.textContent = '';
        this.estimateResultTarget.classList.remove('text-danger');
        this.estimateResultTarget.classList.add('text-secondary');
    }

    async estimate() {
        const field = (name) => this.formTarget.querySelector(`[name=${name}]`);
        const parameters = new URLSearchParams({
            source_locale: field('source_locale').value,
            target_locale: field('target_locale').value,
            catalogue: field('catalogue').value,
            provider: field('provider').value,
            all: field('all').checked ? '1' : '0',
            retry_errors: field('retry_errors').checked ? '1' : '0',
            scope: field('scope').value,
        });

        // An answer only counts for the request it belongs to: a coordinate changed while it
        // was on its way, or a second click, makes it stale.
        const request = (this.estimateRequest += 1);

        this.estimateButtonTarget.disabled = true;
        this.estimateResultTarget.textContent = '…';

        try {
            const response = await fetch(`${this.estimateUrlValue}?${parameters}`);
            const data = await response.json();

            if (request !== this.estimateRequest) {
                return;
            }

            this.estimateResultTarget.textContent = data.message;
            this.estimateResultTarget.classList.toggle('text-danger', !response.ok);
            this.estimateResultTarget.classList.toggle('text-secondary', response.ok);
        } catch {
            if (request !== this.estimateRequest) {
                return;
            }

            this.estimateResultTarget.textContent = this.estimateErrorValue;
            this.estimateResultTarget.classList.add('text-danger');
        } finally {
            this.estimateButtonTarget.disabled = false;
        }
    }
}
