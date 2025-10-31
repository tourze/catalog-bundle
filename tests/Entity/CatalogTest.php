<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tourze\CatalogBundle\Entity\Catalog;
use Tourze\CatalogBundle\Entity\CatalogType;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(Catalog::class)]
final class CatalogTest extends AbstractEntityTestCase
{
    protected function createEntity(): Catalog
    {
        $type = new CatalogType();
        $type->setCode('test-type-' . uniqid());
        $type->setName('Test Type');

        $catalog = new Catalog();
        $catalog->setType($type);
        $catalog->setName('Test Catalog');

        return $catalog;
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'name' => ['name', '测试分类'];
        yield 'description' => ['description', '这是一个测试分类'];
        yield 'sortOrder' => ['sortOrder', 10];
        yield 'level' => ['level', 1];
        yield 'path' => ['path', 'parent/child'];
        yield 'enabled' => ['enabled', false];
        yield 'metadata' => ['metadata', ['key' => 'value']];
        yield 'thumb' => ['thumb', '/uploads/catalogs/thumb.jpg'];
    }

    public function testParentChildRelationship(): void
    {
        $parent = $this->createEntity();
        $parent->setLevel(0);
        $parent->setPath('parent');

        // 使用反射设置ID以便路径计算正确工作
        $parentReflection = new \ReflectionClass($parent);
        $idProperty = $parentReflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($parent, 'parent');

        $child = $this->createEntity();

        // 使用反射设置子实体的ID
        $childReflection = new \ReflectionClass($child);
        $childIdProperty = $childReflection->getProperty('id');
        $childIdProperty->setAccessible(true);
        $childIdProperty->setValue($child, 'child');

        $child->setParent($parent);

        $this->assertSame($parent, $child->getParent());
        $this->assertSame(1, $child->getLevel());
        $this->assertSame('parent/child', $child->getPath());
        $this->assertTrue($parent->getChildren()->contains($child));
    }

    public function testAddRemoveChildren(): void
    {
        $parent = $this->createEntity();
        $child1 = $this->createEntity();
        $child2 = $this->createEntity();

        $parent->addChild($child1);
        $parent->addChild($child2);

        $this->assertCount(2, $parent->getChildren());
        $this->assertTrue($parent->getChildren()->contains($child1));
        $this->assertTrue($parent->getChildren()->contains($child2));

        $parent->addChild($child1);
        $this->assertCount(2, $parent->getChildren());

        $parent->removeChild($child2);
        $this->assertCount(1, $parent->getChildren());
        $this->assertFalse($parent->getChildren()->contains($child2));
        $this->assertNull($child2->getParent());
    }

    public function testAncestorMethods(): void
    {
        $grandparent = $this->createEntity();
        $parent = $this->createEntity();
        $child = $this->createEntity();
        $sibling = $this->createEntity();

        $parent->setParent($grandparent);
        $child->setParent($parent);
        $sibling->setParent($parent);

        $ancestors = $child->getAncestors();
        $this->assertCount(2, $ancestors);
        $this->assertSame($grandparent, $ancestors[0]);
        $this->assertSame($parent, $ancestors[1]);

        $ancestorIds = $child->getAncestorIds();
        $this->assertCount(0, $ancestorIds);

        $this->assertTrue($grandparent->isAncestorOf($child));
        $this->assertTrue($parent->isAncestorOf($child));
        $this->assertFalse($sibling->isAncestorOf($child));

        $this->assertTrue($child->isDescendantOf($grandparent));
        $this->assertTrue($child->isDescendantOf($parent));
        $this->assertFalse($child->isDescendantOf($sibling));
    }

    public function testToString(): void
    {
        $catalog = $this->createEntity();
        $name = '手机';

        $catalog->setName($name);

        $this->assertSame($name, (string) $catalog);
    }
}
