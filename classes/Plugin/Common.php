<?php

namespace Voucherly\Plugin;

/**
 * Common facilities
 */
class Common{

  public function getMappedStatusesOrder(){
    $mapping = explode(
      "\n",
      AdminSettings::get(Constants::MAP_ORDERS)
    );

    $statuses = [];
    foreach ($mapping as $row) {
      [$id_order_status, $isendu_status_name] = explode(',', $row);
      $statuses[$id_order_status] = trim($isendu_status_name);
    }

    return $statuses;
  }

  public function getMappedCarriers(): array
    {
        $mapping = AdminSettings::exists(Constants::MAP_CARRIERS) ? json_decode(
            AdminSettings::get(Constants::MAP_CARRIERS),
            true
        ) : [];

        return $mapping;
    }
}
