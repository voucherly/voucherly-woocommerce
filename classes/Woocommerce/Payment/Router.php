<?php

namespace Voucherly\Woocommerce\Payment;

use Voucherly\Plugin\AdminSettings;
use Voucherly\Plugin\Constants;

class Router
{

  public static function addRoutes()
  {
    $success = self::createSuccessPage();
    $error = self::createErrorPage();

    return [
      'success' => get_permalink(get_post($success)),
      'error' => get_permalink(get_post($error))
    ];
  }

  public static function renderTemplate($template)
  {
    $uri = $_SERVER['REQUEST_URI'];
    $error_route = Constants::PLUGIN_FOLDER_PATH . 'includes/ui/view/payment/error.php';
    $hasNoPermalink = isset($_GET['page_id']);
    if($hasNoPermalink){
      $isSuccessPage = AdminSettings::get(Constants::DOMAIN.'_success')==$_GET['page_id'];
      $isErrorPage = AdminSettings::get(Constants::DOMAIN.'_error')==$_GET['page_id'];
    }
    if (stripos($uri,Constants::DOMAIN . '-success')!==false || (isset($isSuccessPage) && $isSuccessPage)) {
      if(isset($_GET['orderId'])){

        /**
         * Validate order
         */
        $status = true;
        $order_id = (new Validator($_GET['orderId']))->validate();
        if(isset($_GET['nojson'])){

          $order = wc_get_order($order_id);
          $order->update_status('completed');
          wp_redirect($order->get_checkout_order_received_url());
          
          return;

        }
        if(false===$order_id) $status = false;

        header('Content-Type: application/json');
        exit(
          json_encode(
            [
              'isSuccess' => $status,
              'orderId' => $order_id
            ]
          )
        );
        
      }
      return Constants::PLUGIN_FOLDER_PATH . 'includes/ui/view/payment/success.php';
    }
    if (stripos($uri,Constants::DOMAIN . '-error')!==false || (isset($isErrorPage) && $isErrorPage)) {
      wp_redirect(wc_get_checkout_url().'?error-voucherly=1');

      return $error_route;
    }
    return $template;
  }

  private static function createSuccessPage(){
    return self::createPage(Constants::DOMAIN.'_success','Success','');
  }

  private static function createErrorPage(){
    return self::createPage(Constants::DOMAIN.'_error','Error','');
  }

  private static function createPage($key, $type, $content)
  {
    if (!AdminSettings::exists($key)) {
      $args = array(
        'post_title' => Constants::PLUGIN_NAME . ' ' . $type,
        'post_content' => $content,
        'post_status' => 'publish',
        'post_type' => 'page'
      );

      $page = wp_insert_post($args);
      AdminSettings::update($key, $page);
    }else{
      $page = AdminSettings::get($key);
    }
    return $page;
  }
}
