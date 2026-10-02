import { Controller } from '@hotwired/stimulus';

/*
 * The page-level channel selector. Choosing a channel reloads the page on it; and since
 * Sylius' grid filter form (stock template, untouched) submits to the bare route, the
 * page's channel is carried through that form and through its reset link.
 */
export default class extends Controller {
    static values = { scope: String };

    connect() {
        if (this.scopeValue === '') {
            return;
        }

        document.querySelectorAll('.sylius-filters').forEach((filters) => {
            const form = filters.closest('form');
            if (!form || form.querySelector('input[name="scope"]')) {
                return;
            }

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'scope';
            input.value = this.scopeValue;
            form.appendChild(input);

            form.querySelectorAll('a.btn[href]').forEach((link) => {
                const url = new URL(link.getAttribute('href'), window.location.origin);
                url.searchParams.set('scope', this.scopeValue);
                link.setAttribute('href', url.pathname + url.search);
            });
        });
    }

    submit(event) {
        event.target.form.submit();
    }
}
