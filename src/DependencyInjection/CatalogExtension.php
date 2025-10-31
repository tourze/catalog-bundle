<?php

namespace Tourze\CatalogBundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

class CatalogExtension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }
}
