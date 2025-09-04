<?php
if (!defined('ABSPATH')) exit;

class NSMG_Admin {
  public static function init(){
    add_action('admin_menu', [__CLASS__, 'menu']);
    // Safety: remove any stray top-level Stories menu a theme/plugin may add
    add_action('admin_menu', function(){
      remove_menu_page('edit.php?post_type=nsmg_story');
    }, 999);
  }

  public static function menu(){
    // Parent
    add_menu_page(
      __('NutSpace Mini Games','nsmg'),
      __('NutSpace Mini Games','nsmg'),
      'edit_posts',
      'nsmg_root',
      [__CLASS__, 'render_dashboard'],
      'dashicons-games',
      58
    );

    // Add our own "Dashboard" submenu so WP doesn't auto-duplicate the parent
    add_submenu_page(
      'nsmg_root',
      __('Dashboard','nsmg'),
      __('Dashboard','nsmg'),
      'edit_posts',
      'nsmg_root',
      [__CLASS__, 'render_dashboard']
    );

    // DO NOT add a manual "Stories" submenu — WP will auto-add it for the CPT.
    // Keep "Add Story" and "Grades".
    add_submenu_page(
      'nsmg_root',
      __('Add Story','nsmg'),
      __('Add Story','nsmg'),
      'edit_posts',
      'post-new.php?post_type=nsmg_story'
    );

    add_submenu_page(
      'nsmg_root',
      __('Grades','nsmg'),
      __('Grades','nsmg'),
      'manage_categories',
      'edit-tags.php?taxonomy=nsmg_grade&post_type=nsmg_story'
    );
  }

  public static function render_dashboard(){
    echo '<div class="wrap"><h1>NutSpace Mini Games</h1>';
    echo '<p>Use the menu on the left to manage <strong>Stories</strong> and <strong>Grades</strong>.</p>';
    echo '<p><em>Leads, Scores, Settings & Experiments</em> coming soon.</p>';
    echo '</div>';
  }
}
NSMG_Admin::init();