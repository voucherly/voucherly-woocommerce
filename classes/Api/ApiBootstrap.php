<?php

namespace Voucherly\Api;

use Voucherly\Plugin\AdminSettings;
use Voucherly\Plugin\Constants;
use Voucherly\Api\ApiLogger;

/**
 * API boostrap calls
 */
class ApiBootstrap
{
    /**
     * @var AccessTokenProvider
     */
    protected $accessTokenProvider;
    /**
     * @var ApiLogger
     */
    protected $logger;

    public function getAccessTokenProvider()
    {
        return $this->accessTokenProvider;
    }

    public function getLogger()
    {
        return $this->logger;
    }

    public static function bootStrap()
    {
        $boot = new self();
        $boot->init();

        return $boot;
    }

    public function init()
    {
        $this->prepareLogger();
        $this->setAccesTokenProvider();
        $this->accessTokenProvider->bootstrap();
    }

    public function getEnvironment()
    {
        return AdminSettings::exists(Constants::LIVE_API) ? Constants::API_LIVE_ENV : Constants::API_SAND_ENV;
    }

    protected function prepareLogger()
    {
        $environment = $this->getEnvironment();

        $isActiveLog = AdminSettings::exists(Constants::LOG) === true
                        || $environment == Constants::API_SAND_ENV;

        $logPath = Constants::PLUGIN_FOLDER_PATH . '/log';

        if (!file_exists($logPath)) {
            if (!mkdir($logPath, 0755) && !is_dir($logPath)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $logPath));
            }
        }

        $this->logger = new ApiLogger(
            $logPath, $isActiveLog
        );
    }

    protected function setAccesTokenProvider()
    {
        $env = $this->getEnvironment();

        $apiKeyKey = $env == Constants::API_LIVE_ENV 
            ? Constants::API_KEY 
            : Constants::API_KEY_SAND;

        AdminSettings::update(
            Constants::API_TOKEN,
            AdminSettings::get($apiKeyKey)
        );
        $this->accessTokenProvider = new AccessTokenProvider(
            $this->logger,
            '',
            '',
            $env
        );
    }
}
