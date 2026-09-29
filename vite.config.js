import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'node:path';

/**
 * The host other machines use to reach this server.
 *
 * In dev, Laravel writes this into public/hot and renders every asset tag
 * against it, so "localhost" would send each visitor's browser to its OWN
 * machine and nothing would load over the LAN. Taken from APP_URL so the LAN
 * address is configured in exactly one place.
 */
const devServerHost = (mode) => {
    const appUrl = loadEnv(mode, process.cwd(), '').APP_URL;

    try {
        return new URL(appUrl).hostname;
    } catch {
        return '192.168.2.51';
    }
};

export default defineConfig(({ mode }) => ({
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: devServerHost(mode),
            clientPort: 5174,
        },
        watch: {
            usePolling: true,
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
}));
