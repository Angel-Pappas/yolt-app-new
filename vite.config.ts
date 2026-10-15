import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { fileURLToPath } from 'node:url';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    // Unit tests (`vp test`) only exercise plain TypeScript helpers: they need the
    // `@/` alias but none of the app plugins — and laravel-vite-plugin refuses to
    // start a Vite server when CI=true, which is how the test runner boots.
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    plugins: lazyPlugins(() =>
        process.env.VITEST
            ? []
            : [
                  laravel({
                      input: ['resources/css/app.css', 'resources/js/app.tsx'],
                      refresh: true,
                      fonts: [
                          bunny('Instrument Sans', {
                              weights: [400, 500, 600],
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
              ],
    ),
    // Only the app's own tests: Composer packages (vendor/) can ship JavaScript
    // test files of their own, with dependencies this project does not install.
    test: {
        include: ['resources/js/**/*.test.ts'],
    },
    server: {
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
            'resources/js/components/ui/*',
            'resources/views/mail/*',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            entryPoint: 'resources/css/app.css',
        },
    },
});
