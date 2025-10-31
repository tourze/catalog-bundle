<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CatalogBundle\Entity\Catalog;
use Tourze\CatalogBundle\Entity\CatalogType;
use Tourze\CatalogBundle\Repository\CatalogRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(CatalogRepository::class)]
#[RunTestsInSeparateProcesses]
final class CatalogRepositoryTest extends AbstractRepositoryTestCase
{
    private ?CatalogRepository $repository = null;

    protected function onSetUp(): void
    {
        $repository = self::getService(CatalogRepository::class);
        $this->assertInstanceOf(CatalogRepository::class, $repository);
        $this->repository = $repository;
    }

    protected function onTearDown(): void
    {
        $this->repository = null;
    }

    private function getCatalogRepository(): CatalogRepository
    {
        $repository = $this->repository;
        if (null === $repository) {
            self::fail('Repository not initialized');
        }

        return $repository;
    }

    private function createTestType(string $code): CatalogType
    {
        $type = new CatalogType();
        $type->setCode($code);
        $type->setName('Test Type ' . $code);

        self::getEntityManager()->persist($type);
        self::getEntityManager()->flush();

        return $type;
    }

    public function testFindWithInvalidIdShouldReturnNull(): void
    {
        $result = $this->getCatalogRepository()->find('non-existent-id');

        $this->assertNull($result);
    }

    public function testFindRootsByType(): void
    {
        $type = $this->createTestType('test_roots');

        $root1 = new Catalog();
        $root1->setType($type);
        $root1->setName('Root 1');
        $root1->setSortOrder(2);

        $root2 = new Catalog();
        $root2->setType($type);
        $root2->setName('Root 2');
        $root2->setSortOrder(1);

        $child = new Catalog();
        $child->setType($type);
        $child->setName('Child');
        $child->setParent($root1);

        $repository = $this->getCatalogRepository();
        $repository->save($root1);
        $repository->save($root2);
        $repository->save($child, true);

        $roots = $repository->findRootsByType($type);

        $this->assertCount(2, $roots);
        $this->assertSame('Root 2', $roots[0]->getName());
        $this->assertSame('Root 1', $roots[1]->getName());

        self::getEntityManager()->remove($child);
        self::getEntityManager()->remove($root1);
        self::getEntityManager()->remove($root2);
        self::getEntityManager()->remove($type);
        self::getEntityManager()->flush();
    }

    public function testFindEnabledRootsByType(): void
    {
        $type = $this->createTestType('test_enabled_roots');

        $enabledRoot = new Catalog();
        $enabledRoot->setType($type);
        $enabledRoot->setName('Enabled Root');
        $enabledRoot->setEnabled(true);

        $disabledRoot = new Catalog();
        $disabledRoot->setType($type);
        $disabledRoot->setName('Disabled Root');
        $disabledRoot->setEnabled(false);

        $repository = $this->getCatalogRepository();
        $repository->save($enabledRoot);
        $repository->save($disabledRoot, true);

        $roots = $repository->findEnabledRootsByType($type);

        $this->assertCount(1, $roots);
        $this->assertSame('Enabled Root', $roots[0]->getName());

        self::getEntityManager()->remove($enabledRoot);
        self::getEntityManager()->remove($disabledRoot);
        self::getEntityManager()->remove($type);
        self::getEntityManager()->flush();
    }

    public function testFindChildrenOf(): void
    {
        $type = $this->createTestType('test_children');

        $parent = new Catalog();
        $parent->setType($type);
        $parent->setName('Parent');

        $child1 = new Catalog();
        $child1->setType($type);
        $child1->setName('Child 1');
        $child1->setParent($parent);
        $child1->setSortOrder(2);

        $child2 = new Catalog();
        $child2->setType($type);
        $child2->setName('Child 2');
        $child2->setParent($parent);
        $child2->setSortOrder(1);

        $repository = $this->getCatalogRepository();
        $repository->save($parent);
        $repository->save($child1);
        $repository->save($child2, true);

        $children = $repository->findChildrenOf($parent);

        $this->assertCount(2, $children);
        $this->assertSame('Child 2', $children[0]->getName());
        $this->assertSame('Child 1', $children[1]->getName());

        self::getEntityManager()->remove($child1);
        self::getEntityManager()->remove($child2);
        self::getEntityManager()->remove($parent);
        self::getEntityManager()->remove($type);
        self::getEntityManager()->flush();
    }

