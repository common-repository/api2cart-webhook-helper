<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * REST API controller.
 *
 * @since 1.6.0
 */
class A2C_Webhook_Helper_V1_REST_API_Controller extends WP_REST_Controller {

  /**
   * Endpoint namespace.
   *
   * @var string
   */
  protected $namespace = 'wc-a2c/v1';

  /**
   * Basket route.
   *
   * @var string
   */
  protected $basket_endpoint = 'basket';

  /**
   * Abandoned order route.
   *
   * @var string
   */
  protected $abandoned_order_endpoint = 'abandoned-order';


  /**
   * Cart meta_key slug.
   *
   * @var string
   */
  protected $cart_meta_key_slug;

  /**
   * Register the routes.
   */
  public function register_routes() {
    register_rest_route( $this->namespace, '/' . $this->basket_endpoint . '/(?P<id>[A-Fa-f0-9]{0,32})', array(
        array(
          'methods'             => WP_REST_Server::READABLE,
          'callback'            => array( $this, 'get_basket' ),
          'permission_callback' => array( $this, 'get_items_permissions_check' ),
          'args'                => array(
            'context' => $this->get_context_param( array( 'default' => 'view' ) ),
          )
        ),
        array(
          'methods'             => WP_REST_Server::EDITABLE,
          'callback'            => array( $this, 'update_basket' ),
          'permission_callback' => array( $this, 'edit_item_permissions_check' ),
          'args'                => array_merge( $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ), array(
              'cart' => array(
                'required' => true
              )
            )
          )
        )
      )
    );
    register_rest_route( $this->namespace, '/customer/(?P<id>[0-9]{0,32})/' . $this->basket_endpoint, array(
        array(
          'methods'             => WP_REST_Server::READABLE,
          'callback'            => array( $this, 'get_basket_by_customer_id' ),
          'permission_callback' => array( $this, 'get_items_permissions_check' ),
          'args'                => array(
            'context' => $this->get_context_param( array( 'default' => 'view' ) ),
          )
        ),
        array(
          'methods'             => WP_REST_Server::CREATABLE,
          'callback'            => array( $this, 'create_basket' ),
          'permission_callback' => array( $this, 'edit_item_permissions_check' )
        )
      )
    );
    register_rest_route( $this->namespace, '/customer/(?P<id>[0-9]{0,32})/session', array(
        array(
          'methods'             => WP_REST_Server::READABLE,
          'callback'            => array( $this, 'get_session' ),
          'permission_callback' => array( $this, 'get_items_permissions_check' ),
          'args'                => array(
            'context' => $this->get_context_param( array( 'default' => 'view' ) ),
          )
        )
      )
    );
    register_rest_route( $this->namespace, '/' . $this->abandoned_order_endpoint, array(
        array(
          'methods'             => WP_REST_Server::READABLE,
          'callback'            => array( $this, 'get_abandoned_items' ),
          'permission_callback' => array( $this, 'get_items_permissions_check' )
        )
      )
    );
  }

  /**
   * Check whether a given request has permission to read order live shipping service.
   *
   * @param  WP_REST_Request $request Full details about the request.
   * @return WP_Error|boolean
   */
  public function get_items_permissions_check( $request ) {
    if ( ! wc_rest_check_user_permissions() ) {
      return new WP_Error( 'woocommerce_rest_cannot_view', __( 'Sorry, you cannot list resources.', $this->basket_endpoint ), array( 'status' => rest_authorization_required_code() ) );
    }

    return true;
  }

  /**
   * Check whether a given request has permission to create live shipping service.
   *
   * @param  WP_REST_Request $request Full details about the request.
   * @return WP_Error|boolean
   */
  public function edit_item_permissions_check( $request ) {
    if ( ! wc_rest_check_user_permissions( 'edit' ) ) {
      return new WP_Error( 'woocommerce_rest_cannot_view', __( 'Sorry, you cannot list resources.', $this->basket_endpoint ), array( 'status' => rest_authorization_required_code() ) );
    }

    return true;
  }

  /**
   * Get a single basket.
   *
   * @param WP_REST_Request $request Full details about the request.
   * @return WP_Error|WP_REST_Response
   */
  public function get_basket( $request ) {
    $basketId = $request['id'];
    $data = array();
    $whMetaKeySlug = '_a2c_wh_cart_' . get_current_blog_id() . '_';

    $userData = get_users(
      array(
        'meta_key'   => $whMetaKeySlug . 'id',
        'meta_value' => $basketId
      )
    );

    if ( ! empty( $userData ) ) {
      $userData = reset( $userData );
      $userMeta = get_user_meta( $userData->data->ID );
      $productData = array();

      if ( ! empty( $userMeta[$this->get_cart_meta_key_slug()][0] ) ) {
        $cartData = unserialize( $userMeta[$this->get_cart_meta_key_slug()][0] );

        if ( ! empty( $cartData['cart'] ) ) {
          foreach ( $cartData['cart'] as $item ) {
            if ( $item['variation_id'] !== 0 ) {
              $productData[$item['variation_id']]['data'] = get_post( $item['variation_id'] );
              $productData[$item['variation_id']]['meta_data'] = get_post_meta( $item['variation_id'] );
            } else {
              $productData[$item['product_id']]['data'] = get_post( $item['product_id'] );
              $productData[$item['product_id']]['meta_data'] = get_post_meta( $item['product_id'] );
            }
          }
        }

        $data = array(
          'user_id'      => $userData->data->ID,
          'first_name'   => $userMeta['first_name'][0],
          'last_name'    => $userMeta['last_name'][0],
          'user_email'   => $userData->data->user_email,
          'basket_id'    => $basketId,
          'created_gmt'  => $userMeta[$whMetaKeySlug . 'created_gmt'][0],
          'updated_gmt'  => $userMeta[$whMetaKeySlug . 'updated_gmt'][0],
          'basket_url'   => get_option('woocommerce_cart_page_id'),
          'basket_items' => $cartData,
          'product_data' => $productData
        );
      }
    }

    return rest_ensure_response( $data );
  }

  /**
   * Get a single basket by customer id.
   *
   * @param WP_REST_Request $request Full details about the request.
   * @return WP_Error|WP_REST_Response
   */
  public function get_basket_by_customer_id( $request ) {
    $customerId = $request['id'];
    $data = array();
    $whMetaKeySlug = '_a2c_wh_cart_' . get_current_blog_id() . '_';
    $cartMetaKey = $this->get_cart_meta_key_slug();
    $user = get_user_by( 'id', $customerId );

    if ( ! empty( $user ) ) {
      $userMeta = get_user_meta( $user->data->ID );
      $productData = array();

      if ( ! empty( $userMeta[$cartMetaKey] ) ) {
        $cartData = unserialize( $userMeta[$this->get_cart_meta_key_slug()][0] );

        if ( !empty( $cartData['cart'] ) ) {
          foreach( $cartData['cart'] as $item ) {
            if ( $item['variation_id'] !== 0 ) {
              $productData[$item['variation_id']]['data'] = get_post( $item['variation_id'] );
              $productData[$item['variation_id']]['meta_data'] = get_post_meta( $item['variation_id'] );
            } else {
              $productData[$item['product_id']]['data'] = get_post( $item['product_id'] );
              $productData[$item['product_id']]['meta_data'] = get_post_meta( $item['product_id'] );
            }
          }
        }

        $data = array(
          'user_id'    	 => $user->data->ID,
          'first_name' 	 => $userMeta['first_name'][0],
          'last_name'  	 => $userMeta['last_name'][0],
          'user_email' 	 => $user->data->user_email,
          'basket_id'  	 => $userMeta[$whMetaKeySlug . 'id'][0],
          'created_gmt'  => $userMeta[$whMetaKeySlug . 'created_gmt'][0],
          'updated_gmt'  => $userMeta[$whMetaKeySlug . 'updated_gmt'][0],
          'basket_url'   => get_option( 'woocommerce_cart_page_id' ),
          'basket_items' => $cartData,
          'product_data' => $productData
        );
      }
    }

    return rest_ensure_response( $data );
  }

  /**
   * Update basket.
   *
   * @param WP_REST_Request $request Full details about the request.
   * @return WP_Error|WP_REST_Response
   */
  public function update_basket( $request ) {
    global $wpdb;
    $whMetaKeySlug = '_a2c_wh_cart_' . get_current_blog_id() . '_';
    $cartData = $request['cart'];
    $basketId = $request['id'];
    $sessionData = $request['session_data'];
    $userData = get_users(
      array(
        'meta_key'   => $whMetaKeySlug . 'id',
        'meta_value' => $basketId
      )
    );

    if ( empty( $userData ) ) {
      return new WP_Error( 'woocommerce_rest_entity_not_found', __( 'Basket with ID \'' . $basketId . '\' not found.', $this->basket_endpoint ), array( 'status' => 404 ) );
    }

    if ( empty( $cartData ) ) {
      return new WP_Error( 'woocommerce_rest_invalid_param', __( 'The value of parameter \'cart\' can not be empty.', $this->basket_endpoint ), array( 'status' => 400 ) );
    }

    $userData = reset( $userData );
    update_user_meta( $userData->data->ID, $this->get_cart_meta_key_slug(), unserialize( $cartData, array('allowed_classes' => array( 'stdClass') ) ) );
    update_user_meta( $userData->data->ID, $whMetaKeySlug . 'updated_gmt', time() );

    if ( ! empty( $this->get_sesion_data( $userData->data->ID )) && ! empty( $sessionData ) ) {
      $wpdb->update( "{$wpdb->prefix}woocommerce_sessions", array( 'session_value' => $sessionData ), array( 'session_key' => $userData->data->ID ) );
    }

    return rest_ensure_response( true );
  }

  /**
   * Create basket.
   *
   * @param WP_REST_Request $request Full details about the request.
   * @return WP_Error|WP_REST_Response
   */
  public function create_basket( $request ) {
    global $wpdb;
    $customerId = $request['id'];
    $whMetaKeySlug = '_a2c_wh_cart_' . get_current_blog_id() . '_';
    $cartData = $request['cart'];
    $sessionData = $request['session_data'];
    $userData = get_user_by( 'id', $customerId );

    if ( empty( $userData ) ) {
      return new WP_Error( 'woocommerce_rest_entity_not_found', __( 'User with ID \'' . $customerId . '\' not found.', $this->basket_endpoint ), array( 'status' => 404 ) );
    }

    if ( empty( $cartData ) ) {
      return new WP_Error( 'woocommerce_rest_invalid_param', __( 'The value of parameter \'cart\' can not be empty.', $this->basket_endpoint ), array( 'status' => 400 ) );
    }

    $userData = reset( $userData );
    $userMeta = get_user_meta( $userData->ID );
    $time = time();
    update_user_meta( $userData->ID, $this->get_cart_meta_key_slug(), unserialize( $cartData, array('allowed_classes' => array( 'stdClass') ) ) );
    update_user_meta( $userData->ID, $whMetaKeySlug . 'updated_gmt', $time );

    if ( ! isset( $userMeta[$whMetaKeySlug . 'created_gmt'] ) ) {
      update_user_meta( $userData->ID, $whMetaKeySlug . 'created_gmt', $time );
    }

    if ( ! isset( $userMeta[$whMetaKeySlug . 'id'] ) ) {
      update_user_meta( $userData->ID, $whMetaKeySlug . 'id', md5(microtime() . $userData->ID) );
    }

    if ( ! empty( $this->get_sesion_data( $userData->ID )) && ! empty( $sessionData ) ) {
      $wpdb->update( "{$wpdb->prefix}woocommerce_sessions", array( 'session_value' => $sessionData ), array( 'session_key' => $userData->ID ) );
    }

    return rest_ensure_response( true );
  }

  /**
   * Get user session data.
   *
   * @param WP_REST_Request $request Full details about the request.
   * @return WP_Error|WP_REST_Response
   */
  public function get_session ( $request ) {
    return rest_ensure_response( $this->get_sesion_data($request['id']) );
  }

  /**
   * Get session data.
   *
   * @param string $userId User id.
   * @return null|array
   */
  protected function get_sesion_data( $userId ) {
    global $wpdb;
    $res = null;
    $sessionData = $wpdb->get_var( "SELECT session_value FROM {$wpdb->prefix}woocommerce_sessions WHERE session_key = '{$userId}'" );

    if ( ! empty ( $sessionData ) ) {
      $res = unserialize($sessionData, array( 'allowed_classes' => array( 'stdClass' ) ) );
    }

    return $res;
  }

  /**
   * Get cart meta_key slug.
   *
   * @return string
   */
  protected function get_cart_meta_key_slug() {
    if ( ! empty( $this->cart_meta_key_slug ) ) {
      return $this->cart_meta_key_slug;
    }

    if (version_compare(WooCommerce::instance()->version, '3.1.0', '>=')) {
      $this->cart_meta_key_slug = "_woocommerce_persistent_cart_" . get_current_blog_id();
    } else {
      $this->cart_meta_key_slug = '_woocommerce_persistent_cart';
    }

    return $this->cart_meta_key_slug;
  }

  /**
   * Get user session data.
   *
   * @param WP_REST_Request $request Full details about the request.
   * @return WP_Error|WP_REST_Response
   */
  public function get_abandoned_items( $request ) {
    global $wpdb;
    $abandonedOrders = array();
    $start = isset( $request['start'] ) ? $request['start'] : '0';
    $count = isset( $request['count'] ) ? $request['count'] : '10';
    $modifiedFrom = isset( $request['modified_from'] ) ? $request['modified_from'] : null;
    $modifiedTo = isset( $request['modified_to'] ) ? $request['modified_to'] : null;
    $createdFrom = isset( $request['created_from'] ) ? $request['created_from'] : null;
    $createdTo = isset( $request['created_to'] ) ? $request['created_to'] : null;
    $customerEmail = isset( $request['customer_email'] ) ? $request['customer_email'] : null;
    $customerId = isset( $request['customer_id'] ) ? $request['customer_id'] : null;
    $basketUrl = get_option( 'woocommerce_cart_page_id' );
    $whMetaKeySlug = '_a2c_wh_cart_' . get_current_blog_id() . '_';

    if ( ! is_numeric( $start ) ) {
      $start = '0';
    }

    if ( ! is_numeric( $count ) ) {
      $start = '10';
    }

    if ( ! empty( $modifiedFrom ) && ! is_numeric( $modifiedFrom ) ) {
      unset( $modifiedFrom );
    }

    if ( ! empty( $modifiedTo ) && ! is_numeric( $modifiedTo ) ) {
      unset( $modifiedTo );
    }

    if ( ! empty( $createdFrom ) && ! is_numeric( $createdFrom ) ) {
      unset( $createdFrom );
    }

    if ( ! empty( $createdTo ) && ! is_numeric( $createdTo ) ) {
      unset( $createdTo );
    }

    if ( ! empty( $customerEmail ) && ! filter_var( $customerEmail, FILTER_VALIDATE_EMAIL ) ) {
      unset( $customerEmail );
    }

    if ( ! empty( $customerEmail ) && ! is_numeric( $customerId ) ) {
      unset( $customerId );
    }

    $where = $join = '';

    if ( ! empty( $customerEmail ) ) {
      $where .= ' AND u.user_email = "' . $customerEmail . '"';
    }

    if ( ! empty( $customerId ) ) {
      $where .= ' AND um.user_id = ' . $customerId;
    }

    if ( ! empty( $modifiedFrom ) || ! empty( $modifiedTo ) ) {
      $join .= "
        LEFT JOIN {$wpdb->prefix}usermeta AS um1
          ON um1.user_id = u.ID AND um1.meta_key=\"{$whMetaKeySlug}updated_gmt\"";
    }

    if ( ! empty( $createdFrom ) || ! empty( $createdTo ) ) {
      $join .= "
        LEFT JOIN {$wpdb->prefix}usermeta AS um2
          ON um2.user_id = u.ID AND um2.meta_key=\"{$whMetaKeySlug}created_gmt\"";
    }

    if ( ! empty( $createdFrom ) ) {
      $where .= ' AND um2.meta_value >= ' . $createdFrom;
    }

    if ( ! empty( $createdTo ) ) {
      $where .= ' AND um2.meta_value <= ' . $createdTo;
    }

    if ( ! empty( $modifiedFrom ) ) {
      $where .= ' AND um1.meta_value >= ' . $modifiedFrom;
    }

    if ( ! empty( $modifiedTo ) ) {
      $where .= ' AND um1.meta_value <= ' . $modifiedTo;
    }

    $query = "
      SELECT
        u.*,
        um.*
      FROM {$wpdb->prefix}usermeta AS um
        JOIN {$wpdb->prefix}users AS u
          ON um.user_id = u.ID
          {$join}
      WHERE um.meta_key = '{$this->get_cart_meta_key_slug()}'
        AND um.meta_value != 'a:1:{s:4:\"cart\";a:0:{}}'
        AND um.meta_value != ''
        {$where}
      ORDER BY u.ID asc
      LIMIT {$start},{$count}
    ";
    $res = $wpdb->get_results( $query );

    if ( ! empty ( $res ) ) {
      foreach ( $res as $item ) {
        $cartData = unserialize( $item->meta_value, array('allowed_classes' => array( 'stdClass') ) );
        $itemData[$item->ID] = (array)$item;

        foreach ( $cartData['cart'] as $cartItem ) {
          $itemData[$item->ID]['cart'][$cartItem['product_id']] = $cartItem;

          if ( (string)$cartItem['variation_id'] !== '0' ) {
            $postId = $cartItem['variation_id'];
          } else {
            $postId = $cartItem['product_id'];
          }

          $itemData[$item->ID]['cart'][$cartItem['product_id']]['product_meta'] = get_post_meta( $postId );
          $itemData[$item->ID]['cart'][$cartItem['product_id']]['product_data'] = get_post( $postId );
        }

        $itemData[$item->ID]['basket_url'] = $basketUrl;
        $itemData[$item->ID]['user_meta'] = get_user_meta( $item->ID );
        $abandonedOrders[$item->ID] = $this->exclude_data( $itemData[$item->ID], array( 'user_pass', 'meta_key', 'meta_value', 'user_activation_key' ) );
      }
    }

    return rest_ensure_response( $abandonedOrders );
  }

  /**
   * Exclude data by key.
   * @param array $data Data array.
   * @param array $keys Keys.
   * @return array
   */
  protected function exclude_data( $data, $keys ) {
    foreach ( $keys as $key ) {
      if ( isset( $data[$key] ) ) {
        unset ( $data[$key] );
      }
    }

    return $data;
  }

}