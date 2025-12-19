<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Tests\Param;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Tourze\CatalogBundle\Param\GetCatalogTreeParam;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;

/**
 * GetCatalogTreeParam 单元测试
 *
 * @internal
 */
#[CoversClass(GetCatalogTreeParam::class)]
final class GetCatalogTreeParamTest extends TestCase
{
    public function testImplementsRpcParamInterface(): void
    {
        $param = new GetCatalogTreeParam();

        $this->assertInstanceOf(RpcParamInterface::class, $param);
    }

    public function testConstructorWithDefaultValues(): void
    {
        $param = new GetCatalogTreeParam();

        $this->assertNull($param->typeId);
        $this->assertSame(5, $param->maxLevel);
        $this->assertTrue($param->enabledOnly);
        $this->assertFalse($param->includeMetadata);
    }

    public function testConstructorWithCustomValues(): void
    {
        $param = new GetCatalogTreeParam(
            typeId: 'type-123',
            maxLevel: 3,
            enabledOnly: false,
            includeMetadata: true,
        );

        $this->assertSame('type-123', $param->typeId);
        $this->assertSame(3, $param->maxLevel);
        $this->assertFalse($param->enabledOnly);
        $this->assertTrue($param->includeMetadata);
    }

    public function testClassIsReadonly(): void
    {
        $reflection = new \ReflectionClass(GetCatalogTreeParam::class);

        $this->assertTrue($reflection->isReadOnly());
    }

    public function testValidationPassesWithValidMaxLevel(): void
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        for ($level = 1; $level <= 10; $level++) {
            $param = new GetCatalogTreeParam(maxLevel: $level);
            $violations = $validator->validate($param);
            $this->assertCount(0, $violations, "maxLevel {$level} should be valid");
        }
    }

    public function testValidationFailsWithMaxLevelBelowMin(): void
    {
        $param = new GetCatalogTreeParam(maxLevel: 0);

        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $violations = $validator->validate($param);

        $this->assertGreaterThan(0, count($violations));
    }

    public function testValidationFailsWithMaxLevelAboveMax(): void
    {
        $param = new GetCatalogTreeParam(maxLevel: 11);

        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $violations = $validator->validate($param);

        $this->assertGreaterThan(0, count($violations));
    }
}
