<?php
/**
 * Plugin Name: NutSpace Mini Games
 * Description: Story Sequencing mini-game with parent accounts, children profiles, passwordless (magic link) login, leaderboards, and downloadable certificates.
 * Version: 1.0.0
 * Author: NutSpace
 * Text Domain: nsmg
 */

if (!defined('ABSPATH')) exit;

define('NSMG_VERSION', '1.0.0');
define('NSMG_DIR', plugin_dir_path(__FILE__));
define('NSMG_URL', plugin_dir_url(__FILE__));

/**
 * Expose config for JS (REST base).
 * NOTE: v1.0 uses the nsmg/v1 namespace.
 */
add_action('wp_head', function () {
    $cfg = array(
        'rest' => esc_url_raw(site_url('/wp-json/nsmg/v1/')),
    );
    echo '<script>window.NSMG_CFG = ' . wp_json_encode($cfg) . ';</script>';
});

/**
 * Enqueue assets.
 * - Keeps lodash to avoid theme/plugin JS halts.
 * - Loads common + game assets.
 */
function nsmg_enqueue_assets() {
    wp_enqueue_script('lodash');

    // Common CSS
    wp_enqueue_style(
        'nsmg-common',
        NSMG_URL . 'assets/nsmg-common.css',
        array(),
        NSMG_VERSION
    );

    // Game CSS
    wp_enqueue_style(
        'nsmg-seq-style',
        NSMG_URL . 'games/story_sequence/assets/style.css',
        array('nsmg-common'),
        NSMG_VERSION
    );

    // Common JS (popup + magic link triggers + rewards)
    wp_enqueue_script(
        'nsmg-common',
        NSMG_URL . 'assets/nsmg-common.js',
        array('jquery', 'lodash'),
        NSMG_VERSION,
        true
    );

    // Game JS
    wp_enqueue_script(
        'nsmg-seq-app',
        NSMG_URL . 'games/story_sequence/assets/app.js',
        array('jquery', 'nsmg-common'),
        NSMG_VERSION,
        true
    );

    // 🔹 Option B: expose child_id from ?nsmg_play_as=### to JS BEFORE app.js runs
    $child_id_for_js = isset($_GET['nsmg_play_as']) ? (int) $_GET['nsmg_play_as'] : 0;
    wp_add_inline_script(
        'nsmg-seq-app',
        'window.NSMG_CURRENT_CHILD_ID = ' . ($child_id_for_js ?: 'null') . ';',
        'before'
    );

    // Submit Score JS (if you created this helper file)
    wp_enqueue_script(
        'nsmg-submit-score',
        NSMG_URL . 'games/story_sequence/assets/submit-score.js',
        array('nsmg-seq-app'),
        NSMG_VERSION,
        true
    );

    // Inline keyframes for confetti (tiny)
    $confetti_css = '@keyframes ns-confetti-fall{0%{transform:translateY(-10px) rotate(0)}100%{transform:translateY(60px) rotate(360deg)}}';
    wp_add_inline_style('nsmg-seq-style', $confetti_css);
}
add_action('wp_enqueue_scripts', 'nsmg_enqueue_assets');

/**
 * Shortcode: [nsmg_story_sequence]
 * Renders the Story Sequencing game template.
 */
function nsmg_story_sequence_shortcode() {
    ob_start();
    include NSMG_DIR . 'games/story_sequence/template.php';
    return ob_get_clean();
}
add_shortcode('nsmg_story_sequence', 'nsmg_story_sequence_shortcode');

/**
 * Includes
 * - Existing: Stories CPT/Builder, legacy API, Admin helpers
 * - New v1.0: Accounts/Children, Magic Link, Scores/Leaderboards, Certificates
 */
require_once NSMG_DIR . 'includes/class-nsmg-stories.php';
require_once NSMG_DIR . 'includes/class-nsmg-api.php';
require_once NSMG_DIR . 'includes/class-nsmg-admin.php';

require_once NSMG_DIR . 'includes/class-nsmg-accounts.php';       // parent role + children CPT + shortcodes + REST
require_once NSMG_DIR . 'includes/class-nsmg-magiclink.php';      // passwordless login (one-time link)
require_once NSMG_DIR . 'includes/class-nsmg-scores.php';         // score submit + leaderboards (by grade/child)
require_once NSMG_DIR . 'includes/class-nsmg-certificates.php';   // PNG certificate endpoint

/**
 * Optional: basic sanity check on activation (GD for certificates).
 */
register_activation_hook(__FILE__, function () {
    if (!function_exists('imagecreatefrompng')) {
        // Don’t fatal; just warn in admin later if you prefer.
        error_log('[NSMG] GD extension not found. Certificates image rendering will be unavailable.');
    }
});
