<?php
if (!defined('ABSPATH')) {
  exit;
}

include_once ABSPATH . 'wp-admin/includes/plugin.php';

class WH_Helper
{

  const VERSION                    = '1.7';
  const LANG_DOMAIN                = 'a2c_wh';

  const OPTION_NAME_VERSION        = 'webhook_helper_version';
  const OPTION_NAME_ACTIVE         = 'webhook_helper_active';

  const SESSION_FIELD_CART_ID      = '_a2c_wh_cart_id';
  const SESSION_FIELD_CREATED_GMT  = '_a2c_wh_cart_created_gmt';
  const SESSION_FIELD_UPDATED_GMT  = '_a2c_wh_cart_updated_gmt';
  const METAKEY_SHIPMENT_TRACKING = '_wc_shipment_tracking_items';

  /**
   * @var null|WH_Helper
   */
  private static $_instance = null;

  private $_metaFieldCreatedGmt = null;
  private $_metaFieldUpdatedGmt = null;
  private $_metaFieldCartId = null;
  private $_metaFieldPersistentCart = null;

  private $_blogId = null;
  private $_cartId = null;
  private $_customerId = null;
  private $_isHookThrown = false;
  private $_cartHash = null;
  private $_tracking = null;


  /**
   * Wh_Helper constructor.
   */
  private function __construct()
  {
    add_action('woocommerce_checkout_create_order', array($this, 'setCartIdForOrder'), 10, 1);
    add_action('woocommerce_webhook_payload', array($this, 'buildWebhookPayload'), 10, 3);
    add_action('woocommerce_webhook_topic_hooks', array($this, 'registerWebhookTopicHooks'));

    add_action('woocommerce_update_cart_action_cart_updated', array($this, 'resolveEvent'));
    add_action('woocommerce_cart_item_restored',  array($this, 'resolveEvent'));
    add_action('woocommerce_cart_item_removed', array($this, 'resolveEvent'));
    add_action('woocommerce_add_to_cart',  array($this, 'resolveEvent'));

    add_action('woocommerce_valid_webhook_resources', array($this, 'registerWebhookResource'));
    add_action('woocommerce_webhook_topics',array($this, 'addWebhookTopics'));
    add_action('update_user_meta', array($this, 'beforePersistentCartUpdate'), 29, 3);
    add_action('updated_user_meta', array($this, 'persistentCartUpdated'), 30, 3);

    add_action( 'add_post_meta', array( $this, 'insertShipment' ), 29, 3);
    add_action( 'update_post_meta', array( $this, 'beforeShipmentUpdate' ), 29, 4);
    add_action( 'updated_post_meta', array( $this, 'afterShipmentUpdate' ), 29, 4);
    add_action( 'delete_post_meta', array( $this, 'afterShipmentUpdate' ), 29, 4);

    add_action( 'woocommerce_new_product_variation', array( $this, 'insertChild' ), 29, 2);
    add_action( 'woocommerce_update_product_variation', array( $this, 'updateChild' ), 29, 2);
    add_action( 'woocommerce_before_delete_product_variation', array( $this, 'deleteChild' ), 29, 1);

    add_action( 'create_product_cat', array( $this, 'afterCategoryInsert' ), 29, 3);
    add_action( 'edited_product_cat', array( $this, 'afterCategoryUpdate' ), 29, 4);
    add_action( 'delete_term_taxonomy', array( $this, 'afterCategoryDelete' ), 29, 4);

    $this->_blogId = get_current_blog_id();
    $this->_metaFieldCreatedGmt = "_a2c_wh_cart_{$this->_blogId}_created_gmt";
    $this->_metaFieldUpdatedGmt = "_a2c_wh_cart_{$this->_blogId}_updated_gmt";
    $this->_metaFieldCartId = "_a2c_wh_cart_{$this->_blogId}_id";

    if ( class_exists( 'WooCommerce' ) ) {
      if (version_compare(WooCommerce::instance()->version, '3.1.0', '>=')) {
        $this->_metaFieldPersistentCart = "_woocommerce_persistent_cart_{$this->_blogId}";
      } else {
        $this->_metaFieldPersistentCart = '_woocommerce_persistent_cart';
      }
    }
  }

  /**
   * @return WH_Helper
   */
  public static function instance()
  {
    return self::$_instance ?: self::$_instance = new self;
  }

