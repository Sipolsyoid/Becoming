import './bootstrap';

import Alpine from 'alpinejs';
import photoProof from './photo-proof';

window.Alpine = Alpine;

Alpine.data('photoProof', photoProof);

Alpine.data('liveProgress', (stats, url) => ({
    stats, refreshError: false,
    async refresh() {
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) throw new Error('Progress unavailable');
            this.stats = await response.json();
            this.refreshError = false;
        } catch { this.refreshError = true; }
    },
}));

Alpine.start();
