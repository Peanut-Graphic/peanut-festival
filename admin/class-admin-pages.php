<?php
/**
 * Admin pages class - mounts the React SPA in fullscreen mode
 */

if (!defined('ABSPATH')) {
    exit;
}

class Peanut_Festival_Admin_Pages {

    public function __construct() {
        // Inject fullscreen styles in admin head
        add_action('admin_head', [$this, 'inject_fullscreen_styles']);
    }

    public function add_admin_menu(): void {
        add_menu_page(
            __('Festival', 'peanut-festival'),
            __('Festival', 'peanut-festival'),
            'manage_options',
            'peanut-festival',
            [$this, 'render_admin_page'],
            'dashicons-tickets-alt',
            26
        );
    }

    /**
     * Render the React app container in fullscreen mode
     */
    public function render_admin_page(): void {
        ?>
        <div class="peanut-festival-fullscreen-app">
            <div id="peanut-festival-app">
                <div style="display: flex; align-items: center; justify-content: center; height: 100%; flex-direction: column; color: #64748b;">
                    <div style="width: 40px; height: 40px; border: 3px solid #e2e8f0; border-top-color: #dc2626; border-radius: 50%; animation: pf-spin 0.8s linear infinite;"></div>
                    <p style="margin-top: 16px;">Loading Peanut Festival...</p>
                </div>
            </div>
        </div>
        <style>
            @keyframes pf-spin { to { transform: rotate(360deg); } }
        </style>
        <?php
    }