  /**
   * @param $meta_id
   * @param $object_id
   * @param $meta_key
   */
  public function beforePersistentCartUpdate($meta_id, $object_id, $meta_key)
  {
    if ($meta_key === $this->_metaFieldPersistentCart) {
      $this->_cartHash = md5(wp_json_encode($this->_loadCartFromDb()));
    }
  }

  /**
   * @param $meta_id
   * @param $object_id
   * @param $meta_key
   */
  public function persistentCartUpdated( $meta_id, $object_id, $meta_key )
  {
    if ( $meta_key === $this->_metaFieldPersistentCart
      && ! empty( WooCommerce::instance()->cart )
      && $this->_cartHash !== md5( wp_json_encode( WooCommerce::instance()->cart->get_cart_for_session() ) )
    ) {
      $this->resolveEvent(true);
    }
  }

  /**
   * @param int    $meta_id    Meta ID
   * @param int    $object_id  Object ID
   * @param string $meta_key   Meta Key
   * @param mixed  $meta_value Meta Value
   */
  function beforeShipmentUpdate($meta_id, $object_id, $meta_key, $meta_value) {
    if ( $meta_key === self::METAKEY_SHIPMENT_TRACKING ) {
      if ( !function_exists('get_post_meta_by_id') ) {
        require_once ABSPATH . 'wp-admin/includes/post.php';
      }

      $this->_tracking = isset(get_post_meta_by_id($meta_id)->meta_value) ? get_post_meta_by_id($meta_id)->meta_value : [];
    }
  }

  /**
   * @param int    $meta_id    Meta ID
   * @param int    $object_id  Object ID
   * @param string $meta_key   Meta Key
   * @param mixed  $meta_value Meta Value
   */
  function afterShipmentUpdate($meta_id, $object_id, $meta_key, $meta_value) {
    if ( $meta_key === self::METAKEY_SHIPMENT_TRACKING ) {

      if ( is_plugin_active( 'ast-pro/ast-pro.php' ) ) {
        $isAstProActive = true;
      } else {
        $isAstProActive = false;
      }

      if ( !function_exists('get_post_meta_by_id') ) {
        require_once ABSPATH . 'wp-admin/includes/post.php';
      }

      $currentTracking = isset(get_post_meta_by_id($meta_id)->meta_value) ? get_post_meta_by_id($meta_id)->meta_value : [];

      $countCurrentTracking = count($currentTracking);
      $countExistTracking = $this->_tracking ? count($this->_tracking) : 0;

      if (empty($currentTracking)) {
        ///shipment delete
        if ($isAstProActive) {
          do_action('a2c_wh_shipment_deleted_action', $this->_shipmentData([], $object_id, $this->_tracking['tracking_id']));
        } else {
          do_action('a2c_wh_shipment_deleted_action', $this->_shipmentData([], $object_id));
        }
      } elseif ($countExistTracking > $countCurrentTracking) {
        ///shipment.update tracking number was deleted
        $deletedTracking = reset(array_diff_assoc($this->_tracking, $currentTracking));

        if ($isAstProActive) {
          do_action( 'a2c_wh_shipment_deleted_action', $this->_shipmentData($deletedTracking, $object_id, $deletedTracking['tracking_id']));
        } else {
          do_action('a2c_wh_shipment_updated_action', $this->_shipmentData($deletedTracking, $object_id));
        }
      } elseif ($countCurrentTracking > $countExistTracking) {
        ///shipment.update tracking number was added
        $newTracking = reset(array_diff_assoc($currentTracking, $this->_tracking));

        if ($isAstProActive) {
          do_action('a2c_wh_shipment_created_action', $this->_shipmentData($newTracking, $object_id, $newTracking['tracking_id']));
        } else {
          do_action('a2c_wh_shipment_created_action', $this->_shipmentData($newTracking, $object_id));
        }
      } else {
        ///shipment.update tracking number was changed
        foreach (array_keys($this->_tracking) as $key) {
          if (array_diff_assoc($currentTracking[$key], $this->_tracking[$key])) {
            $changeTracking = $currentTracking[$key];

            if ($isAstProActive) {
              do_action('a2c_wh_shipment_updated_action', $this->_shipmentData($changeTracking, $object_id, $changeTracking['tracking_id']));
            } else {
              do_action('a2c_wh_shipment_updated_action', $this->_shipmentData($changeTracking, $object_id));
            }

            break;
          }
        }
      }
    }
  }

