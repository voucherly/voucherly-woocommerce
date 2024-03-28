<?php

namespace Voucherly\Api;

trait TraitEnv
{
    protected function setupEnv($env)
    {
        $this->env = (int) $env;
        $environment = '';
        if (0 === (int) $env) {
            $environment = 'SAND';
        } else {
            $environment = 'LIVE';
        }
        $this->logger->info('ENVIRONMENT: '.$environment);
    }
}
