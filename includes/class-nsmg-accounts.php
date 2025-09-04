<?php
if (!defined('ABSPATH')) exit;

/**
 * NSMG_Accounts: parent role, child CPT, shortcodes, REST endpoints
 * v1.0
 */
class NSMG_Accounts {

  public static function init() {
    add_action('init', [__CLASS__, 'register_roles']);
    add_action('init', [__CLASS__, 'register_child_cpt']);
    add_action('init', [__CLASS__, 'register_shortcodes']);

    add_action('rest_api_init', [__CLASS__, 'register_rest']);

    // Ensure authorship lock: parent owns their child posts
    add_action('user_register', [__CLASS__, 'maybe_grant_parent_role']);
  }

  /* ---------- Roles ---------- */
  public static function register_roles() {
    if (!get_role('nsmg_parent')) {
      add_role('nsmg_parent', __('NutSpace Parent','nsmg'), get_role('subscriber')->capabilities ?? []);
    }
  }
  public static function maybe_grant_parent_role($user_id) {
    $u = get_user_by('id', $user_id);
    if ($u && empty(array_intersect(['administrator','editor','author','contributor'], $u->roles))) {
      $u->add_role('nsmg_parent');
    }
  }

  /* ---------- CPT: Child ---------- */
  public static function register_child_cpt() {
    register_post_type('nsmg_child', [
      'labels' => [
        'name' => __('Children','nsmg'),
        'singular_name' => __('Child','nsmg'),
      ],
      'public' => false,
      'show_ui' => true,                // visible to admins in backend
      'show_in_menu' => 'nsmg_root',
      'supports' => ['title','author','thumbnail'],
      'capability_type' => 'post',
      'map_meta_cap' => true,
      'show_in_rest' => false,
    ]);

    // Use your existing grade taxonomy in the editing UI
    register_taxonomy_for_object_type('nsmg_grade','nsmg_child');
  }

  /* ---------- Shortcodes ---------- */
  public static function register_shortcodes() {
    add_shortcode('nsmg_auth', [__CLASS__, 'sc_auth']);
    add_shortcode('nsmg_parent_dashboard', [__CLASS__, 'sc_parent_dashboard']);
  }

  public static function sc_auth($atts=[]) {
    if (is_user_logged_in()) {
      return '<div class="nsmg-auth">You are logged in. <a href="'.esc_url(wp_logout_url(get_permalink())).'">Log out</a></div>';
    }
    // Simple login/register toggle (password-based baseline)
    ob_start(); ?>
      <div class="nsmg-auth nsmg-card">
        <div class="nsmg-tabs">
          <button class="nsmg-tab is-active" data-target="#nsmg-login">Login</button>
          <button class="nsmg-tab" data-target="#nsmg-register">Register</button>
        </div>
        <div id="nsmg-login" class="nsmg-pane" style="display:block;">
          <form method="post">
            <?php wp_nonce_field('nsmg_auth','nsmg_auth_nonce'); ?>
            <input type="hidden" name="nsmg_auth_action" value="login"/>
            <p><label>Email<br/><input type="email" name="user_login" required></label></p>
            <p><label>Password<br/><input type="password" name="user_pass" required></label></p>
            <p><button class="button button-primary">Login</button></p>
          </form>
        </div>
        <div id="nsmg-register" class="nsmg-pane">
          <form method="post">
            <?php wp_nonce_field('nsmg_auth','nsmg_auth_nonce'); ?>
            <input type="hidden" name="nsmg_auth_action" value="register"/>
            <p><label>Name<br/><input type="text" name="display_name" required></label></p>
            <p><label>Email<br/><input type="email" name="user_email" required></label></p>
            <p><label>Password<br/><input type="password" name="user_pass" required></label></p>
            <p><button class="button button-primary">Create Account</button></p>
          </form>
        </div>
      </div>
      <script>
        (function(){
          const tabs = document.querySelectorAll('.nsmg-tab');
          tabs.forEach(t => t.addEventListener('click', () => {
            tabs.forEach(x=>x.classList.remove('is-active'));
            t.classList.add('is-active');
            document.querySelectorAll('.nsmg-pane').forEach(p=>p.style.display='none');
            const target = document.querySelector(t.dataset.target);
            if (target) target.style.display='block';
          }));
        })();
      </script>
    <?php
    // Handle POST
    if (!empty($_POST['nsmg_auth_action']) && isset($_POST['nsmg_auth_nonce']) && wp_verify_nonce($_POST['nsmg_auth_nonce'],'nsmg_auth')) {
      if ($_POST['nsmg_auth_action']==='login') {
        $creds = [
          'user_login' => sanitize_text_field($_POST['user_login']),
          'user_password' => (string)$_POST['user_pass'],
          'remember' => true,
        ];
        $u = wp_signon($creds, false);
        if (!is_wp_error($u)) wp_redirect(add_query_arg('loggedin','1',home_url($_SERVER['REQUEST_URI']))); else echo '<div class="nsmg-error">'.esc_html($u->get_error_message()).'</div>';
      } else {
        $email = sanitize_email($_POST['user_email']);
        $pass  = (string)$_POST['user_pass'];
        $name  = sanitize_text_field($_POST['display_name']);
        if (!username_exists($email) && !email_exists($email)) {
          $uid = wp_create_user($email, $pass, $email);
          if (!is_wp_error($uid)) {
            wp_update_user(['ID'=>$uid,'display_name'=>$name,'role'=>'nsmg_parent']);
            wp_set_current_user($uid); wp_set_auth_cookie($uid);
            wp_redirect(add_query_arg('registered','1',home_url($_SERVER['REQUEST_URI'])));
          } else echo '<div class="nsmg-error">'.esc_html($uid->get_error_message()).'</div>';
        } else echo '<div class="nsmg-error">Account already exists. Please log in.</div>';
      }
      exit;
    }
    return ob_get_clean();
  }