    /**
     * Inject CSS for fullscreen React app (escapes WordPress admin CSS)
     */
    public function inject_fullscreen_styles(): void {
        if (!isset($_GET['page']) || $_GET['page'] !== 'peanut-festival') {
            return;
        }

        ?>
        <style>
            /* Hide WordPress admin chrome for fullscreen React app */
            html.wp-toolbar {
                padding-top: 0 !important;
            }
            #wpadminbar {
                display: none !important;
            }
            #adminmenumain,
            #adminmenuback,
            #adminmenuwrap {
                display: none !important;
            }
            #wpcontent,
            #wpfooter {
                margin-left: 0 !important;
            }
            #wpbody-content {
                padding-bottom: 0 !important;
            }
            .update-nag,
            .updated,
            .notice,
            .error:not(.peanut-error) {
                display: none !important;
            }
            #wpfooter {
                display: none !important;
            }
            /* Fullscreen container */
            .peanut-festival-fullscreen-app {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 99999;
                background: #f8fafc;
                overflow: hidden;
            }
            #peanut-festival-app {
                height: 100%;
                width: 100%;
                overflow: auto;
            }

            /* ===== CSS ISOLATION FOR TAILWIND ===== */
            /* Override WordPress's .hidden class that may have !important */
            #peanut-festival-app .hidden {
                display: none;
            }
            /* Tailwind responsive utilities - override any WP conflicts */
            @media (min-width: 768px) {
                #peanut-festival-app .md\:block {
                    display: block !important;
                }
                #peanut-festival-app .md\:hidden {
                    display: none !important;
                }
                #peanut-festival-app .md\:flex {
                    display: flex !important;
                }
                #peanut-festival-app .md\:ml-56 {
                    margin-left: 14rem !important;
                }
                #peanut-festival-app .md\:ml-16 {
                    margin-left: 4rem !important;
                }
            }
            /* Ensure fixed positioning works inside the app */
            #peanut-festival-app .fixed {
                position: fixed;
            }
            /* Ensure sidebar z-index is high enough */
            #peanut-festival-app aside.fixed {
                z-index: 100;
            }
            /*
             * Reset WordPress button styles inside the app. :where() keeps the
             * selector at element specificity (0,0,1) so component and utility
             * classes (.btn-primary, bg-*, border) still win. An ID selector
             * here made primary buttons transparent (white text on nothing),
             * hid the modal backdrop and stripped .btn-secondary's border.
             */
            :where(#peanut-festival-app) button {
                background-color: transparent;
                border: none;
                box-shadow: none;
                text-shadow: none;
            }
            /* Reset WordPress form field styles */
            #peanut-festival-app input[type="text"],
            #peanut-festival-app input[type="email"],
            #peanut-festival-app input[type="url"],
            #peanut-festival-app input[type="password"],
            #peanut-festival-app input[type="search"],
            #peanut-festival-app input[type="number"],
            #peanut-festival-app textarea,
            #peanut-festival-app select {
                background-color: white;
                border: 1px solid #e2e8f0;
                box-shadow: none;
                border-radius: 0.375rem;
            }
            /* Reset WordPress link colors */
            #peanut-festival-app a {
                color: inherit;
                text-decoration: none;
            }
            #peanut-festival-app a:hover {
                color: inherit;
            }
            #peanut-festival-app a:focus {
                box-shadow: none;
                outline: none;
            }
        </style>
        <?php
    }

    public function enqueue_scripts(string $hook): void {
        if ($hook !== 'toplevel_page_peanut-festival') {
            return;
        }

        $dist_path = PEANUT_FESTIVAL_PATH . 'assets/dist/';
        $dist_url = PEANUT_FESTIVAL_URL . 'assets/dist/';
        $manifest_path = $dist_path . '.vite/manifest.json';

        // Check if we're in development mode (manifest doesn't exist)
        $is_dev = !file_exists($manifest_path);

        if ($is_dev) {
            // Development mode - load from Vite dev server
            $dev_url = 'http://localhost:3002';

            wp_enqueue_script(
                'peanut-festival-react-refresh',
                $dev_url . '/@react-refresh',
                [],
                null,
                false
            );

            wp_enqueue_script(
                'peanut-festival-vite-client',
                $dev_url . '/@vite/client',
                [],
                null,
                true
            );

            wp_enqueue_script(
                'peanut-festival-app',
                $dev_url . '/src/main.tsx',
                ['peanut-festival-vite-client'],
                null,
                true
            );

            // Add type="module" to scripts
            add_filter('script_loader_tag', function($tag, $handle) {
                if (strpos($handle, 'peanut-festival') !== false) {
                    return str_replace(' src', ' type="module" src', $tag);
                }
                return $tag;
            }, 10, 2);
        } else {
            // Production mode - load from manifest
            $manifest = json_decode(file_get_contents($manifest_path), true);

            if (isset($manifest['src/main.tsx'])) {
                $entry = $manifest['src/main.tsx'];

                // Enqueue CSS
                if (!empty($entry['css'])) {
                    foreach ($entry['css'] as $index => $css_file) {
                        wp_enqueue_style(
                            'peanut-festival-app-' . $index,
                            $dist_url . $css_file,
                            [],
                            PEANUT_FESTIVAL_VERSION
                        );
                    }
                }

                // Enqueue JS
                wp_enqueue_script(
                    'peanut-festival-app',
                    $dist_url . $entry['file'],
                    [],
                    PEANUT_FESTIVAL_VERSION,
                    true
                );

                // Add type="module"
                add_filter('script_loader_tag', function($tag, $handle) {
                    if ($handle === 'peanut-festival-app') {
                        return str_replace(' src', ' type="module" src', $tag);
                    }
                    return $tag;
                }, 10, 2);
            }
        }

        // Localize script with API data
        wp_localize_script('peanut-festival-app', 'peanutFestival', [
            'apiUrl' => rest_url('peanut-festival/v1'),
            'adminApiUrl' => rest_url('peanut-festival/v1/admin'),
            'nonce' => wp_create_nonce('wp_rest'),
            'version' => PEANUT_FESTIVAL_VERSION,
            'siteUrl' => get_site_url(),
            'adminUrl' => admin_url(),
            'userId' => get_current_user_id(),
            'isAdmin' => current_user_can('manage_options'),
        ]);

        // Hide admin notices on our page
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
    }
}
