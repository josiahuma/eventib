/* Prices are a preview. Registration calculates and validates the payable amount again. */
(function (root) {
    function picker(cfg) {
        const categories = cfg.categories || [];
        return {
            childAges: (cfg.childAges || []).slice(),
            init() { this.syncChildAges(); this.$watch?.("children", () => this.syncChildAges()); },
            syncChildAges() { this.childAges = Array.from({length:this.clamp(this.children,20)}, (_,i) => this.childAges[i] ?? ""); },
            mode: cfg.mode, categories, sessions: cfg.sessions || [],
            sessionIds: (cfg.sessionIds || []).map(String),
            quantities: Object.fromEntries(categories.map(c => [c.id, Number(cfg.quantities?.[c.id] || 0)])),
            quantity: Number(cfg.quantity || 1), adults: Number(cfg.adults || 0), children: Number(cfg.children || 0),
            clamp(value, max, min = 0) { const n = Number(value); return Number.isFinite(n) ? Math.max(min, Math.min(max, Math.floor(n))) : min; },
            items() { return this.mode === 'cats' ? categories.reduce((n,c) => n + this.clamp(this.quantities[c.id],100),0) : this.mode === 'single' ? this.clamp(this.quantity,10,1) : 1+this.clamp(this.adults,20)+this.clamp(this.children,20); },
            subtotalMinor() { return this.mode === 'cats' ? categories.reduce((n,c) => n + c.priceMinor * this.clamp(this.quantities[c.id],100),0) : this.mode === 'single' ? cfg.unitMinor * this.clamp(this.quantity,10,1) : 0; },
            feeMinor() { return Math.round(this.subtotalMinor() * cfg.feeBps / 10000); },
            totalMinor() { return this.subtotalMinor()+this.feeMinor(); },
            money(minor) { return cfg.symbol + (minor / 100).toFixed(2); },
            canContinue() { return this.sessionIds.length>0 && this.items()>0; },
        };
    }
    root.eventibTicketPicker = picker;
    if (typeof module !== 'undefined' && module.exports) module.exports = picker;
})(typeof window !== 'undefined' ? window : globalThis);
