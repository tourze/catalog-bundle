<?php

namespace Tourze\CatalogBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\CatalogBundle\DependencyInjection\CatalogExtension;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(CatalogExtension::class)]
final class CatalogExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
}
