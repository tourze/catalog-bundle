<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Tests\Param;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Tourze\CatalogBundle\Param\GetCatalogListParam;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;

/**
 * GetCatalogListParam 单元测试
 *
 * @internal
 */
#[CoversClass(GetCatalogListParam::class)]
final class GetCatalogListParamTest extends TestCase
{
    public function testImplementsRpcParamInterface(): void
    {
        $param = new GetCatalogListParam();

        $this->assertInstanceOf(RpcParamInterface::class, $param);
    }

    public function testConstructorWithDefaultValues(): void
    {
        $param = new GetCatalogListParam();

        $this->assertNull($param->typeCode);
        $this->assertNull($param->parentId);
        $this->assertNull($param->keyword);
        $this->assertTrue($param->enabledOnly);
        $this->assertFalse($param->includeChildrenCount);
        $this->assertSame('sortOrder', $param->orderBy);
        $this->assertSame('ASC', $param->orderDir);
    }

    public function testConstructorWithCustomValues(): void
    {
        $param = new GetCatalogListParam(
            typeCode: 'product',
            parentId: '123',
            keyword: 'test',
            enabledOnly: false,
            includeChildrenCount: true,
            orderBy: 'name',
            orderDir: 'DESC',
        );

        $this->assertSame('product', $param->typeCode);
        $this->assertSame('123', $param->parentId);
        $this->assertSame('test', $param->keyword);
        $this->assertFalse($param->enabledOnly);
        $this->assertTrue($param->includeChildrenCount);
        $this->assertSame('name', $param->orderBy);
        $this->assertSame('DESC', $param->orderDir);
    }

    public function testClassIsReadonly(): void
    {
        $reflection = new \ReflectionClass(GetCatalogListParam::class);

        $this->assertTrue($reflection->isReadOnly());
    }

    public function testValidationPassesWithValidOrderBy(): void
    {
        $validOrderBys = ['name', 'sortOrder', 'createTime', 'updateTime'];
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        foreach ($validOrderBys as $orderBy) {
            $param = new GetCatalogListParam(orderBy: $orderBy);
            $violations = $validator->validate($param);
            $this->assertCount(0, $violations, "orderBy '{$orderBy}' should be valid");
        }
    }

    public function testValidationFailsWithInvalidOrderBy(): void
    {
        $param = new GetCatalogListParam(orderBy: 'invalid');

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
            $param = new GetCatalogListParam(orderDir: $orderDir);
            $violations = $validator->validate($param);
            $this->assertCount(0, $violations, "orderDir '{$orderDir}' should be valid");
        }
    }
}
