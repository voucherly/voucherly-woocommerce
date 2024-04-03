<?php
/**
 * 
 * This would be the administrator page in wp-admin
 * 
 */
use Voucherly\Plugin\AdminSettings;
use Voucherly\Plugin\Constants;

$admin_settings = new AdminSettings();
$save_result = $admin_settings->saveSettings();
$settings = $admin_settings->loadSettings();

?>
<div class="container pt-4">
  <h1>
    <?php echo Constants::PLUGIN_NAME.' - '.__('impostazioni',Constants::DOMAIN); ?>
  </h1>

  <?php 
  if(false===$save_result){
    ?>
    <p class="text-danger font-weight-bold text-center">
      <?php echo __('Impossibile salvare in quanto la chiave di Google Maps risulta non valida',Constants::DOMAIN); ?>
    </p>
    <?php
  }?>

  <form action="" method="POST" class="row mt-4 line_price">
    <div class="col-12 mb-3">
      <div class="form-check p-0">
        <label class="form-check-label" for="live_api"><?php echo __('Ambiente live',Constants::DOMAIN); ?></label>
        <input type="checkbox" class="form-check-input ml-2 mt-1" name="live_api" <?php echo isset($settings[Constants::DOMAIN.'_'.Constants::LIVE_API]) ? 'checked' : ''; ?>>
      </div>
    </div>
    <div class="col-lg-4 col-12 mb-3">
      <label>
        <?php echo __('API KEY live',Constants::DOMAIN); ?>
      </label>
      <input type="text" name="api_key" class="form-control" value="<?php echo $settings[Constants::DOMAIN.'_'.Constants::API_KEY] ?? ''; ?>">
    </div>
    <div class="col-lg-4 col-12 mb-3">
      <label>
        <?php echo __('API KEY sandbox',Constants::DOMAIN); ?>
      </label>
      <input type="text" name="api_key_sand" class="form-control" value="<?php echo $settings[Constants::DOMAIN.'_'.Constants::API_KEY_SAND] ?? ''; ?>">
    </div>
    <div class="col-lg-6 col-12 mb-3 d-none">
      <label>
        <?php echo __('Url plugin prod',Constants::DOMAIN); ?>
      </label>
      <input type="text" name="url_plugin_prod" class="form-control" value="<?php echo Constants::API_URL; ?>" disabled>
    </div>
    <div class="col-12 mb-3 d-none">
      <label>
        <?php echo __('Chiave segreta web service WooCommerce',Constants::DOMAIN); ?>
      </label>
      <input type="text" name="ws_key_secret" class="form-control" value="<?php echo $settings[Constants::DOMAIN.'_'.Constants::WS_KEY_SECRET] ?? ''; ?>">
    </div>
    <div class="col-12 mb-3 d-none">
      <label>
        <?php echo __('Chiave client web service WooCommerce',Constants::DOMAIN); ?>
      </label>
      <input type="text" name="ws_key" class="form-control" value="<?php echo $settings[Constants::DOMAIN.'_'.Constants::WS_KEY] ?? ''; ?>">
    </div>
    <?php
      include_once(__DIR__.'/includes/mapping_is_food.php');
    ?>
    <div class="col-12 pt-3 d-flex justify-content-center">
      <button class="btn btn-primary" name="save">
        <?php echo __('Salva impostazioni',Constants::DOMAIN); ?>
      </button>
    </div>
  </form>
</div>