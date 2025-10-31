<?php

namespace Tourze\CatalogBundle;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\DoctrineSnowflakeBundle\DoctrineSnowflakeBundle;
use Tourze\DoctrineTimestampBundle\DoctrineTimestampBundle;
use Tourze\EasyAdminMenuBundle\EasyAdminMenuBundle;
use Tourze\EasyAdminTreeSelectFieldBundle\EasyAdminTreeSelectFieldBundle;
use Tourze\FileStorageBundle\FileStorageBundle;
use Tourze\JsonRPCPaginatorBundle\JsonRPCPaginatorBundle;

class CatalogBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            DoctrineBundle::class => ['all' => true],
            DoctrineSnowflakeBundle::class => ['all' => true],
            DoctrineTimestampBundle::class => ['all' => true],
            TwigBundle::class => ['all' => true],
            EasyAdminBundle::class => ['all' => true],
            EasyAdminMenuBundle::class => ['all' => true],
            EasyAdminTreeSelectFieldBundle::class => ['all' => true],
            FileStorageBundle::class => ['all' => true],
            JsonRPCPaginatorBundle::class => ['all' => true],
        ];
    }
}