  public static function sc_parent_dashboard($atts=[]) {
    if (!is_user_logged_in()) return '<div class="nsmg-alert">Please log in to manage children.</div>';
    $u = wp_get_current_user();

    // Handle create/edit/delete via simple POSTs
    if (isset($_POST['nsmg_child_nonce']) && wp_verify_nonce($_POST['nsmg_child_nonce'],'nsmg_child_save')) {
      if (!empty($_POST['nsmg_child_action']) && $_POST['nsmg_child_action']==='create') {
        $child_id = wp_insert_post([
          'post_type' => 'nsmg_child',
          'post_title' => sanitize_text_field($_POST['child_name']),
          'post_status' => 'publish',
          'post_author' => $u->ID,
        ]);
        if ($child_id && !is_wp_error($child_id)) {
          if (!empty($_POST['child_grade'])) wp_set_object_terms($child_id, array_map('intval', (array)$_POST['child_grade']), 'nsmg_grade', false);
          if (!empty($_POST['child_avatar'])) update_post_meta($child_id,'_nsmg_child_avatar', esc_url_raw($_POST['child_avatar']));
          if (!empty($_POST['child_dob'])) update_post_meta($child_id,'_nsmg_child_dob', sanitize_text_field($_POST['child_dob']));
        }
      } elseif (!empty($_POST['nsmg_child_action']) && $_POST['nsmg_child_action']==='delete' && !empty($_POST['child_id'])) {
        $cid = absint($_POST['child_id']);
        $post = get_post($cid);
        if ($post && (int)$post->post_author === (int)$u->ID) {
          wp_trash_post($cid);
        }
      }
      wp_safe_redirect(remove_query_arg(['_wpnonce','nsmg_child_action'])); exit;
    }

    // Fetch children for this parent
    $children = get_posts([
      'post_type' => 'nsmg_child',
      'author' => $u->ID,
      'posts_per_page' => -1,
      'orderby' => 'date',
      'order' => 'DESC',
    ]);

    ob_start(); ?>
      <div class="nsmg-parent-dash">
        <h3><?php echo esc_html(sprintf(__('Welcome, %s','nsmg'), $u->display_name)); ?></h3>

        <div class="nsmg-grid-children">
          <?php if ($children): foreach ($children as $c):
            $avatar = get_post_meta($c->ID,'_nsmg_child_avatar', true);
            $grades = wp_get_post_terms($c->ID,'nsmg_grade',['fields'=>'names']);
          ?>
          <div class="nsmg-child-card">
            <div class="nsmg-child-avatar">
              <?php if ($avatar): ?>
                <img src="<?php echo esc_url($avatar); ?>" alt="">
              <?php else: ?>
                <div class="nsmg-avatar-fallback"><?php echo esc_html(strtoupper(mb_substr($c->post_title,0,1))); ?></div>
              <?php endif; ?>
            </div>
            <div class="nsmg-child-meta">
              <div class="nsmg-child-name"><?php echo esc_html($c->post_title); ?></div>
              <div class="nsmg-child-grade"><?php echo esc_html( $grades ? implode(', ',$grades) : __('No grade','nsmg') ); ?></div>
            </div>
            <div class="nsmg-child-actions">
              <a class="button" href="<?php echo esc_url( add_query_arg(['nsmg_play_as'=>$c->ID], site_url('/')) ); ?>"><?php _e('Play as this child','nsmg'); ?></a>
              <form method="post" onsubmit="return confirm('Delete this child?');" style="display:inline;">
                <?php wp_nonce_field('nsmg_child_save','nsmg_child_nonce'); ?>
                <input type="hidden" name="nsmg_child_action" value="delete"/>
                <input type="hidden" name="child_id" value="<?php echo esc_attr($c->ID); ?>"/>
                <button class="button button-link-delete" type="submit"><?php _e('Delete','nsmg'); ?></button>
              </form>
            </div>
          </div>
          <?php endforeach; else: ?>
            <p><?php _e('No children yet. Create one below.','nsmg'); ?></p>
          <?php endif; ?>
        </div>

        <hr/>
        <h4><?php _e('Add a Child','nsmg'); ?></h4>
        <form method="post" class="nsmg-form">
          <?php wp_nonce_field('nsmg_child_save','nsmg_child_nonce'); ?>
          <input type="hidden" name="nsmg_child_action" value="create"/>
          <p><label><?php _e('Child Name','nsmg'); ?><br/>
            <input type="text" name="child_name" required></label></p>
          <p><label><?php _e('Grade','nsmg'); ?><br/>
            <?php
              wp_dropdown_categories([
                'taxonomy' => 'nsmg_grade',
                'hide_empty' => false,
                'name' => 'child_grade[]',
                'id' => 'nsmg-child-grade',
                'show_option_none' => __('Select grade','nsmg'),
                'class' => 'postform',
              ]);
            ?>
          </label></p>
          <p><label><?php _e('Avatar URL (optional)','nsmg'); ?><br/>
            <input type="url" name="child_avatar" placeholder="https://…/avatar.png"></label></p>
          <p><label><?php _e('DOB (optional)','nsmg'); ?><br/>
            <input type="date" name="child_dob"></label></p>
          <p><button class="button button-primary"><?php _e('Create Child','nsmg'); ?></button></p>
        </form>
      </div>
    <?php
    return ob_get_clean();
  }