    public function testFind(): void
    {
        $type = $this->createTestType('test_find');

        $catalog = new Catalog();
        $catalog->setType($type);
        $catalog->setName('Test Catalog');

        $this->getCatalogRepository()->save($catalog, true);
        $catalogId = $catalog->getId();

        $found = $this->getCatalogRepository()->find($catalogId);

        $this->assertNotNull($found);
        $this->assertSame('Test Catalog', $found->getName());

        $notFound = $this->getCatalogRepository()->find('non-existent-id');
        $this->assertNull($notFound);

        self::getEntityManager()->remove($catalog);
        self::getEntityManager()->remove($type);
        self::getEntityManager()->flush();
    }

    public function testFindOneByPath(): void
    {
        $type = $this->createTestType('test_path');

        $parent = new Catalog();
        $parent->setType($type);
        $parent->setName('Parent');

        $this->getCatalogRepository()->save($parent, true);

        // 手动设置根级分类的路径
        $parent->setPath((string) $parent->getId());
        $this->getCatalogRepository()->save($parent, true);

        $child = new Catalog();
        $child->setType($type);
        $child->setName('Child');
        $child->setParent($parent);

        $this->getCatalogRepository()->save($child, true);

        // 手动设置子分类的路径
        $child->setPath($parent->getPath() . '/' . (string) $child->getId());
        $this->getCatalogRepository()->save($child, true);

        $this->assertStringContainsString('/', $child->getPath() ?? '');

        $found = $this->getCatalogRepository()->findOneByPath($child->getPath() ?? '');

        $this->assertNotNull($found);
        $this->assertSame('Child', $found->getName());

        $notFound = $this->getCatalogRepository()->findOneByPath('non/existent/path');
        $this->assertNull($notFound);

        self::getEntityManager()->remove($child);
        self::getEntityManager()->remove($parent);
        self::getEntityManager()->remove($type);
        self::getEntityManager()->flush();
    }

    public function testGetMaxSortOrder(): void
    {
        $type = $this->createTestType('test_sort_order');

        $parent = new Catalog();
        $parent->setType($type);
        $parent->setName('Parent');

        $child1 = new Catalog();
        $child1->setType($type);
        $child1->setName('Child 1');
        $child1->setParent($parent);
        $child1->setSortOrder(10);

        $child2 = new Catalog();
        $child2->setType($type);
        $child2->setName('Child 2');
        $child2->setParent($parent);
        $child2->setSortOrder(20);

        $this->getCatalogRepository()->save($parent);
        $this->getCatalogRepository()->save($child1);
        $this->getCatalogRepository()->save($child2, true);

        $maxOrder = $this->getCatalogRepository()->getMaxSortOrder($parent, $type);
        $this->assertSame(20, $maxOrder);

        $maxOrderRoot = $this->getCatalogRepository()->getMaxSortOrder(null, $type);
        $this->assertSame(0, $maxOrderRoot);

        self::getEntityManager()->remove($child1);
        self::getEntityManager()->remove($child2);
        self::getEntityManager()->remove($parent);
        self::getEntityManager()->remove($type);
        self::getEntityManager()->flush();
    }

