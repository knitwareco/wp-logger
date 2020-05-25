<?php
/**
 * @author Ben Ward
 * @copyright (c) 2020 Knitware
 * @link https://knitware.co
 */
namespace Knitware\WordPress;

/**
 * Manages a hidden custom post type for logging messages within WordPress plugins
 */
class Logger {

  private $label;
  private $post_type;
  private $post_type_tax;

  // TODO: Maybe take '$name' instead of $post_type and derive that — could handle
  //       the WordPress length limit automatically.
  public function __construct(string $post_type, string $label = "Plugin Log") {
    add_action('init', array($this, 'register'));

    $this->label = $label;
    $this->post_type = $post_type;
    $this->post_type_tax = $post_type . "_level";

    add_action("manage_{$post_type}_posts_columns", array($this, 'admin_columns'));
    add_action("manage_{$post_type}_posts_custom_column", array($this, 'admin_column_content'));
    add_filter("manage_edit-{$post_type}_sortable_columns", array($this, 'admin_sortable_columns'));
  }

  public function register() {
    register_post_type($this->post_type, array(
      'label' => $this->label,
      'public' => false,
      'show_ui' => true,
      'show_in_menu' => false,
      // 'show_in_nav_menu' => true,
      // 'capability_type' => 'post',
      // 'capabilities' => array('read_post'),
      'supports' => array('title', 'editor'),
      'taxonomies' => array($this->post_type_tax)
    ));

    register_taxonomy($this->post_type_tax, $this->post_type, array(
      'label' => $this->label . " Log Level",
      'public' => 'false'
    ));
  }

  public function admin_columns() {
    return array(
      'timestamp' => 'Timestamp',
      'log' => 'Log',
      'level' => 'Level',
      'content' => 'Detail'
    );
  }

  public function admin_sortable_columns($columns) {
    $columns['timestamp'] = 'date';
    $columns['log'] = 'post_title';
    return $columns;
  }

  public function admin_column_content($column = '') {
    if ($column == 'timestamp') {
      $page = get_post();
      echo $page->post_date;
      return;
    }

    if ($column == 'log') {
      $page = get_post();
      echo $page->post_title;
      return;
    }

    if ($column == 'level') {
      // get the log level  based on its post_id
      $page = get_post();
      if ($page) {
        $levels = wp_get_object_terms($page->ID, $this->post_type_tax);
        if (count($levels)) {
          echo implode(' ', array_map(function ($level) {
            return '<span class="button disabled">' . $level->name . '</span>';
          }, $levels));
        }
      }
    }

    if ($column == 'content') {
      // get the page based on its post_id
      $page = get_post();
      if ($page) {
       echo apply_filters('the_content', $page->post_content);
      }
    }
  }

  // TODO: Make the $level an enum rather than strings
  public function log(string $title, string $message = '', array $lines = array(), string $level = "notice") {
    if ('debug' == $level && !WP_DEBUG) {
      return;
    }

    $formatted_message = sprintf('<p>%s</p>', htmlentities($message));

    if (count($lines)) {
      $formatted_lines = sprintf('<pre><code>%s</code></pre>', implode('<br>', $lines));
    } else {
      $formatted_lines = '';
    }

    $result = wp_insert_post(array(
      'post_title' => $title,
      'post_content' => $formatted_message . $formatted_lines,
      'post_status' => 'private',
      'post_type' => $this->post_type,
    ));
    $tagged = wp_set_post_terms($result , array($level), $this->post_type_tax, false);

    return $result;
  }
}
