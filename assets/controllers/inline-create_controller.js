import { Controller } from '@hotwired/stimulus';

/**
 * Ouvre une dialog native contenant une turbo-frame de création (Category/Tag),
 * chargée en lazy-loading, et referme la dialog quand la soumission réussit.
 * Les erreurs de validation restent affichées : la frame les réaffiche nativement,
 * la dialog ne se ferme que sur turbo:submit-end { success: true }.
 */
export default class extends Controller {
    static targets = ['dialog', 'frame'];
    static values = { createPath: String };

    open() {
        if (!this.frameTarget.getAttribute('src')) {
            this.frameTarget.setAttribute('src', this.createPathValue);
        }
        this.dialogTarget.showModal();
    }

    close() {
        this.dialogTarget.close();
    }

    submitEnd(event) {
        // closest(), not contains(): a stacked dialog's own frame is nested inside ours,
        // so contains() would also match its submissions and close the wrong dialog.
        if (event.target.closest('turbo-frame') !== this.frameTarget) return;
        if (!event.detail.success) return;

        this.dialogTarget.close();
        this.frameTarget.removeAttribute('src');
        this.frameTarget.replaceChildren();
    }
}