  /**
   * @param int    $post_id  Post ID
   * @param string $meta_key   Meta Key
   * @param mixed  $meta_value Meta Value
   */
  function insertShipment($post_id, $meta_key, $meta_value) {
    if ( $meta_key === self::METAKEY_SHIPMENT_TRACKING ) {
      $currentShipments = reset($meta_value);

      if ( is_plugin_active( 'ast-pro/ast-pro.php' ) ) {
        $isAstProActive = true;
      } else {
        $isAstProActive = false;
      }

      if ($isAstProActive) {
        do_action( 'a2c_wh_shipment_created_action',  $this->_shipmentData($currentShipments, $post_id, $currentShipments['tracking_id']));
      } else {
        do_action( 'a2c_wh_shipment_created_action',  $this->_shipmentData($currentShipments, $post_id));
      }
    }
  }

  function insertChild($id, $product) {
    do_action('a2c_wh_variant_created_action', ['store_id' => $this->_blogId, 'id' => $id, 'parent_id' => $product->get_data()['parent_id']]);
  }

  function updateChild($id, $product) {
    do_action('a2c_wh_variant_updated_action', ['store_id' => $this->_blogId, 'id' => $id, 'parent_id' => $product->get_data()['parent_id']]);
  }

  function deleteChild($id)
  {
    global $wpdb;
    $parent = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->posts WHERE ID = %d", $id));

    do_action('a2c_wh_variant_deleted_action', ['store_id' => $this->_blogId, 'parent_id' => $parent->post_parent, 'id' => $id]);
  }

  /**
   * @param $term_id
   */
  function afterCategoryInsert($term_id) {
    do_action('a2c_wh_category_created_action', ['store_id' => $this->_blogId, 'id' => $term_id]);
  }

  /**
   * @param $term_id
   */
  function afterCategoryUpdate($term_id) {
    do_action('a2c_wh_category_updated_action', ['store_id' => $this->_blogId, 'id' => $term_id]);
  }

  /**
   * @param $term_id
   */
  function afterCategoryDelete($term_id) {
    do_action('a2c_wh_category_deleted_action', ['store_id' => $this->_blogId, 'id' => $term_id]);
  }

  /**
   * @param array  $shipmentData Shipment Data
   * @param string $object_id    Object ID
   * @param string $entity_id    Entity ID
   *
   * @return array
   */
  private function _shipmentData($shipmentData, $object_id, $entity_id = null)
  {
    return [
      'entity_id'                => $entity_id !== null ? $entity_id : md5($object_id . 'shipment'),
      'order_id'                 => $object_id,
      'blog_id'                  => $this->_blogId,
      'custom_tracking_link'     => isset($shipmentData['custom_tracking_link']) ? $shipmentData['custom_tracking_link'] : '',
      'custom_tracking_provider' => isset($shipmentData['custom_tracking_provider']) ? $shipmentData['custom_tracking_provider'] : '',
      'date_shipped'             => isset($shipmentData['date_shipped']) ? $shipmentData['date_shipped'] : '',
      'tracking_id'              => isset($shipmentData['tracking_id']) ? $shipmentData['tracking_id'] : '',
      'tracking_number'          => isset($shipmentData['tracking_number']) ? $shipmentData['tracking_number'] : '',
      'tracking_provider'        => isset($shipmentData['tracking_provider']) ? $shipmentData['tracking_provider'] : '',
    ];
  }

  /**
   * @return array
   */
  private function _loadCartFromDb()
  {
    $saved_cart = array();

    if (apply_filters('woocommerce_persistent_cart_enabled', true)) {
      $saved_cart_meta = get_user_meta(get_current_user_id(), $this->_metaFieldPersistentCart, true);

      if (isset($saved_cart_meta['cart'])) {
        $saved_cart = array_filter((array)$saved_cart_meta['cart']);
      }
    }

    return $saved_cart;
  }

  /**
   * @param array $resources
   *
   * @return array
   */
  public function registerWebhookResource($resources)
  {
    $resources[] = 'basket';
    $resources[] = 'order_shipment';
    $resources[] = 'variant';
    $resources[] = 'category';

    return $resources;
  }

