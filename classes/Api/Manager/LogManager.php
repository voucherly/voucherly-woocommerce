<?php

namespace Voucherly\Api\Manager;

use Voucherly\Plugin\Constants;

class LogManager
{
  /**
   * @var array
   */
  const HIDDEN_FILES = [
    '.',
    '..',
    '.DS_Store',
  ];

  public function getLatestLog(): string
  {
    $logs = array_diff(
      scandir(Constants::LOG_FOLDER),
      self::HIDDEN_FILES
    );

    if (empty($logs)) {
      return '';
    }

    $logs = array_reverse(
      $logs
    );

    return Constants::LOG_WEB_FOLDER . $logs[0];
  }
}
