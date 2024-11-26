<?php

declare(strict_types=1);

namespace LmcCors;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencyConfig(),
            'lmc_cors'     => $this->getModuleConfig(),
        ];
    }

    public function getDependencyConfig(): array
    {
        return [
            'factories' => [
                Mvc\CorsRequestListener::class => Factory\CorsRequestListenerFactory::class,
                Options\CorsOptions::class     => Factory\CorsOptionsFactory::class,
                Service\CorsService::class     => Factory\CorsServiceFactory::class,
            ],
        ];
    }

    public function getModuleConfig(): array
    {
        return [];
    }
}
