import './bootstrap';
import Alpine from 'alpinejs';

// Livewire 3/4 bundles and initializes Alpine internally.
// Only start standalone Alpine if Livewire isn't already doing it to prevent double-initialization collision.
if (!window.Alpine) {
    window.Alpine = Alpine;
    // If Livewire isn't loaded on this page, start Alpine manually
    document.addEventListener('DOMContentLoaded', () => {
        if (!window.Livewire && !window.Alpine.__started) {
            Alpine.start();
            window.Alpine.__started = true;
        }
    });
}
