import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defaultAllowedOrigins, loadEnv } from 'vite';
import { defineConfig, lazyPlugins } from 'vite-plus';

function viteDevServerCorsOrigins(env: Record<string, string>) {
    const origins: (RegExp | string)[] = [
        defaultAllowedOrigins,
        /^https?:\/\/.*\.test(:\d+)?$/,
    ];

    if (env.APP_URL) {
        origins.push(env.APP_URL);
    }

    if (env.NGROK_DOMAIN) {
        origins.push(`https://${env.NGROK_DOMAIN}`);
    }

    return origins;
}

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    return {
        plugins: lazyPlugins(() => [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.tsx'],
                refresh: true,
                fonts: [
                    bunny('Source Sans 3', {
                        weights: [400, 500, 600, 700],
                    }),
                    bunny('Yeseva One', {
                        weights: [400],
                    }),
                ],
            }),
            inertia(),
            react(),
            babel({
                presets: [reactCompilerPreset()],
            }),
            tailwindcss(),
            wayfinder({
                formVariants: true,
            }),
        ]),
        server: {
            cors: {
                origin: viteDevServerCorsOrigins(env),
            },
            watch: {
                ignored: [
                    '**/.agents/**',
                    '**/.claude/**',
                    '**/.cursor/**',
                    '**/.junie/**',
                    '**/vendor/**',
                ],
            },
        },
        lint: {
            ignorePatterns: [
                'vendor/**',
                'node_modules/**',
                'public/**',
                'bootstrap/ssr/**',
                'tailwind.config.js',
                'resources/js/actions/**',
                'resources/js/components/ui/*',
                'resources/js/routes/**',
                'resources/js/wayfinder/**',
            ],
            options: {
                denyWarnings: true,
                typeAware: true,
            },
        },
        fmt: {
            printWidth: 80,
            tabWidth: 4,
            singleQuote: true,
            semi: true,
            singleAttributePerLine: false,
            htmlWhitespaceSensitivity: 'css',
            ignorePatterns: [
                '.github/**',
                'composer.json',
                'resources/js/components/ui/*',
                'resources/views/mail/*',
            ],
            sortTailwindcss: {
                functions: ['clsx', 'cn', 'cva'],
                stylesheet: 'resources/css/app.css',
            },
        },
    };
});
