import './bootstrap';

import Alpine from 'alpinejs';
import photoProof from './photo-proof';

window.Alpine = Alpine;

Alpine.data('photoProof', photoProof);

Alpine.start();
