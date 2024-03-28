<?php

use Voucherly\Enum\RefundType;
use Voucherly\Plugin\Constants;
?>
<div class="col-12 mt-5">
  <h3>
    <?php echo __('Rimborso ordine', Constants::DOMAIN); ?>
  </h3>
  <div>
    <small>
      <?php echo __('Indica lo stato preposto al rimborso tra quelli disponibili nella select sottostante', Constants::DOMAIN); ?>
    </small>
    <div class="row my-4">
      <div class="col">
        <label>
          <?php echo __('Tipologia di rimborso', Constants::DOMAIN); ?>
        </label>
        <select name="refund_type" class="form-control">
          <option value="<?php echo RefundType::AUTO; ?>" <?php echo isset($settings[Constants::DOMAIN . '_' . Constants::REFUND_TYPE]) && $settings[Constants::DOMAIN . '_' . Constants::REFUND_TYPE] == RefundType::AUTO ? 'selected' : ''; ?>>
            <?php echo __('Automatico', Constants::DOMAIN); ?>
          </option>
          <option value="<?php echo RefundType::MANUAL; ?>" <?php echo isset( $settings[Constants::DOMAIN . '_' . Constants::REFUND_TYPE]) && $settings[Constants::DOMAIN . '_' . Constants::REFUND_TYPE] == RefundType::MANUAL ? 'selected' : ''; ?>>
            <?php echo __('Manuale', Constants::DOMAIN); ?>
          </option>
        </select>
      </div>
      <div class="col <?php echo isset( $settings[Constants::DOMAIN . '_' . Constants::REFUND_TYPE]) && $settings[Constants::DOMAIN . '_' . Constants::REFUND_TYPE] == RefundType::MANUAL ? 'd-none' : ''; ?>" data-woocommerce-status>
        <label>
          <?php echo __('Stato Woocommerce',Constants::DOMAIN); ?>
        </label>
        <select name="map_orders" class="form-control">
          <?php
          foreach (wc_get_order_statuses() as $key => $status) {
            $order_key_status = str_replace('wc-', '', $key);
          ?>
            <option value="<?php echo $order_key_status; ?>" <?php echo isset($settings[Constants::DOMAIN . '_' . Constants::MAP_ORDERS]) && $settings[Constants::DOMAIN . '_' . Constants::MAP_ORDERS] == $order_key_status ? 'selected' : ''; ?>>
              <?php echo $status; ?>
            </option>
          <?php
          }
          ?>
        </select>
      </div>
    </div>
  </div>
</div>