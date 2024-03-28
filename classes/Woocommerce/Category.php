<?php

namespace Voucherly\Woocommerce;

use Voucherly\Plugin\AdminSettings;
use Voucherly\Plugin\Constants;

class Category {

  private static $category_instance;

  public static function getInstance(){
    if(!isset(self::$category_instance)){
      self::$category_instance = new Category;
    }

    return self::$category_instance;
  }

  public function isFood($category_id){
    if(!AdminSettings::exists(Constants::CATEGORY_IS_FOOD)) return false;

    $json = AdminSettings::get(Constants::CATEGORY_IS_FOOD);

    return array_key_exists($category_id,$json);
  }

  public function get($hide_empty = false) {
    return get_terms(array(
      'taxonomy' => 'product_cat',
      'hide_empty' => $hide_empty
    ));
  }
}