    public function testFindTreeArrayByType(): void
    {
        $type = $this->createTestType('test_tree');

        $root = new Catalog();
        $root->setType($type);
        $root->setName('Root');

        $child = new Catalog();
        $child->setType($type);
        $child->setName('Child');
        $child->setParent($root);

        $grandchild = new Catalog();
        $grandchild->setType($type);
        $grandchild->setName('Grandchild');
        $grandchild->setParent($child);

        $this->getCatalogRepository()->save($root);
        $this->getCatalogRepository()->save($child);
        $this->getCatalogRepository()->save($grandchild, true);

        $tree = $this->getCatalogRepository()->findTreeArrayByType($type);

        $this->assertCount(3, $tree);

        $rootNode = $tree[0];
        $this->assertSame('Root', $rootNode['name']);
        $this->assertSame(0, $rootNode['level']);
        $this->assertNull($rootNode['parent_id']);

        $childNode = $tree[1];
        $this->assertSame('Child', $childNode['name']);
        $this->assertSame(1, $childNode['level']);
        $this->assertSame($rootNode['id'], $childNode['parent_id']);

        $grandchildNode = $tree[2];
        $this->assertSame('Grandchild', $grandchildNode['name']);
        $this->assertSame(2, $grandchildNode['level']);
        $this->assertSame($childNode['id'], $grandchildNode['parent_id']);

        self::getEntityManager()->remove($grandchild);
        self::getEntityManager()->remove($child);
        self::getEntityManager()->remove($root);
        self::getEntityManager()->remove($type);
        self::getEntityManager()->flush();
    }

    public function testCreateTreeQueryBuilder(): void
    {
        $type = $this->createTestType('test_tree_qb');

        $qb = $this->getCatalogRepository()->createTreeQueryBuilder($type);

        $this->assertInstanceOf(QueryBuilder::class, $qb);

        $dql = $qb->getDQL();
        $this->assertStringContainsString('c.type = :type', $dql);
        $this->assertStringContainsString('ORDER BY c.level ASC, c.sortOrder ASC, c.name ASC', $dql);

        $parameters = $qb->getParameters();
        $typeParam = null;
        foreach ($parameters as $param) {
            if ('type' === $param->getName()) {
                $typeParam = $param;
                break;
            }
        }
        $this->assertNotNull($typeParam);
        $this->assertEquals($type, $typeParam->getValue());

        $qbEnabled = $this->getCatalogRepository()->createTreeQueryBuilder($type, true);
        $dqlEnabled = $qbEnabled->getDQL();
        $this->assertStringContainsString('c.enabled = :enabled', $dqlEnabled);

        self::getEntityManager()->remove($type);
        self::getEntityManager()->flush();
    }

    public function testFindAllDescendantsOf(): void
    {
        $type = $this->createTestType('test_descendants');

        $root = new Catalog();
        $root->setType($type);
        $root->setName('Root Descendants');

        $this->getCatalogRepository()->save($root, true);
        $root->setPath((string) $root->getId());
        $this->getCatalogRepository()->save($root, true);

        $child = new Catalog();
        $child->setType($type);
        $child->setName('Child Descendants');
        $child->setParent($root);

        $this->getCatalogRepository()->save($child, true);
        $child->setPath($root->getPath() . '/' . (string) $child->getId());
        $this->getCatalogRepository()->save($child, true);

        $grandchild = new Catalog();
        $grandchild->setType($type);
        $grandchild->setName('Grandchild Descendants');
        $grandchild->setParent($child);

        $this->getCatalogRepository()->save($grandchild, true);
        $grandchild->setPath($child->getPath() . '/' . (string) $grandchild->getId());
        $this->getCatalogRepository()->save($grandchild, true);

        $descendants = $this->getCatalogRepository()->findAllDescendantsOf($root);

        $this->assertCount(2, $descendants);
        $descendantNames = array_map(fn ($catalog) => $catalog->getName(), $descendants);
        $this->assertContains('Child Descendants', $descendantNames);
        $this->assertContains('Grandchild Descendants', $descendantNames);

        self::getEntityManager()->remove($grandchild);
        self::getEntityManager()->remove($child);
        self::getEntityManager()->remove($root);
        self::getEntityManager()->remove($type);
        self::getEntityManager()->flush();
    }

