/**
 * Regression guards for CSS collisions with wp-admin.
 *
 * The app renders inside wp-admin, next to WordPress core CSS and the inline
 * admin_head <style> from admin/class-admin-pages.php. jsdom does not resolve
 * the cascade by specificity, so these tests check the sources directly.
 */
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, resolve } from 'node:path';
import postcss from 'postcss';
import wpAdminSpecificity, { prefixSelector } from '../../postcss-wp-admin-specificity.js';

const srcDir = resolve(__dirname, '..');
const adminPagesPhp = resolve(srcDir, '../../admin/class-admin-pages.php');

function inlineAdminStyles(): string {
    const php = readFileSync(adminPagesPhp, 'utf8');
    const start = php.indexOf('function inject_fullscreen_styles');
    expect(start).toBeGreaterThan(-1);
    const body = php.slice(start);
    const open = body.indexOf('<style>');
    const close = body.indexOf('</style>');
    expect(open).toBeGreaterThan(-1);
    expect(close).toBeGreaterThan(open);
    // Drop comments so prose about old selectors does not count.
    return body.slice(open, close).replace(/\/\*[\s\S]*?\*\//g, '');
}

function tsxFiles(dir: string): string[] {
    return readdirSync(dir).flatMap((name) => {
        const path = join(dir, name);
        if (statSync(path).isDirectory()) return tsxFiles(path);
        return path.endsWith('.tsx') && !path.endsWith('.test.tsx') ? [path] : [];
    });
}

describe('wp-admin cascade', () => {
    it('keeps the inline button reset below component class specificity', () => {
        const css = inlineAdminStyles();
        // An ID-qualified `button` selector (specificity 1,0,1) beats .btn-primary,
        // bg-* and border utilities: primary buttons rendered as white text on a
        // transparent background and the modal backdrop disappeared.
        expect(css).not.toMatch(/(^|[\s,}])#peanut-festival-app\s+button\b/);
        expect(css).toMatch(/:where\(#peanut-festival-app\)\s+button\s*\{/);
    });

    it('does not use the `card` class that wp-admin styles', () => {
        // wp-admin forms.css: .card { max-width: 520px; min-width: 255px;
        // margin-top: 20px; padding: .7em 2em 1em; ... }
        const offenders = tsxFiles(srcDir).filter((file) =>
            /className=["'`{][^"'`}]*(?<![\w-])card(?![\w-])/.test(readFileSync(file, 'utf8')),
        );
        expect(offenders).toEqual([]);

        const componentsCss = readFileSync(join(srcDir, 'styles/components.css'), 'utf8');
        expect(componentsCss).toMatch(/^\.pf-card\s*\{/m);
        expect(componentsCss).not.toMatch(/^\.card\s*\{/m);
    });

    it('does not reset form fields with an ID selector', () => {
        const css = inlineAdminStyles();
        // `#peanut-festival-app input[type="text"] { border: 1px solid #e2e8f0;
        // border-radius: .375rem }` (specificity 1,1,1) beat .input's
        // border-gray-300 and rounded-lg on every text field and select.
        expect(css).not.toMatch(/#peanut-festival-app(?!\))[^{}]*\b(input|select|textarea)\b/);
    });

    it('does not reset link colors with an ID selector', () => {
        const css = inlineAdminStyles();
        // `#peanut-festival-app a { color: inherit }` (1,0,1) beat every link
        // color class: the active nav item lost text-primary-700.
        expect(css).not.toMatch(/#peanut-festival-app(?!\))[^{}]*\ba\b\s*[,:{]/);

        // WordPress's `a:hover`/`a:focus` colors are neutralized in the bundle,
        // below the utilities, so link color classes still win.
        const compat = readFileSync(join(srcDir, 'styles/wp-admin-compat.css'), 'utf8').replace(
            /\/\*[\s\S]*?\*\//g,
            '',
        );
        expect(compat).toMatch(/a:hover,\s*a:active,\s*a:focus\s*\{\s*color:\s*inherit/);
    });

    it('loads components before utilities so utilities can override them', () => {
        // `.input` defined after the utilities beat `pl-10`: the Performers
        // search placeholder ran under its icon. `input w-40` was full width.
        const indexCss = readFileSync(join(srcDir, 'index.css'), 'utf8');
        const order = [
            'tailwindcss/preflight.css',
            './styles/wp-admin-compat.css',
            './styles/components.css',
            'tailwindcss/utilities.css',
        ].map((file) => indexCss.indexOf(`@import '${file}'`));
        expect(order.every((i) => i > -1)).toBe(true);
        expect([...order].sort((a, b) => a - b)).toEqual(order);
        // No component class rules left after the utilities import.
        expect(indexCss).not.toMatch(/^\.(input|btn|pf-card|badge|table)\b/m);
    });

    it('wires the html-prefix plugin into the PostCSS config', () => {
        const config = readFileSync(resolve(srcDir, '../postcss.config.js'), 'utf8');
        expect(config).toMatch(/plugins:\s*\[\s*tailwindcss\(\),\s*wpAdminSpecificity\(\)\s*\]/);
    });
});

describe('postcss-wp-admin-specificity', () => {
    it('prefixes class-led selectors and leaves the rest alone', () => {
        expect(prefixSelector('.input')).toBe('html .input');
        expect(prefixSelector('.hover\\:text-gray-900:hover')).toBe('html .hover\\:text-gray-900:hover');
        expect(prefixSelector('a:hover')).toBe('a:hover');
        expect(prefixSelector('*')).toBe('*');
        expect(prefixSelector(':where(.space-y-1 > :not(:last-child))')).toBe(':where(.space-y-1 > :not(:last-child))');
    });

    it('rewrites rules (incl. media queries) but not keyframes', async () => {
        const input = [
            '.input, a:hover { color: red }',
            '@media (min-width: 640px) { .sm\\:p-4 { padding: 1rem } }',
            '@keyframes spin { from { opacity: 0 } to { opacity: 1 } }',
        ].join('\n');
        const { css } = await postcss([wpAdminSpecificity()]).process(input, {
            from: undefined,
        });
        expect(css).toContain('html .input, a:hover');
        expect(css).toContain('html .sm\\:p-4');
        expect(css).toContain('from { opacity: 0 }');
        expect(css).not.toContain('html from');
    });
});
