/**
 * PostCSS plugin: prefix every class-led selector with `html `.
 *
 * The admin app renders inside wp-admin, whose core CSS styles form fields
 * and links with element-level selectors of specificity (0,1,1):
 * `input[type=text]`, `select`/`textarea` via `.wp-core-ui select`,
 * `a:hover`, `a:focus`. A Tailwind class is (0,1,0), so inside WordPress
 * `.input`, `pl-10`, `rounded-lg`, `text-primary-700` and friends lost to
 * WordPress on exactly the elements that carry them.
 *
 * Prefixing class-led selectors with `html` adds one type selector: (0,1,1).
 * That ties WordPress, and this bundle is printed after WordPress core CSS,
 * so the app wins. Because every class-led rule gets the same prefix, the
 * app's own relative order (components < utilities < variants) is unchanged.
 * Element-led rules (preflight, base, wp-admin-compat.css) are left alone so
 * classes still beat them. Selectors inside @keyframes are not touched.
 */
export const PREFIX = 'html';

export function prefixSelector(selector, prefix = PREFIX) {
  const trimmed = selector.trim();
  if (!trimmed.startsWith('.')) return selector;
  return `${prefix} ${trimmed}`;
}

export default function wpAdminSpecificity(opts = {}) {
  const prefix = opts.prefix ?? PREFIX;
  return {
    postcssPlugin: 'wp-admin-specificity',
    Once(root) {
      root.walkRules((rule) => {
        for (let p = rule.parent; p; p = p.parent) {
          if (p.type === 'atrule' && /keyframes$/i.test(p.name)) return;
        }
        rule.selectors = rule.selectors.map((sel) => prefixSelector(sel, prefix));
      });
    },
  };
}
wpAdminSpecificity.postcss = true;
