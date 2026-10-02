import { Controller } from '@hotwired/stimulus';

/*
 * Sylius' grid header macro renders bare <th>s: the help of each column (the same texts as
 * the edit modal's) is attached here, by the column class Sylius stamps on each one.
 */
export default class extends Controller {
    static targets = ['icon'];

    static values = { help: Object };

    connect() {
        Object.entries(this.helpValue).forEach(([column, help]) => {
            document.querySelectorAll(`th.sylius-table-column-${column}`).forEach((header) => {
                if (header.hasAttribute('title')) {
                    return;
                }

                header.setAttribute('title', help);
                header.append(' ', this.iconTarget.content.cloneNode(true));
            });
        });
    }
}
