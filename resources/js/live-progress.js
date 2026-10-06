export default (stats, url) => ({
    stats, refreshError: false, timer: null, refreshing: false, destroyed: false,
    init() { this.schedule(); },
    schedule() {
        clearTimeout(this.timer);
        if (this.destroyed) return;
        // Use the server's remaining day length, including daylight saving changes.
        const delay = this.refreshError ? 60000 : Math.max(1000, this.stats.dayEndsInMs + 500);
        this.timer = setTimeout(() => this.refresh(), delay);
    },
    async refresh() {
        if (this.destroyed || this.refreshing) return;
        this.refreshing = true;
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) throw new Error('Progress unavailable');
            const next = await response.json();
            if (next.date !== this.stats.date || next.timezone !== this.stats.timezone) {
                // Due habits and photo forms are rendered by the server for each local date.
                window.location.reload();
                return;
            }
            this.stats = next;
            this.refreshError = false;
        } catch { this.refreshError = true; }
        finally { this.refreshing = false; this.schedule(); }
    },
    resume() { if (!document.hidden) this.refresh(); },
    destroy() { this.destroyed = true; clearTimeout(this.timer); },
});