    public function testFindByType(): void
    {
        $type1 = $this->createTestType('test_bytype_1');
        $type2 = $this->createTestType('test_bytype_2');

        $catalog1 = new Catalog();
        $catalog1->setType($type1);
        $catalog1->setName('Type 1 Catalog');

        $catalog2 = new Catalog();
        $catalog2->setType($type2);
        $catalog2->setName('Type 2 Catalog');

        $catalog3 = new Catalog();
        $catalog3->setType($type1);
        $catalog3->setName('Another Type 1 Catalog');

        $this->getCatalogRepository()->save($catalog1);
        $this->getCatalogRepository()->save($catalog2);
        $this->getCatalogRepository()->save($catalog3, true);

        $type1Results = $this->getCatalogRepository()->findByType($type1);
        $this->assertCount(2, $type1Results);

        $type1Names = array_map(fn ($catalog) => $catalog->getName(), $type1Results);
        $this->assertContains('Type 1 Catalog', $type1Names);
        $this->assertContains('Another Type 1 Catalog', $type1Names);

        $type2Results = $this->getCatalogRepository()->findByType($type2);
        $this->assertCount(1, $type2Results);
        $this->assertEquals('Type 2 Catalog', $type2Results[0]->getName());

        self::getEntityManager()->remove($catalog1);
        self::getEntityManager()->remove($catalog2);
        self::getEntityManager()->remove($catalog3);
        self::getEntityManager()->remove($type1);
        self::getEntityManager()->remove($type2);
        self::getEntityManager()->flush();
    }

    public function testFindEnabledChildrenOf(): void
    {
        $type = $this->createTestType('test_enabled_children');

        $parent = new Catalog();
        $parent->setType($type);
        $parent->setName('Parent Enabled Children');

        $enabledChild = new Catalog();
        $enabledChild->setType($type);
        $enabledChild->setName('Enabled Child');
        $enabledChild->setParent($parent);
        $enabledChild->setEnabled(true);

        $disabledChild = new Catalog();
        $disabledChild->setType($type);
        $disabledChild->setName('Disabled Child');
        $disabledChild->setParent($parent);
        $disabledChild->setEnabled(false);

        $this->getCatalogRepository()->save($parent);
        $this->getCatalogRepository()->save($enabledChild);
        $this->getCatalogRepository()->save($disabledChild, true);

        $enabledChildren = $this->getCatalogRepository()->findEnabledChildrenOf($parent);

        $this->assertCount(1, $enabledChildren);
        $this->assertEquals('Enabled Child', $enabledChildren[0]->getName());

        self::getEntityManager()->remove($enabledChild);
        self::getEntityManager()->remove($disabledChild);
        self::getEntityManager()->remove($parent);
        self::getEntityManager()->remove($type);
        self::getEntityManager()->flush();
    }

    public function testSave(): void
    {
        $type = $this->createTestType('test_save');

        $catalog = new Catalog();
        $catalog->setType($type);
        $catalog->setName('Save Test');

        $this->getCatalogRepository()->save($catalog, false);
        self::getEntityManager()->flush();

        $savedCatalog = $this->getCatalogRepository()->find($catalog->getId());
        $this->assertNotNull($savedCatalog);
        $this->assertSame('Save Test', $savedCatalog->getName());

        self::getEntityManager()->remove($catalog);
        self::getEntityManager()->remove($type);
        self::getEntityManager()->flush();
    }

    public function testRemove(): void
    {
        $type = $this->createTestType('test_remove');

        $catalog = new Catalog();
        $catalog->setType($type);
        $catalog->setName('Remove Test');
        $this->getCatalogRepository()->save($catalog, true);

        $catalogId = $catalog->getId();

        $this->getCatalogRepository()->remove($catalog, true);

        $removedCatalog = $this->getCatalogRepository()->find($catalogId);
        $this->assertNull($removedCatalog);

        self::getEntityManager()->remove($type);
        self::getEntityManager()->flush();
    }

