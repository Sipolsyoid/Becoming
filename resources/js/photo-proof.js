export default (initial = null) => ({
    preview: '', error: '', invalidFile: false, busy: false, filename: '', fileSize: '',
    check: initial, timer: null, controller: null, polling: false, stopped: false, connectionNote: '', failures: 0,
    get pending() { return ['queued', 'checking'].includes(this.check?.status); },
    get done() { return this.check?.status === 'approved'; },
    get title() {
        return ({ queued: 'Photo saved. You can keep going.', checking: 'Checking your photo…', approved: 'A small win, recorded.',
            needs_review: 'A clearer photo would help.', rejected: 'Let’s try a different photo.', failed: 'Your photo is safe. The check needs a retry.' })[this.check?.status] || '';
    },
    init() { if (this.pending) this.schedule(); },
    select(event) {
        if (this.preview) URL.revokeObjectURL(this.preview);
        this.preview = ''; this.error = ''; this.invalidFile = false; this.filename = ''; this.fileSize = '';
        const file = event.target.files[0];
        event.target.setCustomValidity('');
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            this.error = 'Choose a JPG, PNG or WebP image.';
        } else if (file.size > 5 * 1024 * 1024) {
            this.error = 'This photo is too large. Choose one smaller than 5 MB.';
        } else {
            this.preview = URL.createObjectURL(file);
            this.filename = file.name || 'Selected photo';
            this.fileSize = file.size < 1024 * 1024 ? Math.max(1, Math.round(file.size / 1024)) + ' KB' : (file.size / 1024 / 1024).toFixed(1) + ' MB';
        }
        event.target.setCustomValidity(this.error);
        this.invalidFile = !!this.error;
    },
    async submit(event) {
        event.preventDefault();
        if (this.busy || this.invalidFile || this.pending) return;
        await this.send(event.target.action, new FormData(event.target));
    },
    async retry() {
        if (this.busy || !this.check?.can_retry) return;
        const data = new FormData();
        data.append('_token', document.querySelector('meta[name="csrf-token"]').content);
        await this.send(this.check.retry_url, data);
    },
    async send(url, body) {
        this.busy = true; this.error = ''; this.connectionNote = '';
        try {
            const response = await fetch(url, { method: 'POST', body, headers: { Accept: 'application/json' } });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                this.error = response.status === 419 || response.status === 401
                    ? 'Your session expired. Sign in again before uploading.'
                    : Object.values(data.errors || {}).flat()[0] || 'The upload could not be saved. Please try again.';
                return;
            }
            this.check = data;
            this.schedule();
        } catch {
            this.error = 'Connection lost. Your photo may have saved — refresh the page to check before uploading again.';
        } finally { this.busy = false; }
    },
    schedule() {
        clearTimeout(this.timer);
        if (!this.stopped && this.pending) this.timer = setTimeout(() => this.poll(), Math.min(3000 * (1 + this.failures), 15000));
    },
    async poll() {
        if (this.stopped || !this.pending || this.polling) return;
        if (document.hidden) { this.schedule(); return; }
        this.polling = true;
        this.controller = new AbortController();
        const timeout = setTimeout(() => this.controller?.abort(), 15000);
        try {
            const response = await fetch(this.check.status_url, { headers: { Accept: 'application/json' }, cache: 'no-store', signal: this.controller.signal });
            if ([401, 419, 404].includes(response.status)) {
                this.connectionNote = response.status === 404 ? 'This check is no longer available. Refresh to see your habits.' : 'Sign in again to see your result. Your saved photo will still be checked.';
                this.stopped = true;
                return;
            }
            if (!response.ok) throw new Error('Check unavailable');
            this.check = await response.json();
            this.connectionNote = ''; this.failures = 0;
            if (!this.pending) this.$dispatch('photo-checked');
        } catch {
            if (!this.stopped) {
                this.failures++;
                this.connectionNote = 'Reconnecting… Your saved photo will keep its place. We’ll check for an update automatically.';
            }
        } finally {
            clearTimeout(timeout); this.polling = false; this.controller = null; this.schedule();
        }
    },
    destroy() {
        this.stopped = true; clearTimeout(this.timer); this.controller?.abort();
        if (this.preview) URL.revokeObjectURL(this.preview);
    },
});
