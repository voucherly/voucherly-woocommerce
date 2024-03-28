<?php

namespace Voucherly\Api;

use Voucherly\Api\Manager\GatewayManager;

/**
 * Instantiate a specific library API passed as argument
 */
class ApiCreator
{
    public static function createApiService(
        string $apiClassName
    ) {
        $bootrap = ApiBootstrap::bootStrap();
        return new $apiClassName(
            $bootrap->getAccessTokenProvider()
        );
    }

    public static function getGatewayInstance(): GatewayManager {
        return self::createApiService(GatewayManager::class);
    }
}
