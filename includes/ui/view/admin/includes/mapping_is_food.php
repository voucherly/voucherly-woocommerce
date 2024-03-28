<?php

use Voucherly\Plugin\Constants;
use Voucherly\Woocommerce\Category;

/**
 * @var Category
 */
$categoryHelper = Category::getInstance();

?>
<div class="col-12 my-5">
  <h3>
    <?php echo __('Prodotti Food', Constants::DOMAIN); ?>
  </h3>
  <div class="row bg-white">
    <div class="col-12">
      <!-- search -->
      <input type="text" name="search" id="search" class="form-control" placeholder="<?php echo __('Cerca le categorie', Constants::DOMAIN); ?>" />
    </div>
    <div class="col-12 mt-3">
      <div class="border max-height-300">
        <?php
        foreach ($categoryHelper->get() as $category) {
        ?>
          <div class="border-bottom">
            <div class="row p-2" data-category-name="<?php echo $category->name; ?>" data-category-id="<?php echo $category->term_id; ?>">
              <div class="col">
                <?php echo $category->name; ?>
              </div>
              <div class="col-1 text-center">
                <input type="checkbox" class="form-check-input mt-1" name="category_is_food[<?php echo $category->term_id; ?>]" <?php echo $categoryHelper->isFood($category->term_id) ? 'checked' : ''; ?>>
              </div>
            </div>
          </div>
        <?php
        }
        ?>
      </div>
    </div>
  </div>
</div>