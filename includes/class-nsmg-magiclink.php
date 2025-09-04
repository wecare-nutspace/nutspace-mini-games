<?php
if (!defined('ABSPATH')) exit;

/**
 * NSMG_MagicLink: passwordless login via one-time tokens
 * v1.0
 *
 * Uses usermeta _nsmg_magic_token (hash) + _nsmg_magic_expires (ts).
 */
class NSMG_MagicLink {
  const EXP_MINUTES = 20; // link valid for 20 minutes

  public static function init() {
    add_action('rest_api_init', [__CLASS__, 'register_rest']);
    add_shortcode('nsmg_magic_login', [__CLASS__, 'shortcode_form']); // optional, can be folded into your existing [nsmg_auth]
    add_action('init', [__CLASS__, 'maybe_consume_magiclink']);
  }

  /* ---------- Shortcode: Request link ---------- */
  public static function shortcode_form($atts=[]) {
    if (is_user_logged_in()) return '<div class="nsmg-alert">You are already logged in.</div>';

    // Handle submit
    if (!empty($_POST['nsmg_magic_email']) && isset($_POST['nsmg_magic_nonce']) && wp_verify_nonce($_POST['nsmg_magic_nonce'],'nsmg_magic_req')) {
      $email = sanitize_email($_POST['nsmg_magic_email']);
      $user  = get_user_by('email',$email);
      if ($user) {
        self::issue_token_and_email($user->ID, $email);
        return '<div class="nsmg-card">We’ve sent a login link to <strong>'.esc_html($email).'</strong>. Check your inbox.</div>';
      } else {
        return '<div class="nsmg-error">No account found for that email.</div>';
      }
    }

    ob_start(); ?>
      <form method="post" class="nsmg-card" style="max-width:420px">
        <h3>Login via Magic Link</h3>
        <?php wp_nonce_field('nsmg_magic_req','nsmg_magic_nonce'); ?>
        <p><label>Email<br/><input type="email" name="nsmg_magic_email" required></label></p>
        <p><button class="button button-primary">Send me a login link</button></p>
        <p class="description">We’ll email a one-time link (valid <?php echo esc_html(self::EXP_MINUTES); ?> minutes).</p>
      </form>
    <?php
    return ob_get_clean();
  }

  /* ---------- REST (optional if you want AJAX) ---------- */
  public static function register_rest() {
    register_rest_route('nsmg/v1','/magiclink', [
      'methods'  => 'POST',
      'callback' => function(WP_REST_Request $req){
        $email = sanitize_email($req->get_param('email'));
        if (!$email) return new WP_Error('invalid','Email required',['status'=>400]);
        $user = get_user_by('email',$email);
        if (!$user) return new WP_Error('notfound','No user for that email',['status'=>404]);
        self::issue_token_and_email($user->ID, $email);
        return rest_ensure_response(['ok'=>true]);
      },
      'permission_callback' => '__return_true'
    ]);
  }

  private static function issue_token_and_email($user_id, $email) {
    $token   = wp_generate_password(48, false, false);
    $hash    = wp_hash_password($token);
    $expires = time() + (self::EXP_MINUTES * 60);

    update_user_meta($user_id, '_nsmg_magic_token', $hash);
    update_user_meta($user_id, '_nsmg_magic_expires', $expires);

    $url = add_query_arg([
      'nsmg_magic' => rawurlencode($token),
      'uid'        => $user_id,
      'redirect'   => rawurlencode(home_url('/'))
    ], home_url('/'));

    $subject = 'Your NutSpace login link';
    $body    = "Hi,\n\nClick to log in:\n$url\n\nThis link expires in ".self::EXP_MINUTES." minutes.";
    wp_mail($email, $subject, $body);
  }

  /* ---------- Consume link on front-end ---------- */
  public static function maybe_consume_magiclink() {
    if (empty($_GET['nsmg_magic']) || empty($_GET['uid'])) return;

    $token   = (string)$_GET['nsmg_magic'];
    $uid     = absint($_GET['uid']);
    $user    = get_user_by('id', $uid);
    if (!$user) return;

    $hash    = get_user_meta($uid, '_nsmg_magic_token', true);
    $expires = (int) get_user_meta($uid, '_nsmg_magic_expires', true);

    if (!$hash || !$expires || time() > $expires) {
      wp_die(__('This login link has expired. Please request a new one.','nsmg'));
    }

    if (!wp_check_password($token, $hash, $uid)) {
      wp_die(__('Invalid login link. Please request a new one.','nsmg'));
    }

    // Success: clear token and log them in
    delete_user_meta($uid, '_nsmg_magic_token');
    delete_user_meta($uid, '_nsmg_magic_expires');

    wp_set_current_user($uid);
    wp_set_auth_cookie($uid, true);

    // Ensure parent role at minimum
    if (!in_array('nsmg_parent', $user->roles, true)) {
      $user->add_role('nsmg_parent');
    }

    $redir = !empty($_GET['redirect']) ? esc_url_raw($_GET['redirect']) : home_url('/');
    wp_safe_redirect($redir);
    exit;
  }
}

NSMG_MagicLink::init();
