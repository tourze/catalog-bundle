<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Constraint\Callback;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tourze\CatalogBundle\Entity\Catalog;
use Tourze\CatalogBundle\Entity\CatalogType;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(CatalogType::class)]
final class CatalogTypeTest extends AbstractEntityTestCase
{
    protected function createEntity(): CatalogType
    {
        return new CatalogType();
    }

    /**
     * @return iterable<string, array{string, string|bool}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'code' => ['code', 'product_type'];
        yield 'name' => ['name', '商品分类'];
        yield 'description' => ['description', '这是商品分类的描述'];
        yield 'enabled' => ['enabled', false];
    }

    public function testCatalogRelationship(): void
    {
        $catalogType = $this->createEntity();
        $this->assertInstanceOf(CatalogType::class, $catalogType);

        $catalog1 = $this->createMock(Catalog::class);
        $catalog2 = $this->createMock(Catalog::class);

        $catalog1->expects($this->once())->method('setType')->with($catalogType);
        $catalog2->expects($this->exactly(2))
            ->method('setType')
            ->with(new Callback(fn ($arg) => $arg === $catalogType || null === $arg))
        ;
        $catalog2->expects($this->once())->method('getType')->willReturn($catalogType);

        $catalogType->addCatalog($catalog1);
        $catalogType->addCatalog($catalog2);

        $this->assertCount(2, $catalogType->getCatalogs());
        $this->assertTrue($catalogType->getCatalogs()->contains($catalog1));
        $this->assertTrue($catalogType->getCatalogs()->contains($catalog2));

        $catalogType->addCatalog($catalog1);
        $this->assertCount(2, $catalogType->getCatalogs());

        $catalogType->removeCatalog($catalog2);
        $this->assertCount(1, $catalogType->getCatalogs());
        $this->assertFalse($catalogType->getCatalogs()->contains($catalog2));
    }

    public function testToString(): void
    {
        $catalogType = $this->createEntity();

        /** @var CatalogType $catalogType */
        $this->assertInstanceOf(CatalogType::class, $catalogType);

        $name = '文章分类';
        $catalogType->setName($name);

        $this->assertSame($name, $catalogType->__toString());
    }
}
