/**
 * Regression guards for CSS collisions with wp-admin.
 *
 * The app renders inside wp-admin, next to WordPress core CSS and the inline
 * admin_head <style> from admin/class-admin-pages.php. jsdom does not resolve
 * the cascade by specificity, so these tests check the sources directly.
 */
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, resolve } from 'node:path';

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
      /className=["'`{][^"'`}]*(?<![\w-])card(?![\w-])/.test(
        readFileSync(file, 'utf8'),
      ),
    );
    expect(offenders).toEqual([]);

    const indexCss = readFileSync(join(srcDir, 'index.css'), 'utf8');
    expect(indexCss).toMatch(/^\.pf-card\s*\{/m);
    expect(indexCss).not.toMatch(/^\.card\s*\{/m);
  });
});
