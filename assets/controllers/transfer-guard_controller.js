import { Controller } from '@hotwired/stimulus';

/**
 * Drives the transfer-only part of the transaction form:
 *  - shows the destination fields only when the selected type is a transfer
 *  - removes the source account option from the destination select, so the same
 *    account can never be chosen on both sides of a transfer
 *
 * Display only: TransactionService discards a destination posted with any other
 * type, so nothing here is what actually keeps the data clean.
 *
 * Usage on the <form> element:
 *   data-controller="transfer-guard"
 *   data-transfer-guard-source-id-value="<id of source <select>>"
 *   data-transfer-guard-dest-id-value="<id of destination <select>>"
 *   data-transfer-guard-type-id-value="<id of type <select>>"
 * and data-transfer-guard-target="destination" on the block to reveal for transfers.
 */
export default class extends Controller {
    static values = {
        sourceId: String,
        destId: String,
        typeId: String,
    };

    static targets = ['destination'];

    connect() {
        this.source = document.getElementById(this.sourceIdValue);
        this.dest   = document.getElementById(this.destIdValue);
        this.type   = document.getElementById(this.typeIdValue);

        if (this.type) {
            this.type.addEventListener('change', () => this.toggleDestination());
            this.toggleDestination();
        }

        if (!this.source || !this.dest) return;

        // Snapshot all options once so we can restore them on source change.
        this.allOptions = Array.from(this.dest.options).map(o => o.cloneNode(true));

        this.source.addEventListener('change', () => this.filter());
        this.filter();
    }

    /** Selected values are kept while hidden: switching back restores them, and the server ignores them meanwhile. */
    toggleDestination() {
        const isTransfer = this.type.value === 'transfer';

        for (const target of this.destinationTargets) {
            target.hidden = !isTransfer;
        }
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
