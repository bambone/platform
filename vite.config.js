import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/platform-marketing.css',
                'resources/js/platform-marketing.js',
                'resources/css/platform-admin.css',
                'resources/js/platform-admin-overlay-diagnostics.js',
                'resources/css/tenant-admin.css',
                'resources/js/tenant-admin-notifications.js',
                'resources/css/booking-calendar.css',
                'resources/js/booking-calendar.js',
                'resources/css/tenant-expert-auto.css',
                'resources/css/tenant-advocate-editorial.css',
                'resources/js/tenant-expert-inquiry-form.js',
                'resources/js/service-program-cover-focal-editor.js',
                'resources/js/tenant-public-push.js',
                'resources/js/tenant-admin-onesignal.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
