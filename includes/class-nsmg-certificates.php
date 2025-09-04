<?php
if (!defined('ABSPATH')) exit;

/**
 * NSMG_Certificates: generate certificate PNG for a score
 * v1.0 (uses GD)
 * Route: GET /wp-json/nsmg/v1/cert/<score_id>.png
 */
class NSMG_Certificates {
  public static function init() {
    add_action('rest_api_init', [__CLASS__, 'register_rest']);
  }

  public static function register_rest() {
    register_rest_route('nsmg/v1','/cert/(?P<id>\d+)\.png', [
      'methods'=>'GET',
      'callback'=>[__CLASS__,'render_png'],
      'permission_callback'=>'__return_true'
    ]);
  }

  public static function render_png(WP_REST_Request $req) {
    $score_id = absint($req['id']);
    $p = get_post($score_id);
    if (!$p || $p->post_type !== 'nsmg_score') return new WP_Error('notfound','Score not found',['status'=>404]);

    $child_id = (int)get_post_meta($score_id,'_nsmg_child_id',true);
    $child    = $child_id ? get_the_title($child_id) : __('Guest','nsmg');
    $story_id = (int)get_post_meta($score_id,'_nsmg_story_id',true);
    $story    = $story_id ? get_the_title($story_id) : __('Story','nsmg');
    $points   = (int)get_post_meta($score_id,'_nsmg_points',true);
    $date     = get_post_meta($score_id,'_nsmg_date',true) ?: current_time('mysql');

    $bg_path = plugin_dir_path(__FILE__).'../assets/certificates/cert-template.png';
    if (!file_exists($bg_path)) return new WP_Error('cfg','Certificate template missing',['status'=>500]);

    $im = imagecreatefrompng($bg_path);
    if (!$im) return new WP_Error('gd','GD could not load template',['status'=>500]);

    $black = imagecolorallocate($im, 20, 24, 35);

    // Load a TTF font you include in /assets/fonts/
    $font_path = plugin_dir_path(__FILE__).'../assets/fonts/Inter-SemiBold.ttf';
    if (!file_exists($font_path)) {
      // fallback to built-in font
      imagestring($im, 5, 100, 100, "Install Inter-SemiBold.ttf", $black);
    }

    // Helper to center text
    $center = function($text, $y, $size=36) use ($im, $font_path, $black) {
      if (file_exists($font_path)) {
        $bbox = imagettfbbox($size, 0, $font_path, $text);
        $w = imagesx($im);
        $text_w = abs($bbox[4] - $bbox[0]);
        $x = (int)(($w - $text_w) / 2);
        imagettftext($im, $size, 0, $x, $y, $black, $font_path, $text);
      } else {
        // crude center fallback
        $x = max(10, (imagesx($im)/2) - (strlen($text)*4));
        imagestring($im, 5, (int)$x, (int)$y-14, $text, $black);
      }
    };

    // Compose lines
    $center('Certificate of Achievement', 260, 48);
    $center($child, 350, 40);
    $center('for completing: ' . $story, 410, 28);
    $center('Score: '.$points.'   Date: '.mysql2date('M d, Y', $date), 460, 24);
    $center('NutSpace Mini Games', 520, 22);

    // Output PNG
    header('Content-Type: image/png');
    imagepng($im);
    imagedestroy($im);
    exit;
  }
}
NSMG_Certificates::init();
