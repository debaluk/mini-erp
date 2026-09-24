import { initAjax } from './core/ajax.js';
import { initNotification } from './core/notification.js';

const modules = {};

async function initModules() {
    const elements = document.querySelectorAll('[data-js-module]');

    for (const element of elements) {
        const moduleName = element.dataset.jsModule;

        if (!moduleName || modules[moduleName]) {
            continue;
        }

        try {
            const module = await import(`./modules/${moduleName}.js`);

            if (typeof module.init === 'function') {
                await module.init(element);
                modules[moduleName] = true;
            }
        } catch (error) {
            console.error(
                `[Mini ERP] Gagal memuat module "${moduleName}".`,
                error
            );
        }
    }
}

function initApp() {
    initAjax();
    initNotification();
    initModules();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp, { once: true });
} else {
    initApp();
}
