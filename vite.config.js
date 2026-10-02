import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import statamic from '@statamic/cms/vite-plugin';
import tailwindcss from '@tailwindcss/vite';

/**
 * The control panel bundle: the footnote toolbar button and node view.
 * `statamic()` externalises `vue` onto the CP's own runtime and leaves
 * `@statamic/cms/*` imports to the host, so this stays a few kilobytes
 * rather than a second copy of the CP.
 *
 * `@statamic/cms` resolves to vendor/statamic/cms/resources/dist-package,
 * so `composer install` has to have run before `npm install`.
 *
 * `dist` matches the provider's `$vite` config; the build is committed so
 * consumers never need a Node toolchain (they publish it instead).
 */
export default defineConfig({
    plugins: [
        statamic(),
        tailwindcss(),
        laravel({
            input: ['resources/js/cp.js', 'resources/css/cp.css'],
            publicDirectory: 'dist',
            hotFile: 'dist/hot',
        }),
    ],
});
