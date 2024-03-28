<?php
use Voucherly\Plugin\Constants;
?>
<div class="woocommerce-notices-wrapper">
	<div class="woocommerce-error" role="alert">
    <?php
      echo __('Si è verificato un errore di pagamento, riprova!',Constants::DOMAIN);
    ?>
  </div>
</div>