    public function testFindSiblings(): void
    {
        $type = $this->createTestType('test_siblings');

        $parent = new Catalog();
        $parent->setType($type);
        $parent->setName('Parent');
        $this->getCatalogRepository()->save($parent);

        $sibling1 = new Catalog();
        $sibling1->setType($type);
        $sibling1->setName('Sibling 1');
        $sibling1->setParent($parent);
        $sibling1->setSortOrder(1);
        $this->getCatalogRepository()->save($sibling1);

        $sibling2 = new Catalog();
        $sibling2->setType($type);
        $sibling2->setName('Sibling 2');
        $sibling2->setParent($parent);
        $sibling2->setSortOrder(2);
        $this->getCatalogRepository()->save($sibling2);

        $sibling3 = new Catalog();
        $sibling3->setType($type);
        $sibling3->setName('Sibling 3');
        $sibling3->setParent($parent);
        $sibling3->setSortOrder(3);
        $this->getCatalogRepository()->save($sibling3, true);

        $siblings = $this->getCatalogRepository()->findSiblings($sibling2);

        $this->assertCount(2, $siblings);
        $this->assertSame('Sibling 1', $siblings[0]->getName());
        $this->assertSame('Sibling 3', $siblings[1]->getName());

        self::getEntityManager()->remove($sibling1);
        self::getEntityManager()->remove($sibling2);
        self::getEntityManager()->remove($sibling3);
        self::getEntityManager()->remove($parent);
        self::getEntityManager()->remove($type);
        self::getEntityManager()->flush();
    }

    public function testCountByAssociationParentShouldReturnCorrectNumber(): void
    {
        $type = $this->createTestType('test_count_association');

        $parent = new Catalog();
        $parent->setType($type);
        $parent->setName('Count Parent');
        $this->getCatalogRepository()->save($parent);

        $child = new Catalog();
        $child->setType($type);
        $child->setName('Count Child');
        $child->setParent($parent);
        $this->getCatalogRepository()->save($child, true);

        $countWithParent = $this->getCatalogRepository()->count(['parent' => $parent]);
        $this->assertSame(1, $countWithParent);

        $countByType = $this->getCatalogRepository()->count(['type' => $type]);
        $this->assertGreaterThanOrEqual(2, $countByType);

        self::getEntityManager()->remove($child);
        self::getEntityManager()->remove($parent);
        self::getEntityManager()->remove($type);
        self::getEntityManager()->flush();
    }

    public function testCountByAssociationTypeShouldReturnCorrectNumber(): void
    {
        $type = $this->createTestType('test_count_type');

        $catalog = new Catalog();
        $catalog->setType($type);
        $catalog->setName('Count Type');
        $this->getCatalogRepository()->save($catalog, true);

        $count = $this->getCatalogRepository()->count(['type' => $type]);
        $this->assertGreaterThanOrEqual(1, $count);

        self::getEntityManager()->remove($catalog);
        self::getEntityManager()->remove($type);
        self::getEntityManager()->flush();
    }

    public function testCountByAssociationChildrenShouldReturnCorrectNumber(): void
    {
        $type = $this->createTestType('test_count_children');

        $parent = new Catalog();
        $parent->setType($type);
        $parent->setName('Parent for Count Children');

        $child = new Catalog();
        $child->setType($type);
        $child->setName('Child Count Test');
        $child->setParent($parent);

        $this->getCatalogRepository()->save($parent);
        $this->getCatalogRepository()->save($child, true);

        $count = $this->getCatalogRepository()->count(['parent' => $parent]);
        $this->assertGreaterThanOrEqual(1, $count);

        self::getEntityManager()->remove($child);
        self::getEntityManager()->remove($parent);
        self::getEntityManager()->remove($type);
        self::getEntityManager()->flush();
    }

