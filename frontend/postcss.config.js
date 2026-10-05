import tailwindcss from '@tailwindcss/postcss';
import wpAdminSpecificity from './postcss-wp-admin-specificity.js';

export default {
  // wpAdminSpecificity runs on Tailwind's output; see the file for why.
  plugins: [tailwindcss(), wpAdminSpecificity()],
};
