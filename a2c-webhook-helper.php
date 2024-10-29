<?php
/*
Plugin Name: API2Cart Webhook Helper
Description: The plugin adds extra webhook topics for WooCommerce.
Author: API2Cart
Version: 1.7
Author URI: https://api2cart.com/
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
*/

/*
API2Cart webhook helper is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

API2Cart webhook helper is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with API2Cart webhook helper. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/

if (!defined('ABSPATH')) {
  exit;
}

if (!defined('WEBHOOK_HELPER_VERSION')) {
  require_once 'app' . DIRECTORY_SEPARATOR . 'WH_Helper.php';
  require_once 'includes' . DIRECTORY_SEPARATOR . 'class-a2c-webhook-helper-rest-api-controller.php';

  define('WEBHOOK_HELPER_VERSION', WH_Helper::VERSION);

  function a2c_wh_init()
  {
    if (WEBHOOK_HELPER_VERSION !== get_option(WH_Helper::OPTION_NAME_VERSION)) {
      WH_Helper::activate();
    }

    WH_Helper::instance();
  }

  /**
   * Register routes.
   *
   * @since 1.6.0
   */
  function register_rest_api_routes_a2c_wh() {
    $restApiController = new A2C_Webhook_Helper_V1_REST_API_Controller();
    $restApiController->register_routes();
  }

  function wh_admin_notice()
  {
    if (!class_exists('woocommerce') || version_compare(WC()->version, 2.6, '<')) {
      echo '<div class="notice notice-warning is-dismissible"><p>' .
        esc_html('API2Cart Webhook Helper requires WooCommerce version 2.6+') . '</p></div>';
    }
  }

  add_action('admin_notices', 'wh_admin_notice');
  add_action('rest_api_init', 'register_rest_api_routes_a2c_wh' );
  add_action('plugins_loaded', 'a2c_wh_init', 20);

  register_activation_hook(__FILE__,  array('WH_Helper', 'activate'));
  register_deactivation_hook(__FILE__,  array('WH_Helper', 'deactivate'));
  register_uninstall_hook(__FILE__, array('WH_Helper', 'uninstall'));
}