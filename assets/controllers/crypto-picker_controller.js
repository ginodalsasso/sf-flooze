import { Controller } from '@hotwired/stimulus';

/**
 * Crypto picker of the asset form: searches the provider's catalogue, fills ticker and name,
 * and shows the market price that will be applied to the initial buy.
 *
 * Nothing here is authoritative. The server re-reads the price on submit and ignores whatever
 * the price field carries, so a picker that stays silent only means the user fills the fields by hand.
 */
export default class extends Controller {
    static targets = [
        'type', 'currency', 'ticker', 'name',
        'search', 'query', 'results', 'status',
        'quote', 'quoteValue', 'quoteHint', 'manualPrice',
    ];

    static values = {
        searchUrl: String,
        priceUrl: String,
        cryptoType: String,
        debounce: { type: Number, default: 300 },
    };

    connect() {
        this.lastSearch = 0;
        this.lastQuote = 0;
        this.toggle();
    }

    disconnect() {
        clearTimeout(this.searchTimer);
        clearTimeout(this.quoteTimer);
    }

    /** Only a crypto has a searchable catalogue and an imposed price. */
    toggle() {
        const isCrypto = this.typeTarget.value === this.cryptoTypeValue;

        this.searchTarget.hidden = !isCrypto;

        if (!isCrypto) {
            this.clearResults();
            this.showManualPrice('');
            return;
        }

        this.refreshQuote();
    }

    // ── Catalogue search ────────────────────────────────────────

    scheduleSearch() {
        clearTimeout(this.searchTimer);
        this.searchTimer = setTimeout(() => this.search(), this.debounceValue);
    }

    async search() {
        clearTimeout(this.searchTimer);

        const query = this.queryTarget.value.trim();
        if (query.length < 2) {
            this.clearResults();
            return;
        }

        const request = ++this.lastSearch; // ++ = increment before assignment, so the first request is 1, not 0.
        this.statusTarget.textContent = 'Recherche…';

        const coins = await this.fetchJson(this.searchUrlValue, { q: query });

        // Answers can arrive out of order: only the latest request may write the list.
        if (request !== this.lastSearch) return;

        if (coins === null) {
            this.clearResults('Recherche indisponible hors ligne. Saisis le ticker et le nom à la main.');
            return;
        }

        this.renderResults(coins.coins ?? []);
    }

    renderResults(coins) {
        this.resultsTarget.replaceChildren();

        if (coins.length === 0) {
            this.clearResults('Aucune crypto trouvée. Saisis le ticker et le nom à la main.');
            return;
        }

        // Built node by node: provider names are third-party text, never markup.
        for (const coin of coins) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'crypto-picker-result';
            button.dataset.action = 'crypto-picker#select';
            button.dataset.cryptoPickerTickerParam = coin.ticker;
            button.dataset.cryptoPickerNameParam = coin.name;

            const ticker = document.createElement('span');
            ticker.className = 'crypto-picker-result-ticker';
            ticker.textContent = coin.ticker;

            const name = document.createElement('span');
            name.className = 'crypto-picker-result-name';
            name.textContent = coin.name;

            button.append(ticker, name);

            const item = document.createElement('li');
            item.append(button);
            this.resultsTarget.append(item);
        }

        this.resultsTarget.hidden = false;
        this.statusTarget.textContent = '';
    }

    select({ params: { ticker, name } }) { // params are set by the button's data attributes
        this.tickerTarget.value = ticker;
        this.nameTarget.value = name;
        this.queryTarget.value = `${ticker} — ${name}`;
        this.clearResults();
        this.refreshQuote();
    }

    clearResults(status = '') {
        this.resultsTarget.replaceChildren();
        this.resultsTarget.hidden = true;
        this.statusTarget.textContent = status;
    }

    // ── Market price ────────────────────────────────────────────

    scheduleQuote() {
        clearTimeout(this.quoteTimer);
        this.quoteTimer = setTimeout(() => this.refreshQuote(), this.debounceValue);
    }

    async refreshQuote() {
        clearTimeout(this.quoteTimer);

        if (!this.hasQuoteTarget || this.typeTarget.value !== this.cryptoTypeValue) return;

        const ticker = this.tickerTarget.value.trim();
        if (ticker === '') {
            this.showManualPrice('Choisis une crypto pour appliquer son cours du marché.');
            return;
        }

        const request = ++this.lastQuote;
        const answer = await this.fetchJson(this.priceUrlValue, { ticker, currency: this.currencyTarget.value });

        if (request !== this.lastQuote) return;

        const price = answer?.price ?? null;
        if (price === null) {
            this.showManualPrice('Cours indisponible pour ce ticker : saisis le prix unitaire toi-même.');
            return;
        }

        this.showQuote(price);
    }

    /** The market price replaces the input: it is the one the server will record. */
    showQuote({ unitPrice, currency, label, asOf }) {
        this.quoteValueTarget.textContent = `${this.format(unitPrice)} ${currency}`;
        this.quoteHintTarget.textContent = `${label} · relevé le ${this.formatDate(asOf)}. Ce cours s'appliquera à l'achat initial.`;
        this.quoteTarget.hidden = false;

        if (this.hasManualPriceTarget) this.manualPriceTarget.hidden = true;
    }

    showManualPrice(hint) {
        if (!this.hasQuoteTarget) return;

        this.quoteTarget.hidden = true;
        if (this.hasManualPriceTarget) this.manualPriceTarget.hidden = false;
        this.statusTarget.textContent = hint;
    }

    // ── Plumbing ────────────────────────────────────────────────

    /** Null on any unusable answer, so callers have a single degraded case. */
    async fetchJson(path, params) {
        const url = new URL(path, window.location.origin);
        url.search = new URLSearchParams(params);

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });

            return response.ok 
                ? await response.json() 
                : console.error(`Failed to fetch ${url}: ${response.status} ${response.statusText}`), null;
        } catch {
            return console.error(`Failed to fetch ${url}`), null;
        }
    }

    format(value) {
        return new Intl.NumberFormat('fr-FR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 4,
        }).format(value);
    }

    formatDate(value) {
        return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value));
    }
}
