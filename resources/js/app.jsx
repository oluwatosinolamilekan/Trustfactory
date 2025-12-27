import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Global error handler for Inertia requests
document.addEventListener('DOMContentLoaded', () => {
    // Intercept Inertia errors
    router.on('error', (event) => {
        // Check if it's a 419 CSRF error
        if (event.detail && event.detail.response && event.detail.response.status === 419) {
            console.warn('CSRF token mismatch detected. Reloading page...');
            window.location.reload();
        }
    });
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        // Update CSRF token whenever Inertia navigation occurs
        router.on('navigate', (event) => {
            const csrfToken = event.detail.page.props.csrf_token;
            if (csrfToken) {
                // Update the meta tag
                const metaTag = document.head.querySelector('meta[name="csrf-token"]');
                if (metaTag) {
                    metaTag.content = csrfToken;
                }
                // Update axios default header
                if (window.axios) {
                    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
                }
            }
        });

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});
