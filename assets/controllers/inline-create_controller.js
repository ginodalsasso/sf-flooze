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
        if (!this.frameTarget.contains(event.target)) return;
        if (event.detail.success) this.dialogTarget.close();
    }
}
