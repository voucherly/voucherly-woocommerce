<?php

namespace Voucherly\Traits;

use Voucherly\Api\Dto\Gateway\PaymentDiscountDto;
use Voucherly\Api\Dto\Gateway\PaymentDto;
use Voucherly\Api\Dto\Gateway\PaymentProductDto;
use Voucherly\Woocommerce\Category;
use Voucherly\Woocommerce\Payment\Router;
use WC_Coupon;
use WC_Order;

trait GatewayTrait {
  public function getPayment(WC_Order $order){
    $routes = Router::addRoutes();
    /**
     * @var PaymentDto
     */
    $paymentDto = new PaymentDto;
    $paymentDto->customerFirstName = $order->get_billing_first_name();
    $paymentDto->customerLastName = $order->get_billing_last_name();
    $paymentDto->customerEmail = $order->get_billing_email();
    $paymentDto->shippingAddress = $order->get_formatted_billing_address();
    $paymentDto->country = $order->get_billing_country();
    $paymentDto->language = explode('_',get_locale())[0];
    $paymentDto->lines = array_merge($this->getPaymentLines(),$this->getShippingLines($order));
    $paymentDto->discounts = $this->getPaymentDiscounts();
    $paymentDto->redirectSuccessUrl = $routes['success'].(stripos($routes['success'],'?')===false ? '?' : '&').'nojson=1&orderId='.$order->get_id();
    $paymentDto->redirectErrorUrl = $routes['error'];

    return $paymentDto;
  }

  private function getPaymentLines(){
    $products = [];
    $cart_items = WC()->cart->get_cart();
    /**
     * @var Category
     */
    $categoryHelper = Category::getInstance();

    foreach($cart_items as $key => $item){
      /**
       * @var WC_Product
       */
      $product = wc_get_product($item['product_id']);

      /**
       * @var PaymentProductDto
       */
      $paymentProductDto = new PaymentProductDto();
      $paymentProductDto->productName = $product->get_name();
      $paymentProductDto->productImage = wp_get_attachment_image_src(get_post_thumbnail_id($product->get_id()),'full')[0];
      $paymentProductDto->unitAmount = $product->get_regular_price()*100;
      if($product->get_sale_price()) $paymentProductDto->unitDiscountAmount = $paymentProductDto->unitAmount-$product->get_sale_price()*100;
      $paymentProductDto->quantity = $item['quantity'];
      foreach($product->get_category_ids() as $category_id){
        $paymentProductDto->isFood = $categoryHelper->isFood(
          $category_id
        );
        if($paymentProductDto->isFood) break;
      }
      $products[] = $paymentProductDto;
    }
    return $products;
  }

  private function getShippingLines(WC_Order $order){
    $shipping_lines = [];
    foreach($order->get_shipping_methods() as $shipping_method){
      if($shipping_method->get_total()<=0) continue;
      /**
       * @var PaymentProductDto
       */
      $paymentProductDto = new PaymentProductDto();
      $paymentProductDto->productName = $shipping_method->get_method_title();
      $paymentProductDto->unitAmount = $shipping_method->get_total()*100;
      $paymentProductDto->quantity = 1;
      $shipping_lines[] = $paymentProductDto;
    }

    return $shipping_lines;
  }

  private function getPaymentDiscounts() {
    $discounts = [];

    $coupons = WC()->cart->get_applied_coupons();

    foreach($coupons as $coupon_code){
      /**
       * @var WC_Coupon
       */
      $coupon = new WC_Coupon($coupon_code);
      /**
       * @var PaymentDiscountDto
       */
      $paymentDiscountDto = new PaymentDiscountDto();
      $paymentDiscountDto->discountName = $coupon->get_code();
      $paymentDiscountDto->amount = $coupon->get_amount()*100;

      $discounts[] = $paymentDiscountDto;
    }

    return $discounts;
  }
}