  /**
   * @param array $topics
   *
   * @return array
   */
  public function addWebhookTopics($topics)
  {
    $topics = array_merge(
      $topics,
      array(
        'basket.created' => __('Basket Created', 'a2c_wh'),
        'basket.updated' => __('Basket Updated', 'a2c_wh'),
        'basket.deleted' => __('Basket Deleted', 'a2c_wh'),
        'order_shipment.created' => __( 'Shipment Tracking Created', 'a2c_wh' ),
        'order_shipment.updated' => __( 'Shipment Tracking Updated', 'a2c_wh' ),
        'order_shipment.deleted' => __( 'Shipment Tracking Deleted', 'a2c_wh' ),
        'variant.created' => __( 'Variant Created', 'a2c_wh' ),
        'variant.updated' => __( 'Variant Updated', 'a2c_wh' ),
        'variant.deleted' => __( 'Variant Deleted', 'a2c_wh' ),
        'category.created' => __( 'Category Created', 'a2c_wh' ),
        'category.updated' => __( 'Category Updated', 'a2c_wh' ),
        'category.deleted' => __( 'Category Deleted', 'a2c_wh' ),
      )
    );

    return $topics;
  }

  /**
   * @return int
   */
  private function _getCustomerId()
  {
    if ($this->_customerId === null) {
      $this->_customerId = (int)get_current_user_id();
    }

    return $this->_customerId;
  }

  /**
   * @param bool $isDelete
   *
   * @return array
   */
  private function _webhookData($isDelete = false)
  {
    $webhookData = array(
      'blog_id' => $this->_blogId,
      'cart_id' => $this->_getCartId(),
      'session_key' => WooCommerce::instance()->session->get_customer_id(),
      'customer' => $this->_prepareCustomerData(),
      self::SESSION_FIELD_CREATED_GMT => WooCommerce::instance()->session->get(self::SESSION_FIELD_CREATED_GMT),
      self::SESSION_FIELD_UPDATED_GMT => WooCommerce::instance()->session->get(self::SESSION_FIELD_UPDATED_GMT),
    );

    if (!$isDelete) {
      $webhookData['cart'] = $this->_cartDataToArray();
    }

    return $webhookData;
  }

  /**
   * @return array
   */
  private function _cartDataToArray()
  {
    new WC_Cart_Totals(WooCommerce::instance()->cart);

    $items = [];
    foreach (WooCommerce::instance()->cart->get_cart_contents() as $key => $item) {
      $items[] = $this->_prepareItemData($item, $key);
    }

    return $items;
  }

  /**
   * @param array $item
   * @param string $basketItemId
   *
   * @return array
   */
  private function _prepareItemData($item, $basketItemId)
  {
    $itemData = $item['data'];
    /**
     * @var WC_Product $itemData
     */

    $data = array(
      'id'           => $basketItemId,
      'product_id'   => $itemData->get_id(),
      'sku'          => $itemData->get_sku(),
      'name'         => $itemData->get_name(),
      'variant_id'   => $item['variation_id'] ?: null,
      'weight'       => $itemData->get_weight(),
      'length'       => $itemData->get_length(),
      'width'        => $itemData->get_width(),
      'height'       => $itemData->get_height(),
      'quantity'     => $item['quantity'],
      'price'        => $itemData->get_price(),
      'subtotal'     => $item['line_subtotal'],
      'subtotal_tax' => $item['line_subtotal_tax'],
      'total'        => $item['line_total'],
      'total_tax'    => $item['line_tax'],
    );

    return $data;
  }

