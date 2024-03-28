<?php

namespace Voucherly\Woocommerce;

use Voucherly\Plugin\Constants;
use Voucherly\Plugin\Database\EntityManager;

/**
 * Class for handling the admin order BO cycle
 */
class AdminOrder{

  public static $voucherly_order_data;

  /**
   * Calls add_meta_box in order to render the box inside the admin order page
   */
  public static function showAdminOrderBox(){
    global $post;

    self::$voucherly_order_data = (new EntityManager(wc_get_order($post->ID)))->getOrder();

    if(self::$voucherly_order_data===false) return;

    add_meta_box(
      'Some identifier of your custom box',
      __( Constants::PLUGIN_NAME, Constants::DOMAIN ),
      [
        'Voucherly\WooCommerce\AdminOrder',
        'renderAdminOrderBox'
      ],
      'shop_order'
    );
  }

  /**
   * Renders the real content
   */
  public static function renderAdminOrderBox(){
    echo implode('<br>',[
      implode('',[
        '<b>',__('ID della transazione:',Constants::DOMAIN),'</b>',
        '<p>',self::$voucherly_order_data->transaction_id,'</p>'
      ])
    ]);
  }

  /**
   * Add new column to admin orders list
   */
  public static function addColumnOrdersList($columns){
    $reordered_columns = array();

    foreach( $columns as $key => $column){
        $reordered_columns[$key] = $column;
        if( $key ==  'order_status' ){
            $reordered_columns[Constants::PLUGIN_FOLDER_NAME] = __( Constants::PLUGIN_NAME, Constants::DOMAIN);
        }
    }
    return $reordered_columns;
  }

  /**
   * Render if the order is made by a Isendu
   */
  public static function getGLSColumnValue($column, $post_id){
    switch($column){
      case Constants::PLUGIN_FOLDER_NAME:
        $order = (new EntityManager(wc_get_order($post_id)))->getOrder();
        echo '<i class="dashicons dashicons-'.($order!==false ? 'yes' : 'no').'" style="color: '.($order!==false ? 'green' : 'red').';"></i>';
        break;
    }
  }
}