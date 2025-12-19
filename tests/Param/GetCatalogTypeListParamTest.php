<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Tests\Param;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Tourze\CatalogBundle\Param\GetCatalogTypeListParam;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;

/**
 * GetCatalogTypeListParam 单元测试
 *
 * @internal
 */
#[CoversClass(GetCatalogTypeListParam::class)]
final class GetCatalogTypeListParamTest extends TestCase
{
    public function testImplementsRpcParamInterface(): void
    {
        $param = new GetCatalogTypeListParam();

        $this->assertInstanceOf(RpcParamInterface::class, $param);
    }

    public function testConstructorWithDefaultValues(): void
    {
        $param = new GetCatalogTypeListParam();

        $this->assertNull($param->keyword);
        $this->assertTrue($param->enabledOnly);
        $this->assertFalse($param->includeCatalogCount);
        $this->assertSame('createTime', $param->orderBy);
        $this->assertSame('DESC', $param->orderDir);
    }

    public function testConstructorWithCustomValues(): void
    {
        $param = new GetCatalogTypeListParam(
            keyword: 'test',
            enabledOnly: false,
            includeCatalogCount: true,
            orderBy: 'name',
            orderDir: 'ASC',
        );

        $this->assertSame('test', $param->keyword);
        $this->assertFalse($param->enabledOnly);
        $this->assertTrue($param->includeCatalogCount);
        $this->assertSame('name', $param->orderBy);
        $this->assertSame('ASC', $param->orderDir);
    }

    public function testClassIsReadonly(): void
    {
        $reflection = new \ReflectionClass(GetCatalogTypeListParam::class);

        $this->assertTrue($reflection->isReadOnly());
    }

    public function testValidationPassesWithValidOrderBy(): void
    {
        $validOrderBys = ['name', 'code', 'createTime', 'updateTime'];
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        foreach ($validOrderBys as $orderBy) {
            $param = new GetCatalogTypeListParam(orderBy: $orderBy);
            $violations = $validator->validate($param);
            $this->assertCount(0, $violations, "orderBy '{$orderBy}' should be valid");
        }
    }

    public function testValidationFailsWithInvalidOrderBy(): void
    {
        $param = new GetCatalogTypeListParam(orderBy: 'invalid');

        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $violations = $validator->validate($param);

        $this->assertGreaterThan(0, count($violations));
    }

    public function testValidationPassesWithValidOrderDir(): void
    {
        $validOrderDirs = ['ASC', 'DESC'];
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        foreach ($validOrderDirs as $orderDir) {
            $param = new GetCatalogTypeListParam(orderDir: $orderDir);
            $violations = $validator->validate($param);
            $this->assertCount(0, $violations, "orderDir '{$orderDir}' should be valid");
        }
    }

    public function testValidationFailsWithInvalidOrderDir(): void
    {
        $param = new GetCatalogTypeListParam(orderDir: 'invalid');

        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $violations = $validator->validate($param);

        $this->assertGreaterThan(0, count($violations));
    }
}
