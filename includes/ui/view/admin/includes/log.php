<?php

use Voucherly\Api\Manager\LogManager;
use Voucherly\Plugin\Constants;

$log_manager = new LogManager;
$url_log = $log_manager->getLatestLog();

if (!empty($url_log)) {
?>
<a href="<?php echo $url_log; ?>" class="btn btn-primary" target="_blank">
  <?php echo __('Scarica log',Constants::DOMAIN); ?>
</a>
<?php
}
?>