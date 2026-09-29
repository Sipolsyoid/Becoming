export default () => ({
    preview: '', error: '', busy: false,
    select(event) {
        if (this.preview) URL.revokeObjectURL(this.preview);
        this.preview = '';
        this.error = '';
        const file = event.target.files[0];
        event.target.setCustomValidity('');
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            this.error = 'Choose a JPG, PNG or WebP image.';
        } else if (file.size > 5 * 1024 * 1024) {
            this.error = 'This photo is too large. Choose one smaller than 5 MB.';
        } else {
            this.preview = URL.createObjectURL(file);
        }
        event.target.setCustomValidity(this.error);
    },
    submit(event) {
        if (this.busy || this.error) { event.preventDefault(); return; }
        this.busy = true;
    },
    destroy() { if (this.preview) URL.revokeObjectURL(this.preview); },
});
