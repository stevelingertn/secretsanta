import Alpine from 'alpinejs';

/**
 * Live tallies: polls /tallies and updates every [data-tally="<entry number>"] element.
 * Plain polling on purpose (no websockets). Failures keep the last known counts and say so.
 */
Alpine.data('tallies', (url, refreshedAt, intervalMs = 30000) => ({
    refreshedAt,
    failed: false,
    loading: false,
    timer: null,

    init() {
        this.timer = setInterval(() => this.refresh(), intervalMs);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') this.refresh();
        });
    },

    destroy() {
        clearInterval(this.timer);
    },

    async refresh() {
        if (this.loading || document.visibilityState === 'hidden') return;
        this.loading = true;
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) throw new Error(response.status);
            const data = await response.json();
            document.querySelectorAll('[data-tally]').forEach((el) => {
                const count = data.counts[el.dataset.tally] ?? 0;
                el.textContent = count;
                const label = el.parentElement?.querySelector('[data-tally-label]');
                if (label) label.textContent = count === 1 ? 'vote' : 'votes';
            });
            this.refreshedAt = data.refreshed_at;
            this.failed = false;
        } catch (e) {
            this.failed = true;
        } finally {
            this.loading = false;
        }
    },
}));

/**
 * Online ballot picker. Limits selection to the remaining allowance and never
 * lets a contestant pick a car they already voted for (the server enforces both too).
 */
Alpine.data('ballotPicker', (remaining, initial = []) => ({
    remaining,
    selected: initial.map(Number),
    query: '',

    toggle(number) {
        number = Number(number);
        if (this.selected.includes(number)) {
            this.selected = this.selected.filter((n) => n !== number);
        } else if (this.selected.length < this.remaining) {
            this.selected.push(number);
        }
    },

    isSelected(number) {
        return this.selected.includes(Number(number));
    },

    get full() {
        return this.selected.length >= this.remaining;
    },

    matches(text) {
        const q = this.query.trim().toLowerCase().replace(/^#/, '');
        return q === '' || text.includes(q);
    },
}));

window.Alpine = Alpine;
Alpine.start();
