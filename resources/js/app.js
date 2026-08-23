import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

import { initThemeToggle } from './layout/theme';
import { initSidebar } from './layout/sidebar';
import { initChatUI } from './chat/ui';

document.addEventListener('DOMContentLoaded', () => {
    initThemeToggle();
    initSidebar();
    
    if (document.getElementById('chatInput')) {
        initChatUI();
    }
});
