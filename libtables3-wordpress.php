<?php
/*
Plugin Name: Libtables3 Wordpress
Plugin URI: https://github.com/bartnv/libtables3-wordpress
Description: WordPress plugin for Libtables integration
Version: 3.0
Author: Bart Noordervliet
License: AGPL3
License URI: https://www.gnu.org/licenses/agpl-3.0.html
*/
/*
Copyright 2020  Bart Noordervliet

    Libtables Integration is free software: you can redistribute it and/or
    modify it under the terms of the GNU General Public License version 3.0 as
    published by the Free Software Foundation.

    Libtables Integration is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Libtables Integration.  If not, see
    <https://www.gnu.org/licenses/>.
*/

defined('ABSPATH') or die('No script kiddies please!');

class Libtables_Integration {
  public static function register_session() {
    if (!session_id()) session_start();
  }
  public static function handle_shortcode($atts) {
    require_once('libtables.php');
    if (empty($atts['block'])) return "No block in shortcode";
    ob_start();
    lt_print_block($atts['block']);
    return ob_get_clean();
  }
}

add_action('init', [ 'Libtables_Integration', 'register_session' ]);
add_shortcode('libtables', [ 'Libtables_Integration', 'handle_shortcode' ]);
wp_enqueue_style('libtables-css', '/wp-content/plugins/libtables3-wordpress/style.css');
wp_enqueue_script('libtables-js', '/wp-content/plugins/libtables3-wordpress/clientside.js', [ 'jquery' ]);
if (is_file(WP_PLUGIN_DIR . '/libtables3-wordpress/local.css')) wp_enqueue_style('libtables-local-css', WP_PLUGIN_URL . '/libtables3-wordpress/local.css');
if (is_file(WP_PLUGIN_DIR . '/libtables3-wordpress/local.js')) wp_enqueue_script('libtables-local-js', WP_PLUGIN_URL . '/libtables3-wordpress/local.js');
