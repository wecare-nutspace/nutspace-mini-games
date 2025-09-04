<?php
if (!defined('ABSPATH')) exit;

/**
 * NSMG_Scores: store scores + REST (submit + leaderboards)
 * v1.0.0
 *
 * CPT: nsmg_score
 * Meta:
 *  - _nsmg_child_id   (int|null)
 *  - _nsmg_grade_id   (int, taxonomy term ID)
 *  - _nsmg_story_id   (int, nsmg_story post ID)
 *  - _nsmg_points     (int)
 *  - _nsmg_duration   (int, seconds)
 *  - _nsmg_date       (mysql datetime string)
 */
class NSMG_Scores {

  public static function init() {
    add_action('init', [__CLASS__, 'register_cpt']);
    add_action('rest_api_init', [__CLASS__, 'register_rest']);
  }

  /* ---------------- CPT ---------------- */
  public static function register_cpt() {
    register_post_type('nsmg_score', [
      'labels' => [
        'name'          => __('Scores','nsmg'),
        'singular_name' => __('Score','nsmg'),
      ],
      'public'        => false,
      'show_ui'       => true,            // visible to admins for debugging
      'show_in_menu'  => 'nsmg_root',
      'supports'      => ['title','author'],
      'map_meta_cap'  => true,
    ]);
  }

  /* ---------------- REST ---------------- */
  public static function register_rest() {
    // Submit score
    register_rest_route('nsmg/v1','/score', [
      'methods'             => 'POST',
      'callback'            => [__CLASS__,'rest_submit'],
      'permission_callback' => '__return_true', // allow guests; tie to child if provided
    ]);

    // Leaderboard by grade
    register_rest_route('nsmg/v1','/leaderboard/grade', [
      'methods'             => 'GET',
      'callback'            => [__CLASS__,'rest_leaderboard_grade'],
      'permission_callback' => '__return_true',
    ]);

    // Leaderboard by child
    register_rest_route('nsmg/v1','/leaderboard/child', [
      'methods'             => 'GET',
      'callback'            => [__CLASS__,'rest_leaderboard_child'],
      'permission_callback' => '__return_true',
    ]);
  }

  /* ---------------- Handlers ---------------- */
  public static function rest_submit(WP_REST_Request $req) {
    $child_id   = absint($req->get_param('child_id'));      // optional
    $grade_id   = absint($req->get_param('grade_id'));      // required
    $story_id   = absint($req->get_param('story_id'));      // required
    $points     = intval($req->get_param('points'));        // required
    $duration_s = max(0, intval($req->get_param('duration_s')));

    if (!$grade_id || !$story_id) {
      return new WP_Error('invalid','grade_id and story_id required', ['status'=>400]);
    }

    // Title is just for backend readability
    $title  = sprintf('Story #%d — %d pts', $story_id, $points);
    $author = get_current_user_id() ?: 0;

    $score_id = wp_insert_post([
      'post_type'   => 'nsmg_score',
      'post_status' => 'publish',
      'post_title'  => $title,
      'post_author' => $author,
    ], true);
    if (is_wp_error($score_id)) return $score_id;

    update_post_meta($score_id,'_nsmg_child_id',$child_id ?: 0);
    update_post_meta($score_id,'_nsmg_grade_id',$grade_id);
    update_post_meta($score_id,'_nsmg_story_id',$story_id);
    update_post_meta($score_id,'_nsmg_points',$points);
    update_post_meta($score_id,'_nsmg_duration',$duration_s);
    update_post_meta($score_id,'_nsmg_date', current_time('mysql'));

    return rest_ensure_response(['id'=>$score_id,'ok'=>true]);
  }

  public static function rest_leaderboard_grade(WP_REST_Request $req) {
    $grade_id = absint($req->get_param('grade_id'));
    $limit    = min(100, max(1, intval($req->get_param('limit') ?: 20)));

    if (!$grade_id) return new WP_Error('invalid','grade_id required',['status'=>400]);

    $posts = get_posts([
      'post_type'      => 'nsmg_score',
      'posts_per_page' => $limit,
      'meta_key'       => '_nsmg_points',
      'orderby'        => 'meta_value_num',
      'order'          => 'DESC',
      'meta_query'     => [
        ['key'=>'_nsmg_grade_id','value'=>$grade_id,'compare'=>'='],
      ],
    ]);

    $out = [];
    foreach ($posts as $p) {
      $child_id   = (int) get_post_meta($p->ID,'_nsmg_child_id',true);
      $child_name = $child_id ? get_the_title($child_id) : __('Guest','nsmg');
      $out[] = [
        'score_id' => $p->ID,
        'points'   => (int) get_post_meta($p->ID,'_nsmg_points',true),
        'duration' => (int) get_post_meta($p->ID,'_nsmg_duration',true),
        'child_id' => $child_id,
        'child'    => $child_name,
        'story_id' => (int) get_post_meta($p->ID,'_nsmg_story_id',true),
        'date'     => get_post_meta($p->ID,'_nsmg_date',true),
      ];
    }
    return rest_ensure_response($out);
  }

  public static function rest_leaderboard_child(WP_REST_Request $req) {
    $child_id = absint($req->get_param('child_id'));
    $limit    = min(100, max(1, intval($req->get_param('limit') ?: 20)));

    if (!$child_id) return new WP_Error('invalid','child_id required',['status'=>400]);

    $posts = get_posts([
      'post_type'      => 'nsmg_score',
      'posts_per_page' => $limit,
      'meta_key'       => '_nsmg_points',
      'orderby'        => 'meta_value_num',
      'order'          => 'DESC',
      'meta_query'     => [
        ['key'=>'_nsmg_child_id','value'=>$child_id,'compare'=>'='],
      ],
    ]);

    $out = [];
    foreach ($posts as $p) {
      $out[] = [
        'score_id' => $p->ID,
        'points'   => (int) get_post_meta($p->ID,'_nsmg_points',true),
        'duration' => (int) get_post_meta($p->ID,'_nsmg_duration',true),
        'story_id' => (int) get_post_meta($p->ID,'_nsmg_story_id',true),
        'date'     => get_post_meta($p->ID,'_nsmg_date',true),
      ];
    }
    return rest_ensure_response($out);
  }
}

NSMG_Scores::init();