    public function testComplexAssociationQueryWithJoins(): void
    {
        $type = $this->createTestType('test_complex_join');

        $parent = new Catalog();
        $parent->setType($type);
        $parent->setName('Parent for Complex');

        $child = new Catalog();
        $child->setType($type);
        $child->setName('Child for Complex');
        $child->setParent($parent);

        $this->getCatalogRepository()->save($parent);
        $this->getCatalogRepository()->save($child, true);

        $results = self::getEntityManager()
            ->createQuery('SELECT c FROM Tourze\CatalogBundle\Entity\Catalog c JOIN c.type t WHERE c.parent IS NOT NULL AND t.code = :code')
            ->setParameter('code', 'test_complex_join')
            ->getResult()
        ;

        /** @var Catalog[] $results */
        $this->assertIsArray($results);
        $this->assertGreaterThanOrEqual(1, \count($results));
        $foundChild = false;
        foreach ($results as $result) {
            $this->assertInstanceOf(Catalog::class, $result);
            if ('Child for Complex' === $result->getName()) {
                $foundChild = true;
                break;
            }
        }
        $this->assertTrue($foundChild);

        self::getEntityManager()->remove($child);
        self::getEntityManager()->remove($parent);
        self::getEntityManager()->remove($type);
        self::getEntityManager()->flush();
    }

    public function testMultipleNullableFieldsQuery(): void
    {
        $type = $this->createTestType('test_multiple_null');

        $catalog1 = new Catalog();
        $catalog1->setType($type);
        $catalog1->setName('Catalog 1');

        $catalog2 = new Catalog();
        $catalog2->setType($type);
        $catalog2->setName('Catalog 2');

        $catalog3 = new Catalog();
        $catalog3->setType($type);
        $catalog3->setName('Catalog 3');
        $catalog3->setMetadata(['key' => 'value']);

        $this->getCatalogRepository()->save($catalog1);
        $this->getCatalogRepository()->save($catalog2);
        $this->getCatalogRepository()->save($catalog3, true);

        $nullDescription = $this->getCatalogRepository()->findBy(['description' => null]);
        $this->assertGreaterThanOrEqual(2, \count($nullDescription));

        $nullMetadata = $this->getCatalogRepository()->findBy(['metadata' => null]);
        $this->assertGreaterThanOrEqual(2, \count($nullMetadata));

        $nullPath = $this->getCatalogRepository()->findBy(['path' => null]);
        $this->assertGreaterThanOrEqual(3, \count($nullPath));

        self::getEntityManager()->remove($catalog1);
        self::getEntityManager()->remove($catalog2);
        self::getEntityManager()->remove($catalog3);
        self::getEntityManager()->remove($type);
        self::getEntityManager()->flush();
    }

    public function testFindOneByAssociationTypeShouldReturnMatchingEntity(): void
    {
        $type = $this->createTestType('test_findone_assoc_type');

        $catalog = new Catalog();
        $catalog->setType($type);
        $catalog->setName('FindOneBy Association Test');

        $this->getCatalogRepository()->save($catalog, true);

        $found = $this->getCatalogRepository()->findOneBy(['type' => $type->getId()]);

        $this->assertNotNull($found);
        $this->assertInstanceOf(Catalog::class, $found);
        $foundType = $found->getType();
        $this->assertNotNull($foundType);
        $this->assertEquals($type->getId(), $foundType->getId());
        $this->assertEquals('FindOneBy Association Test', $found->getName());

        self::getEntityManager()->remove($catalog);
        self::getEntityManager()->remove($type);
        self::getEntityManager()->flush();
    }

    /**
     * @return ServiceEntityRepository<Catalog>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return self::getService(CatalogRepository::class);
    }

    protected function createNewEntity(): object
    {
        $type = new CatalogType();
        $type->setCode('test_type_' . uniqid());
        $type->setName('Test Type');
        $type->setEnabled(true);

        $catalog = new Catalog();
        $catalog->setType($type);
        $catalog->setName('Test Catalog');
        $catalog->setEnabled(true);

        return $catalog;
    }
}
