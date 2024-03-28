<?php

namespace Voucherly\Api;

use Analog\Handler\File;
use Analog\Logger;

/**
 * Api logger class
 */
class ApiLogger
{

  protected $path;
  /**
   * @var Logger|null
   */
  private $logger;

  public function __construct($path, $enabled)
  {
    $today = new \DateTime();
    $this->path = $path . DIRECTORY_SEPARATOR . $today->format('Ymd') . '.log';
    if (true === $enabled) {
      $this->logger = new Logger();
      $this->logger->handler(File::init($this->path));
    }
  }

  public function critical($message, $context = [])
  {
    if (null === $this->logger) {
      return;
    }
    $this->logger->critical($message, $context);
  }

  public function error($message, $context = [])
  {
    if (null === $this->logger) {
      return;
    }
    $this->logger->error($message, $context);
  }

  public function info($message, $context = [])
  {
    if (null === $this->logger) {
      return;
    }
    $this->logger->info($message, $context);
  }

  /**
   * @param object $object
   * @param string $method
   *
   * @return void
   */
  public function infoCall($object, $method)
  {
    $this->info('call ' . get_class($object) . '->' . $method);
  }

  public function warning($message, $context = [])
  {
    if (null === $this->logger) {
      return;
    }
    $this->logger->warning($message, $context);
  }
}
