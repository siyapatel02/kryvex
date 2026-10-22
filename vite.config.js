import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/pages/auth.css',
                'resources/js/pages/auth.js',
                'resources/js/admin/auth.js',

                // User Pages JS
                'resources/js/pages/dashboard.js',
                'resources/js/pages/settings.js',
                'resources/js/pages/futures.js',
                'resources/js/pages/home.js',
                'resources/js/pages/kyc.js',
                'resources/js/pages/markets.js',
                'resources/js/pages/options.js',
                'resources/js/pages/wallet.js',
                'resources/js/pages/learn.js',
                'resources/js/pages/portfolio.js',
                'resources/js/pages/invite.js',

                // User Pages CSS
                'resources/css/pages/settings.css',
                'resources/css/pages/futures.css',
                'resources/css/pages/home.css',
                'resources/css/pages/markets.css',
                'resources/css/pages/options.css',
                'resources/css/pages/wallet.css',
                'resources/css/pages/learn.css',
                'resources/css/pages/portfolio.css',
                'resources/css/pages/invite.css',

                'resources/css/admin/login.css',
                'resources/css/admin/layout.css',
                'resources/css/admin/dashboard.css',
                'resources/css/admin/users.css',
                'resources/css/admin/user-detail.css',
                'resources/css/admin/wallet.css',
                'resources/css/admin/orders.css',
                'resources/css/admin/positions.css',
                'resources/css/admin/reports.css',
                'resources/css/admin/settings.css',
                'resources/css/admin/audit.css',
                'resources/css/admin/admin.css',
                'resources/css/admin/kyc.css',
                'resources/css/admin/markets.css',
                'resources/css/admin/notifications.css',
                'resources/css/admin/risk.css',
                'resources/css/admin/theme-light-fix.css',
                'resources/css/common/theme-global.css',
                'resources/css/common/toast.css',
                'resources/js/common/theme.js',

                'resources/js/admin/admin.js',
                'resources/js/admin/auth.js',
                'resources/js/admin/login.js',
                'resources/js/admin/layout.js',
                'resources/js/admin/users.js',
                'resources/js/admin/user-detail.js',
                'resources/js/admin/wallet.js',
                'resources/js/admin/positions.js',
                'resources/js/admin/settings.js',
                'resources/js/admin/audit.js',
                'resources/js/admin/kyc.js',
                'resources/js/admin/markets.js',
                'resources/js/common/toast.js',
                'resources/css/pages/invite.css',

            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
      server: {
        host: '0.0.0.0',
        port: 5173,
        cors: true,
        hmr: {
            protocol: 'wss',
            host: ' https://portrait-platinum-lending-pharmacies.trycloudflare.com',
        },
    },
    // server: {
    //     watch: {
    //         ignored: ['**/storage/framework/views/**'],
    //     },
    //     host: '0.0.0.0',          
    //     port: 5173,                
    //     hmr: {
    //         host: '192.168.1.100', 
    //     },
    // },
});