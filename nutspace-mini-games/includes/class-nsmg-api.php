<?php
if (!defined('ABSPATH')) exit;

/**
 * NSMG_API – simple REST for grades & stories
 * v0.9.1
 *
 * Routes:
 *   GET  /wp-json/nutspace/v1/grades
 *   GET  /wp-json/nutspace/v1/stories?grade=slug[,slug2]
 */
class NSMG_API {

  public static function init(){
    add_action('rest_api_init', [__CLASS__, 'register_routes']);
  }

  public static function register_routes(){

    register_rest_route('nutspace/v1', '/grades', [
      'methods'  => 'GET',
      'callback' => [__CLASS__, 'get_grades'],
      'permission_callback' => '__return_true',
    ]);

    register_rest_route('nutspace/v1', '/stories', [
      'methods'  => 'GET',
      'callback' => [__CLASS__, 'get_stories'],
      'permission_callback' => '__return_true',
      'args' => [
        'grade' => [
          'description' => 'Comma separated grade slugs (taxonomy nsmg_grade).',
          'required'    => false,
          'type'        => 'string',
        ],
        'limit' => [
          'description' => 'Max stories to return',
          'required'    => false,
          'type'        => 'integer',
          'default'     => 20,
        ],
      ],
    ]);
  }

  /** GET /grades – list grade terms (taxonomy: nsmg_grade) */
  public static function get_grades(WP_REST_Request $req){
    $terms = get_terms([
      'taxonomy'   => 'nsmg_grade',
      'hide_empty' => false,
      'orderby'    => 'name',
      'order'      => 'ASC',
    ]);
    if (is_wp_error($terms)) {
      return new WP_REST_Response(['ok'=>false,'error'=>$terms->get_error_message()], 500);
    }
    $out = array_map(function($t){
      return [
        'id'   => $t->term_id,
        'name' => $t->name,
        'slug' => $t->slug,
      ];
    }, $terms);
    return new WP_REST_Response(['ok'=>true,'grades'=>$out], 200);
  }

  /** GET /stories?grade=slug[,slug2] – stories filtered by Grades */
  public static function get_stories(WP_REST_Request $req){
    $limit = max(1, min(50, intval($req->get_param('limit') ?: 20)));
    $grade = trim((string)$req->get_param('grade') ?: '');

    $tax_query = [];
    if ($grade !== '') {
      $slugs = array_filter(array_map('trim', explode(',', $grade)));
      if ($slugs) {
        $tax_query[] = [
          'taxonomy' => 'nsmg_grade',
          'field'    => 'slug',
          'terms'    => $slugs,
          'operator' => 'IN', // story can match any of the selected grades
        ];
      }
    }

    $q = new WP_Query([
      'post_type'      => 'nsmg_story',
      'post_status'    => 'publish',
      'posts_per_page' => $limit,
      'tax_query'      => $tax_query ?: null,
      'orderby'        => 'date',
      'order'          => 'DESC',
      'no_found_rows'  => true,
      'fields'         => 'ids',
    ]);

    $stories = [];
    foreach ($q->posts as $post_id){
      $title   = get_the_title($post_id);
      $audio   = get_post_meta($post_id, '_nsmg_audio', true);
      $imagesC = get_post_meta($post_id, '_nsmg_images', true); // CSV URLs
      $orderC  = get_post_meta($post_id, '_nsmg_order', true);  // CSV filenames

      $images = array_values(array_filter(array_map('trim', explode(',', (string)$imagesC))));
      $order  = array_values(array_filter(array_map('trim', explode(',', (string)$orderC))));

      $stories[] = [
        'id'           => $post_id,
        'title'        => $title,
        'audio'        => $audio,
        'images'       => $images,
        'correctOrder' => $order,
        'grades'       => wp_get_post_terms($post_id, 'nsmg_grade', ['fields'=>'slugs']),
      ];
    }

    return new WP_REST_Response(['ok'=>true,'stories'=>$stories], 200);
  }
}

NSMG_API::init();