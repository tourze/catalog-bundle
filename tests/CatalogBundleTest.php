<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CatalogBundle\CatalogBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(CatalogBundle::class)]
#[RunTestsInSeparateProcesses]
final class CatalogBundleTest extends AbstractBundleTestCase
{
}
