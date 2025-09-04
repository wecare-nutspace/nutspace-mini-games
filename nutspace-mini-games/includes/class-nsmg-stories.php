<?php
if (!defined('ABSPATH')) exit;

/**
 * NSMG_Stories – Stories CPT + polished Story Builder (simplified order) + Live Preview
 * v0.9.1-Builder
 */
class NSMG_Stories {

  public static function init(){
    add_action('init', [__CLASS__, 'register_cpt']);
    add_action('init', [__CLASS__, 'register_grade_tax']);

    add_action('add_meta_boxes', [__CLASS__, 'meta_boxes']);
    add_action('save_post', [__CLASS__, 'save_meta']);

    // Admin assets only on Story editor screens
    add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);
  }

  /* ---------------- CPT + Taxonomy ---------------- */
  public static function register_cpt(){
    $labels = [
      'name'                  => __('Stories','nsmg'),
      'singular_name'         => __('Story','nsmg'),
      'menu_name'             => __('Stories','nsmg'),
      'name_admin_bar'        => __('Story','nsmg'),
      'add_new'               => __('Add Story','nsmg'),
      'add_new_item'          => __('Add Story','nsmg'),
      'edit_item'             => __('Edit Story','nsmg'),
      'new_item'              => __('New Story','nsmg'),
      'view_item'             => __('View Story','nsmg'),
      'view_items'            => __('View Stories','nsmg'),
      'search_items'          => __('Search Stories','nsmg'),
      'not_found'             => __('No stories found','nsmg'),
      'not_found_in_trash'    => __('No stories found in Trash','nsmg'),
      'all_items'             => __('Stories','nsmg'),
    ];

    register_post_type('nsmg_story', [
      'label'        => 'Stories',
      'labels'       => $labels,
      'public'       => true,
      'show_in_menu' => 'nsmg_root',   // under NutSpace Mini Games
      'menu_icon'    => 'dashicons-book',
      'supports'     => ['title'],
      'has_archive'  => false,
      'show_in_rest' => false,
      'rewrite'      => false,
      'taxonomies'   => ['nsmg_grade'], // show Grades meta box on the side
    ]);
  }

  public static function register_grade_tax(){
    register_taxonomy('nsmg_grade', 'nsmg_story', [
      'labels' => [
        'name'          => __('Grades','nsmg'),
        'singular_name' => __('Grade','nsmg'),
        'search_items'  => __('Search Grades','nsmg'),
        'all_items'     => __('All Grades','nsmg'),
        'edit_item'     => __('Edit Grade','nsmg'),
        'update_item'   => __('Update Grade','nsmg'),
        'add_new_item'  => __('Add New Grade','nsmg'),
        'new_item_name' => __('New Grade Name','nsmg'),
        'menu_name'     => __('Grades','nsmg'),
      ],
      'public'            => true,
      'hierarchical'      => true,          // ✅ checklist (multi-select), not free-text tags
      'show_ui'           => true,
      'show_admin_column' => true,          // show column in Stories list
      'show_in_rest'      => true,
      'meta_box_cb'       => null,          // default WP checklist meta box
    ]);
  }

  /* ---------------- Admin Assets ---------------- */
  public static function admin_assets($hook){
    global $post_type;
    if ( ($hook === 'post-new.php' || $hook === 'post.php') && $post_type === 'nsmg_story' ) {
      wp_enqueue_media();
      wp_enqueue_script('jquery-ui-sortable');

      wp_enqueue_style(
        'nsmg-stories-admin',
        plugin_dir_url(__FILE__).'../assets/admin/nsmg-stories-admin.css',
        [],
        defined('NSMG_VERSION')?NSMG_VERSION:'0.9.1'
      );
      wp_enqueue_script(
        'nsmg-stories-admin',
        plugin_dir_url(__FILE__).'../assets/admin/nsmg-stories-admin.js',
        ['jquery','jquery-ui-sortable'],
        defined('NSMG_VERSION')?NSMG_VERSION:'0.9.1',
        true
      );

      $post_id = isset($_GET['post']) ? intval($_GET['post']) : 0;
      $audio   = $post_id ? get_post_meta($post_id, '_nsmg_audio', true) : '';
      $images  = $post_id ? get_post_meta($post_id, '_nsmg_images', true) : '';
      $order   = $post_id ? get_post_meta($post_id, '_nsmg_order', true) : '';

      wp_localize_script('nsmg-stories-admin', 'NSMG_STORY_PREFILL', [
        'audio'  => $audio,
        'images' => $images,  // CSV of URLs
        'order'  => $order,   // CSV of filenames
      ]);
    }
  }

  /* ---------------- Meta Box ---------------- */
  public static function meta_boxes(){
    add_meta_box(
      'nsmg_story_builder',
      __('Story Builder','nsmg'),
      [__CLASS__, 'render_builder'],
      'nsmg_story',
      'normal',
      'high'
    );
  }

  public static function render_builder($post){
    wp_nonce_field('nsmg_story_save','nsmg_story_nonce');

    $audio  = get_post_meta($post->ID, '_nsmg_audio', true);
    $images = get_post_meta($post->ID, '_nsmg_images', true); // CSV of URLs
    $order  = get_post_meta($post->ID, '_nsmg_order', true);  // CSV of filenames
    ?>
    <div class="nsmg-builder">
      <!-- AUDIO -->
      <section class="nsmg-block">
        <h3><?php _e('Audio','nsmg'); ?></h3>
        <div class="row-inline">
          <button type="button" class="button button-secondary" id="nsmg-choose-audio"><?php _e('Choose/Upload Audio','nsmg'); ?></button>
          <input type="text" id="nsmg-audio-url" name="nsmg_audio" value="<?php echo esc_attr($audio); ?>" placeholder="https://example.com/audio.mp3" readonly />
        </div>
        <div id="nsmg-audio-preview" class="nsmg-audio-preview">
          <?php if ($audio): ?>
            <audio controls src="<?php echo esc_url($audio); ?>" style="max-width:420px;"></audio>
          <?php endif; ?>
        </div>
      </section>

      <!-- IMAGES -->
      <section class="nsmg-block">
        <h3><?php _e('Images','nsmg'); ?></h3>
        <button type="button" class="button button-secondary" id="nsmg-add-images"><?php _e('Add Images','nsmg'); ?></button>
        <p class="description"><?php _e('Drag tiles to set the correct sequence. The order updates automatically.','nsmg'); ?></p>

        <div id="nsmg-images-grid" class="nsmg-grid" data-empty="<?php esc_attr_e('No images selected yet. Click “Add Images”.','nsmg'); ?>">
          <!-- tiles via JS -->
        </div>
        <input type="hidden" id="nsmg-images-csv" name="nsmg_images" value="<?php echo esc_attr($images); ?>"/>
      </section>

      <!-- ORDER -->
      <section class="nsmg-block">
        <h3><?php _e('Correct Order','nsmg'); ?></h3>
        <div id="nsmg-order-chips" class="nsmg-order-chips"><!-- chips via JS --></div>
        <label class="manual-toggle">
          <input type="checkbox" id="nsmg-edit-order-manually"/> <?php _e('Edit order manually (advanced)','nsmg'); ?>
        </label>
        <input type="text" id="nsmg-order-manual" name="nsmg_order" value="<?php echo esc_attr($order); ?>" placeholder="1.png, 2.png, 3.png, 4.png" style="display:none; width:100%; margin-top:8px;" />
        <p class="description"><?php _e('Normally you don’t need to edit this. It follows the image grid order.','nsmg'); ?></p>
      </section>

      <!-- PREVIEW -->
      <section class="nsmg-block">
        <h3><?php _e('Live Preview','nsmg'); ?></h3>
        <button type="button" class="button button-primary" id="nsmg-live-preview"><?php _e('Open Preview','nsmg'); ?></button>
        <p class="description"><?php _e('Shows how the board will look. Drag is disabled in preview.','nsmg'); ?></p>
        <div id="nsmg-preview-modal" class="nsmg-modal" style="display:none;">
          <div class="nsmg-modal-inner">
            <button type="button" class="nsmg-modal-close" aria-label="Close">&times;</button>
            <h2><?php _e('Story Sequencing – Preview','nsmg'); ?></h2>
            <div class="nsmg-preview-board">
              <div class="nsmg-preview-palette"></div>
              <div class="nsmg-preview-slots"></div>
            </div>
            <div class="nsmg-preview-note"><?php _e('Numbers indicate target order. Cards here are shuffled for preview.','nsmg'); ?></div>
          </div>
        </div>
      </section>

      <!-- VALIDATION NOTE -->
      <section class="nsmg-block">
        <h3><?php _e('Validation','nsmg'); ?></h3>
        <ul class="nsmg-checklist">
          <li><?php _e('At least 4 images selected','nsmg'); ?></li>
          <li><?php _e('All image filenames must be unique','nsmg'); ?></li>
          <li><?php _e('Correct Order references only the selected filenames','nsmg'); ?></li>
        </ul>
      </section>
    </div>
    <?php
  }

  /* ---------------- Save ---------------- */
  public static function save_meta($post_id){
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (!isset($_POST['nsmg_story_nonce']) || !wp_verify_nonce($_POST['nsmg_story_nonce'],'nsmg_story_save')) return;

    // Audio
    if (isset($_POST['nsmg_audio'])) {
      update_post_meta($post_id, '_nsmg_audio', esc_url_raw($_POST['nsmg_audio']));
    }

    // Images (CSV of URLs)
    $images_csv = isset($_POST['nsmg_images']) ? trim((string)$_POST['nsmg_images']) : '';
    update_post_meta($post_id, '_nsmg_images', sanitize_textarea_field($images_csv));

    // Order (CSV of filenames) — from manual field if provided; else derive from image URLs order
    $order_csv = isset($_POST['nsmg_order']) ? trim((string)$_POST['nsmg_order']) : '';
    if ($order_csv === '' && $images_csv !== '') {
      $urls = array_filter(array_map('trim', explode(',', $images_csv)));
      $fns  = [];
      foreach ($urls as $u) { $fns[] = basename(parse_url($u, PHP_URL_PATH)); }
      $order_csv = implode(', ', $fns);
    }
    update_post_meta($post_id, '_nsmg_order', sanitize_text_field($order_csv));
  }
}

NSMG_Stories::init();