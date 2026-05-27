import '../css/app.css';
import './bootstrap';
import 'primeicons/primeicons.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';

import PrimeVue from 'primevue/config';
import Aura from '@primeuix/themes/aura';
import ToastService from 'primevue/toastservice';
import { primevuePt } from './primevue/passthrough';
import { sk } from './primevue/locales/sk';



createInertiaApp({
    title: (title) => title ? `${title} - Clinvia` : 'Clinvia',
    resolve: async (name) => {
        const page = await resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue')
        );

        // If a page doesn't export a title, generate a readable default from the component name
        if (!page.default.title) {
            const parts = name.split('/');
            const last = parts[parts.length - 1] || name;
            const words = last
                .replace(/[-_]/g, ' ')
                .replace(/([A-Z])/g, ' $1')
                .trim();

            page.default.title = words.charAt(0).toUpperCase() + words.slice(1);
        }

        return page;
    },
    setup({ el, App, props, plugin }) {
        const vueApp = createApp({ render: () => h(App, props) });

        vueApp.config.globalProperties.route = window.route;

        vueApp
            .use(plugin)
            .use(PrimeVue, {
                theme: {
                    preset: Aura,
                    options: {
                        darkModeSelector: false,
                    },
                },
                ripple: true,
                pt: primevuePt,
                locale: sk,
                ptOptions: {
                    mergeSections: true,
                    mergeProps: true,
                },
            })
            .use(ToastService)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});