  /* ---------- REST: /children + extend /scores ---------- */
  public static function register_rest() {
    register_rest_route('nsmg/v1','/children', [
      'methods' => 'GET',
      'callback' => [__CLASS__, 'rest_children_list'],
      'permission_callback' => function(){ return is_user_logged_in(); }
    ]);
    register_rest_route('nsmg/v1','/children', [
      'methods' => 'POST',
      'callback' => [__CLASS__, 'rest_children_create'],
      'permission_callback' => function(){ return is_user_logged_in(); }
    ]);
    register_rest_route('nsmg/v1','/children/(?P<id>\d+)', [
      'methods' => 'PATCH',
      'callback' => [__CLASS__, 'rest_children_update'],
      'permission_callback' => function(){ return is_user_logged_in(); }
    ]);
    register_rest_route('nsmg/v1','/children/(?P<id>\d+)', [
      'methods' => 'DELETE',
      'callback' => [__CLASS__, 'rest_children_delete'],
      'permission_callback' => function(){ return is_user_logged_in(); }
    ]);
  }

  private static function ensure_parent_owns($post_id) {
    $p = get_post($post_id);
    $u = wp_get_current_user();
    return $p && (int)$p->post_author === (int)$u->ID;
  }

  public static function rest_children_list(WP_REST_Request $req) {
    $u = wp_get_current_user();
    $items = get_posts([
      'post_type'=>'nsmg_child',
      'author'=>$u->ID,
      'posts_per_page'=>-1,
      'orderby'=>'date','order'=>'DESC'
    ]);
    $out = [];
    foreach ($items as $c) {
      $out[] = [
        'id' => $c->ID,
        'name' => $c->post_title,
        'avatar' => get_post_meta($c->ID,'_nsmg_child_avatar', true),
        'grades' => wp_get_post_terms($c->ID,'nsmg_grade',['fields'=>'ids']),
      ];
    }
    return rest_ensure_response($out);
  }

  public static function rest_children_create(WP_REST_Request $req) {
    $u = wp_get_current_user();
    $name = sanitize_text_field($req->get_param('name'));
    if (!$name) return new WP_Error('invalid','Name required', ['status'=>400]);

    $child_id = wp_insert_post([
      'post_type'=>'nsmg_child',
      'post_title'=>$name,
      'post_status'=>'publish',
      'post_author'=>$u->ID,
    ]);
    if (is_wp_error($child_id)) return $child_id;

    $avatar = esc_url_raw($req->get_param('avatar'));
    if ($avatar) update_post_meta($child_id,'_nsmg_child_avatar',$avatar);

    $grades = $req->get_param('grades');
    if (is_array($grades)) wp_set_object_terms($child_id, array_map('intval',$grades), 'nsmg_grade', false);

    return rest_ensure_response(['id'=>$child_id]);
  }

  public static function rest_children_update(WP_REST_Request $req) {
    $id = absint($req['id']);
    if (!$id || !self::ensure_parent_owns($id)) return new WP_Error('forbidden','Not allowed', ['status'=>403]);

    $updates = [];
    if ($req->get_param('name')) $updates['post_title'] = sanitize_text_field($req->get_param('name'));
    if ($updates) {
      $updates['ID'] = $id;
      $r = wp_update_post($updates, true);
      if (is_wp_error($r)) return $r;
    }
    if ($req->get_param('avatar')) update_post_meta($id,'_nsmg_child_avatar', esc_url_raw($req->get_param('avatar')));
    if (is_array($req->get_param('grades'))) wp_set_object_terms($id, array_map('intval',$req->get_param('grades')), 'nsmg_grade', false);

    return rest_ensure_response(['ok'=>true]);
  }

  public static function rest_children_delete(WP_REST_Request $req) {
    $id = absint($req['id']);
    if (!$id || !self::ensure_parent_owns($id)) return new WP_Error('forbidden','Not allowed', ['status'=>403]);
    wp_trash_post($id);
    return rest_ensure_response(['ok'=>true]);
  }
}

NSMG_Accounts::init();
