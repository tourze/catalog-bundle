<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Tests\Param;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Tourze\CatalogBundle\Param\GetCatalogDetailParam;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;

/**
 * GetCatalogDetailParam 单元测试
 *
 * @internal
 */
#[CoversClass(GetCatalogDetailParam::class)]
final class GetCatalogDetailParamTest extends TestCase
{
    public function testImplementsRpcParamInterface(): void
    {
        $param = new GetCatalogDetailParam(catalogId: 'test-id');

        $this->assertInstanceOf(RpcParamInterface::class, $param);
    }

    public function testConstructorWithRequiredParameterOnly(): void
    {
        $param = new GetCatalogDetailParam(catalogId: '123');

        $this->assertSame('123', $param->catalogId);
        $this->assertFalse($param->includeAncestors);
        $this->assertFalse($param->includeChildren);
        $this->assertFalse($param->includeSiblings);
        $this->assertTrue($param->enabledOnly);
    }

    public function testConstructorWithAllParameters(): void
    {
        $param = new GetCatalogDetailParam(
            catalogId: 'cat-456',
            includeAncestors: true,
            includeChildren: true,
            includeSiblings: true,
            enabledOnly: false,
        );

        $this->assertSame('cat-456', $param->catalogId);
        $this->assertTrue($param->includeAncestors);
        $this->assertTrue($param->includeChildren);
        $this->assertTrue($param->includeSiblings);
        $this->assertFalse($param->enabledOnly);
    }

    public function testClassIsReadonly(): void
    {
        $reflection = new \ReflectionClass(GetCatalogDetailParam::class);

        $this->assertTrue($reflection->isReadOnly());
    }

    public function testPropertiesArePublicReadonly(): void
    {
        $reflection = new \ReflectionClass(GetCatalogDetailParam::class);

        $properties = ['catalogId', 'includeAncestors', 'includeChildren', 'includeSiblings', 'enabledOnly'];

        foreach ($properties as $propertyName) {
            $property = $reflection->getProperty($propertyName);
            $this->assertTrue($property->isPublic(), "{$propertyName} should be public");
            $this->assertTrue($property->isReadOnly(), "{$propertyName} should be readonly");
        }
    }

    public function testValidationFailsWhenCatalogIdIsBlank(): void
    {
        $param = new GetCatalogDetailParam(catalogId: '');

        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $violations = $validator->validate($param);

        $this->assertGreaterThan(0, count($violations));
        $this->assertSame('catalogId', $violations->get(0)->getPropertyPath());
    }

    public function testValidationPassesWithValidCatalogId(): void
    {
        $param = new GetCatalogDetailParam(catalogId: 'valid-id');

        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $violations = $validator->validate($param);

        $this->assertCount(0, $violations);
    }

    public function testHasMethodParamAttributes(): void
    {
        $reflection = new \ReflectionClass(GetCatalogDetailParam::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);

        foreach ($constructor->getParameters() as $parameter) {
            $attrs = $parameter->getAttributes(\Tourze\JsonRPC\Core\Attribute\MethodParam::class);
            $this->assertNotEmpty($attrs, "Parameter {$parameter->getName()} should have MethodParam attribute");
        }
    }
}
