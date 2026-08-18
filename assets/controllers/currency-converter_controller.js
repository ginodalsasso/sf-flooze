import { Controller } from '@hotwired/stimulus';

/** Same shape as the amount accepted by the endpoint. */
const AMOUNT = /^\d{1,12}([.,]\d{1,6})?$/;

/**
 * Converts an amount between two currencies without reloading the page.
 *
 * Usage on the widget root:
 *   data-controller="currency-converter"
 *   data-currency-converter-url-value="<path to app_exchange_rate_convert>"
 * with targets amount, from, to, result, rate.
 */
export default class extends Controller {
    static targets = ['amount', 'from', 'to', 'result', 'rate'];

    static values = {
        url: String,
        debounce: { type: Number, default: 300 },
    };

    connect() {
        this.lastRequest = 0;
        this.convert();
    }

    disconnect() {
        clearTimeout(this.timer);
    }

    /** Typing rate is far above the round-trip time: only the last keystroke is worth a request. */
    schedule() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.convert(), this.debounceValue);
    }

    swap() { // Swap the two currencies and convert again.
        [this.fromTarget.value, this.toTarget.value] = [this.toTarget.value, this.fromTarget.value];
        this.convert();
    }

    async convert() {
        clearTimeout(this.timer);

        const amount = this.amountTarget.value.trim();
        if (!AMOUNT.test(amount)) {
            this.show('—', '');
            return;
        }

        const request = ++this.lastRequest;
        this.element.classList.add('fx-converter--pending');

        const rate = await this.fetchRate(amount);

        // Answers can arrive out of order: only the latest request may write the result.
        if (request !== this.lastRequest) return;
        this.element.classList.remove('fx-converter--pending');

        if (rate === null) {
            this.show('—', 'Taux indisponible');
            return;
        }

        this.show(`${this.format(rate.result, 2)} ${rate.to}`, this.rateLine(rate));
    }

    /** Null on any unusable answer, so the caller has a single degraded case. */
    async fetchRate(amount) {
        const url = new URL(this.urlValue, window.location.origin);
        url.search = new URLSearchParams({ amount, from: this.fromTarget.value, to: this.toTarget.value });

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });

            return response.ok ? await response.json() : null;
        } catch {
            return null;
        }
    }

    show(result, rate) {
        this.resultTarget.textContent = result;
        this.rateTarget.textContent = rate;
    }

    rateLine({ from, to, rate }) {
        return from === to ? 'Même devise' : `1 ${from} = ${this.format(rate, 4)} ${to}`;
    }

    format(value, decimals) {
        return new Intl.NumberFormat('fr-FR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: decimals,
        }).format(value);
    }
}
