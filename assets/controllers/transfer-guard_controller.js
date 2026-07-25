import { Controller } from '@hotwired/stimulus';

/**
 * Removes the source account option from the destination select so the same
 * account can never be chosen on both sides of a transfer.
 *
 * Usage on the <form> element:
 *   data-controller="transfer-guard"
 *   data-transfer-guard-source-id-value="<id of source <select>>"
 *   data-transfer-guard-dest-id-value="<id of destination <select>>"
 */
export default class extends Controller {
    static values = {
        sourceId: String,
        destId: String,
    };

    connect() {
        this.source = document.getElementById(this.sourceIdValue);
        this.dest   = document.getElementById(this.destIdValue);

        if (!this.source || !this.dest) return;

        // Snapshot all options once so we can restore them on source change.
        this.allOptions = Array.from(this.dest.options).map(o => o.cloneNode(true));

        this.source.addEventListener('change', () => this.filter());
        this.filter();
    }

    filter() {
        const excludedId  = this.source.value;
        const previousDest = this.dest.value;

        // Rebuild destination options, skipping the one matching the source.
        this.dest.innerHTML = '';
        for (const opt of this.allOptions) {
            if (opt.value === '' || opt.value !== excludedId) {
                this.dest.appendChild(opt.cloneNode(true));
            }
        }

        // Restore previous selection only if it is still present.
        if (previousDest !== excludedId) {
            this.dest.value = previousDest;
        }
    }
}