  /**
   * @return array
   */
  private function _prepareCustomerData()
  {
    $customer = WooCommerce::instance()->customer;
    $data = $customer->get_data();

    if ($data['id'] != 0) {
      $data = array_intersect_key(
        $data,
        array_flip(array('id', 'email', 'first_name', 'last_name', 'billing', 'shipping'))
      );
    } else {
      $customer = WooCommerce::instance()->session->get('customer');

      if (!empty($customer)) {
        $data = array(
          'id'         => $customer['id'],
          'email'      => $customer['email'],
          'first_name' => $customer['first_name'],
          'last_name'  => $customer['last_name'],
          'billing'    => array(
            'first_name' => $customer['first_name'],
            'last_name'  => $customer['last_name'],
            'company'    => $customer['company'],
            'address_1'  => isset($customer['address_1']) ? $customer['address_1'] : $customer['address'],
            'address_2'  => $customer['address_2'],
            'city'       => $customer['city'],
            'postcode'   => $customer['postcode'],
            'state'      => $customer['state'],
            'country'    => $customer['country'],
            'email'      => $customer['email'],
            'phone'      => $customer['phone'],
          ),
          'shipping'   => array(
            'first_name' => $customer['shipping_first_name'],
            'last_name'  => $customer['shipping_last_name'],
            'company'    => $customer['shipping_company'],
            'address_1'  => isset($customer['shipping_address_1']) ? $customer['shipping_address_1'] : $customer['shipping_address'],
            'address_2'  => $customer['shipping_address_2'],
            'city'       => $customer['shipping_city'],
            'postcode'   => $customer['shipping_postcode'],
            'state'      => $customer['shipping_state'],
            'country'    => $customer['shipping_country'],
          ),
        );
      }
    }

    $data['ip_address'] = $this->get_current_user_ip();

    return $data;
  }

  /**
   * @return void
   */
  public function cartCreated()
  {
    if (!$this->_isHookThrown) {
      $time = time();

      $cartId = $this->_generateCartId();

      if (($customerId = $this->_getCustomerId()) !== 0) {
        update_user_meta($customerId, $this->_metaFieldUpdatedGmt, $time);
        update_user_meta($customerId, $this->_metaFieldCreatedGmt, $time);
        update_user_meta($customerId, $this->_metaFieldCartId, $cartId);
      }

      WooCommerce::instance()->session->set(self::SESSION_FIELD_UPDATED_GMT, $time);
      WooCommerce::instance()->session->set(self::SESSION_FIELD_CREATED_GMT, $time);
      WooCommerce::instance()->session->set(self::SESSION_FIELD_CART_ID, $cartId);

      do_action('a2c_wh_cart_created_action', $this->_webhookData());
      $this->_isHookThrown = true;
    }
  }

  /**
   * @return void
   */
  public function cartUpdated()
  {
    if (!$this->_isHookThrown) {
      $this->_updateCartDate();

      do_action('a2c_wh_cart_updated_action', $this->_webhookData());
      $this->_isHookThrown = true;
    }
  }

  /**
   * @param $arg
   */
  public function cartEmptied()
  {
    if (!$this->_isHookThrown) {
      $this->_updateCartDate();

      do_action('a2c_wh_cart_emptied_action', $this->_webhookData());
      $this->_isHookThrown = true;
    }
  }

  /**
   * @return void
   */
  private function _updateCartDate()
  {
    $time = time();

    if ($this->_getCustomerId() !== 0) {
      update_user_meta($this->_getCustomerId(), $this->_metaFieldUpdatedGmt, $time);
    }

    WooCommerce::instance()->session->set(self::SESSION_FIELD_UPDATED_GMT, $time);
  }

  /**
   * @param $arg
   */
  public function cartDeleted()
  {
    if (!$this->_isHookThrown && !empty($this->_getCartId())) {
      if ($this->_getCustomerId() !== 0) {
        delete_user_meta($this->_getCustomerId(), $this->_metaFieldCreatedGmt);
        delete_user_meta($this->_getCustomerId(), $this->_metaFieldUpdatedGmt);
        delete_user_meta($this->_getCustomerId(), $this->_metaFieldCartId);
      }

      WooCommerce::instance()->session->__unset(self::SESSION_FIELD_CREATED_GMT);
      WooCommerce::instance()->session->__unset(self::SESSION_FIELD_UPDATED_GMT);
      WooCommerce::instance()->session->__unset(self::SESSION_FIELD_CART_ID);

      do_action('a2c_wh_cart_deleted_action', $this->_webhookData(true));
      $this->_isHookThrown = true;
      $this->_cartId = null;
    }
  }

  /**
   * @param mixed $arg
   *
   * @return mixed
   */
  public function resolveEvent($arg)
  {
    if (empty(WooCommerce::instance()->cart->get_cart_contents())) {
      $this->cartEmptied();
    } else {
      if ($this->_getCustomerId() !== 0) {
        if (empty(get_user_meta($this->_getCustomerId(), $this->_metaFieldCreatedGmt, true))) {
          $this->cartCreated();
        } else {
          $this->cartUpdated();
        }
      } elseif (empty(WooCommerce::instance()->session->get(self::SESSION_FIELD_CREATED_GMT))) {
        $this->cartCreated();
      } else {
        $this->cartUpdated();
      }
    }

    return $arg;
  }

