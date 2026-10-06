import './bootstrap';

import Alpine from 'alpinejs';
import photoProof from './photo-proof';
import liveProgress from './live-progress';

window.Alpine = Alpine;

Alpine.data('photoProof', photoProof);

Alpine.data('liveProgress', liveProgress);

Alpine.start();
