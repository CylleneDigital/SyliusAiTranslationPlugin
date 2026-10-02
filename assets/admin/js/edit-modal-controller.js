import { Controller } from '@hotwired/stimulus';

/*
 * The edit modal of the catalogue screen: one for the page, filled from the data-row-*
 * attributes of whatever opened it (a grid cell, the row button). Bootstrap hands that
 * trigger over as the `relatedTarget` of `show.bs.modal`.
 */
export default class extends Controller {
    static targets = [
        'keyInput', 'catalogueInput', 'localeInput', 'scopeInput',
        'key', 'catalogue', 'localeBadge',
        'referenceBlock', 'reference', 'referenceLocale',
        'original', 'originalLocale',
        'value', 'valueLocale',
        'reset',
    ];

    connect() {
        this.fill = this.fill.bind(this);
        this.element.addEventListener('show.bs.modal', this.fill);
    }

    disconnect() {
        this.element.removeEventListener('show.bs.modal', this.fill);
    }

    fill(event) {
        const row = event.relatedTarget?.dataset;
        if (!row) {
            return;
        }

        this.keyInputTarget.value = row.rowKey;
        this.catalogueInputTarget.value = row.rowCatalogue;
        this.localeInputTarget.value = row.rowLocale;
        this.scopeInputTarget.value = row.rowScope;

        this.keyTarget.textContent = row.rowKey;
        this.catalogueTarget.textContent = row.rowCatalogue;
        this.localeBadgeTarget.textContent = row.rowLocale + (row.rowScope ? ' · ' + row.rowScope : '');

        this.referenceTarget.value = row.rowReference;
        this.referenceLocaleTarget.textContent = row.rowReferenceLocale;
        this.referenceBlockTarget.classList.toggle('d-none', row.rowReference === '');

        this.originalTarget.value = row.rowOriginal;
        this.originalLocaleTarget.textContent = row.rowLocale;
        this.valueTarget.value = row.rowCurrent;
        this.valueLocaleTarget.textContent = row.rowLocale;

        // Removing only makes sense when an override exists.
        this.resetTarget.classList.toggle('d-none', row.rowHasOverride !== '1');
    }
}