  /**
   * @param array $topics
   *
   * @return array
   */
  public function registerWebhookTopicHooks($topics)
  {
    $topics['basket.updated'] = array(
      'a2c_wh_cart_updated_action',
      'a2c_wh_cart_emptied_action',
    );
    $topics['basket.created'] = array(
      'a2c_wh_cart_created_action',
    );
    $topics['basket.deleted'] = array(
      'a2c_wh_cart_deleted_action',
    );
    $topics['order_shipment.created'] = array(
      'a2c_wh_shipment_created_action'
    );
    $topics['order_shipment.updated'] = array(
      'a2c_wh_shipment_updated_action'
    );
    $topics['order_shipment.deleted'] = array(
      'a2c_wh_shipment_deleted_action'
    );
    $topics['variant.created'] = array(
      'a2c_wh_variant_created_action',
    );
    $topics['variant.updated'] = array(
      'a2c_wh_variant_updated_action',
    );
    $topics['variant.deleted'] = array(
      'a2c_wh_variant_deleted_action',
    );
    $topics['category.created'] = array(
      'a2c_wh_category_created_action',
    );
    $topics['category.updated'] = array(
      'a2c_wh_category_updated_action',
    );
    $topics['category.deleted'] = array(
      'a2c_wh_category_deleted_action',
    );

    return $topics;
  }

  /**
   * @param mixed  $payload
   * @param string $resource
   * @param mixed  $resource_id
   *
   * @return mixed
   */
  public function buildWebhookPayload($payload, $resource, $resource_id)
  {
    if ( in_array($resource, ['basket', 'order_shipment', 'variant', 'category'] )) {
      $payload = $resource_id;
    }

    return $payload;
  }

  /**
   * @return string|null
   */
  private function _getCartId()
  {
    if ($this->_cartId !== null) {
      return $this->_cartId;
    }

    $cartId = WooCommerce::instance()->session->get(self::SESSION_FIELD_CART_ID, false);

    if (!$cartId && ($userId = get_current_user_id()) !== 0) {
      $cartId = get_user_meta($userId, $this->_metaFieldCartId, true);
    }

    if ($cartId) {
      $this->_cartId = $cartId;
    }

    return $this->_cartId;
  }

  /**
   * @return string
   */
  private function _generateCartId()
  {
    require_once ABSPATH . 'wp-includes/class-phpass.php';
    $hasher  = new PasswordHash(8, false);

    $this->_cartId = md5($hasher->get_random_bytes(32));

    return $this->_cartId;
  }

  /**
   * @param WC_Order $order
   * @return void
   */
  public function setCartIdForOrder($order)
  {
    $order->add_meta_data(self::SESSION_FIELD_CART_ID, $this->_getCartId());

    $this->cartDeleted();
  }

  /**
   * @return void
   */
  public static function deactivate()
  {
    update_option(self::OPTION_NAME_ACTIVE, false);
  }

  /**
   * @return void
   */
  public static function uninstall()
  {
    /**
     * @global $wpdb wpdb Database Access Abstraction Object
     */
    global $wpdb;
    $wpdb->query('DELETE FROM `' . $wpdb->prefix . 'usermeta` WHERE `meta_key` LIKE "_a2c_wh_cart_%"');
    delete_option(self::OPTION_NAME_VERSION);
    delete_option(self::OPTION_NAME_ACTIVE);
  }

  /**
   * @return void
   */
  public static function activate()
  {
    if (!class_exists('WooCommerce') || version_compare(WC()->version, 2.6, '<')) {
      return;
    }

    update_option(self::OPTION_NAME_VERSION, self::VERSION, false);
    update_option(self::OPTION_NAME_ACTIVE, true, false);
  }

  /**
   * Gets current user IP address.
   *
   * @return string Current user IP address.
   */
  public function get_current_user_ip() {
    foreach (
      [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'HTTP_VIA',
        'REMOTE_ADDR',
      ] as $key
    ) {
      if (!empty($_SERVER[$key])) {
        return $_SERVER[$key];
      }
    }

    return '';
  }

}