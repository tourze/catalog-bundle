<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CatalogBundle\Entity\Catalog;
use Tourze\CatalogBundle\Entity\CatalogType;
use Tourze\CatalogBundle\Repository\CatalogRepository;
use Tourze\CatalogBundle\Repository\CatalogTypeRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(CatalogTypeRepository::class)]
#[RunTestsInSeparateProcesses]
final class CatalogTypeRepositoryTest extends AbstractRepositoryTestCase
{
    private ?CatalogTypeRepository $repository = null;

    protected function onSetUp(): void
    {
        $repository = self::getService(CatalogTypeRepository::class);
        $this->assertInstanceOf(CatalogTypeRepository::class, $repository);
        $this->repository = $repository;
    }

    protected function onTearDown(): void
    {
        $this->repository = null;
    }

    private function getCatalogTypeRepository(): CatalogTypeRepository
    {
        $repository = $this->repository;
        if (null === $repository) {
            self::fail('Repository not initialized');
        }

        return $repository;
    }

    public function testFindEnabledTypes(): void
    {
        $type1 = new CatalogType();
        $type1->setCode('test_enabled_1');
        $type1->setName('Test Enabled 1');
        $type1->setEnabled(true);

        $type2 = new CatalogType();
        $type2->setCode('test_disabled');
        $type2->setName('Test Disabled');
        $type2->setEnabled(false);

        $type3 = new CatalogType();
        $type3->setCode('test_enabled_2');
        $type3->setName('Test Enabled 2');
        $type3->setEnabled(true);

        $repository = $this->getCatalogTypeRepository();
        $repository->save($type1);
        $repository->save($type2);
        $repository->save($type3, true);

        $enabledTypes = $repository->findEnabledTypes();

        $enabledCodes = array_map(fn ($type) => $type->getCode(), $enabledTypes);

        $this->assertContains('test_enabled_1', $enabledCodes);
        $this->assertContains('test_enabled_2', $enabledCodes);
        $this->assertNotContains('test_disabled', $enabledCodes);

        $repository->remove($type1);
        $repository->remove($type2);
        $repository->remove($type3, true);
    }

    public function testFindOneByCode(): void
    {
        $type = new CatalogType();
        $type->setCode('test_find_by_code');
        $type->setName('Test Find By Code');

        $this->getCatalogTypeRepository()->save($type, true);

        $foundType = $this->getCatalogTypeRepository()->findOneByCode('test_find_by_code');

        $this->assertNotNull($foundType);
        $this->assertSame('test_find_by_code', $foundType->getCode());
        $this->assertSame('Test Find By Code', $foundType->getName());

        $notFound = $this->getCatalogTypeRepository()->findOneByCode('non_existent');
        $this->assertNull($notFound);

        $this->getCatalogTypeRepository()->remove($type, true);
    }

    public function testFindByCodesIn(): void
    {
        $type1 = new CatalogType();
        $type1->setCode('test_codes_1');
        $type1->setName('Test Codes 1');

        $type2 = new CatalogType();
        $type2->setCode('test_codes_2');
        $type2->setName('Test Codes 2');

        $type3 = new CatalogType();
        $type3->setCode('test_codes_3');
        $type3->setName('Test Codes 3');

        $this->getCatalogTypeRepository()->save($type1);
        $this->getCatalogTypeRepository()->save($type2);
        $this->getCatalogTypeRepository()->save($type3, true);

        $foundTypes = $this->getCatalogTypeRepository()->findByCodesIn(['test_codes_1', 'test_codes_3', 'non_existent']);

        $this->assertCount(2, $foundTypes);

        $foundCodes = array_map(fn ($type) => $type->getCode(), $foundTypes);
        $this->assertContains('test_codes_1', $foundCodes);
        $this->assertContains('test_codes_3', $foundCodes);
        $this->assertNotContains('test_codes_2', $foundCodes);

        $this->getCatalogTypeRepository()->remove($type1);
        $this->getCatalogTypeRepository()->remove($type2);
        $this->getCatalogTypeRepository()->remove($type3, true);
    }

    public function testFindAllIndexedByCode(): void
    {
        $type1 = new CatalogType();
        $type1->setCode('test_indexed_1');
        $type1->setName('Test Indexed 1');

        $type2 = new CatalogType();
        $type2->setCode('test_indexed_2');
        $type2->setName('Test Indexed 2');

        $this->getCatalogTypeRepository()->save($type1);
        $this->getCatalogTypeRepository()->save($type2, true);

        $indexed = $this->getCatalogTypeRepository()->findAllIndexedByCode();

        $this->assertArrayHasKey('test_indexed_1', $indexed);
        $this->assertArrayHasKey('test_indexed_2', $indexed);
        $this->assertSame('Test Indexed 1', $indexed['test_indexed_1']->getName());
        $this->assertSame('Test Indexed 2', $indexed['test_indexed_2']->getName());

        $this->getCatalogTypeRepository()->remove($type1);
        $this->getCatalogTypeRepository()->remove($type2, true);
    }

    public function testFindWithInvalidIdShouldReturnNull(): void
    {
        $result = $this->getCatalogTypeRepository()->find('non-existent-id');

        $this->assertNull($result);
    }

    public function testSaveAndRemove(): void
    {
        $type = new CatalogType();
        $type->setCode('test_save_remove');
        $type->setName('Test Save Remove');

        $this->getCatalogTypeRepository()->save($type, true);

        $foundType = $this->getCatalogTypeRepository()->findOneByCode('test_save_remove');
        $this->assertNotNull($foundType);

        $this->getCatalogTypeRepository()->remove($foundType, true);

        $notFound = $this->getCatalogTypeRepository()->findOneByCode('test_save_remove');
        $this->assertNull($notFound);
    }

    public function testRemove(): void
    {
        $type = new CatalogType();
        $type->setCode('test_remove_method');
        $type->setName('Test Remove Method');

        $this->getCatalogTypeRepository()->save($type, true);

        $foundBefore = $this->getCatalogTypeRepository()->findOneByCode('test_remove_method');
        $this->assertNotNull($foundBefore);

        $this->getCatalogTypeRepository()->remove($type, true);

        $foundAfter = $this->getCatalogTypeRepository()->findOneByCode('test_remove_method');
        $this->assertNull($foundAfter);
    }

    public function testFindOneByWithOrderBy(): void
    {
        $type1 = new CatalogType();
        $type1->setCode('test_order_1');
        $type1->setName('B Type');

        $type2 = new CatalogType();
        $type2->setCode('test_order_2');
        $type2->setName('A Type');

        $this->getCatalogTypeRepository()->save($type1);
        $this->getCatalogTypeRepository()->save($type2, true);

        $result = $this->getCatalogTypeRepository()->findOneBy(['code' => ['test_order_1', 'test_order_2']], ['name' => 'ASC']);
        $this->assertNotNull($result);
        $this->assertSame('A Type', $result->getName());

        $resultDesc = $this->getCatalogTypeRepository()->findOneBy(['code' => ['test_order_1', 'test_order_2']], ['name' => 'DESC']);
        $this->assertNotNull($resultDesc);
        $this->assertSame('B Type', $resultDesc->getName());

        $resultByCode = $this->getCatalogTypeRepository()->findOneBy(['code' => ['test_order_1', 'test_order_2']], ['code' => 'ASC']);
        $this->assertNotNull($resultByCode);
        $this->assertSame('test_order_1', $resultByCode->getCode());

        $this->getCatalogTypeRepository()->remove($type1);
        $this->getCatalogTypeRepository()->remove($type2, true);
    }

    public function testCountByAssociationCatalogsShouldReturnCorrectNumber(): void
    {
        $type = new CatalogType();
        $type->setCode('test_count_catalogs');
        $type->setName('Type for Count Catalogs');

        $this->getCatalogTypeRepository()->save($type, true);

        $catalogRepository = self::getService(CatalogRepository::class);

        $catalog = new Catalog();
        $catalog->setType($type);
        $catalog->setName('Test Count Catalog');

        $catalogRepository->save($catalog, true);

        $count = (int) self::getEntityManager()
            ->createQuery('SELECT COUNT(ct) FROM Tourze\CatalogBundle\Entity\CatalogType ct JOIN ct.catalogs c WHERE c.id = :catalogId')
            ->setParameter('catalogId', $catalog->getId())
            ->getSingleScalarResult()
        ;

        $this->assertGreaterThanOrEqual(1, $count);

        $catalogRepository->remove($catalog);
        $this->getCatalogTypeRepository()->remove($type, true);
    }

    public function testComplexAssociationQueryWithCatalogs(): void
    {
        $type = new CatalogType();
        $type->setCode('test_complex_catalogs');
        $type->setName('Type with Complex Catalogs');
        $type->setDescription('Complex association test');

        $this->getCatalogTypeRepository()->save($type, true);

        $catalogRepository = self::getService(CatalogRepository::class);

        $catalog1 = new Catalog();
        $catalog1->setType($type);
        $catalog1->setName('Complex Catalog 1');
        $catalog1->setDescription('First complex catalog');

        $catalog2 = new Catalog();
        $catalog2->setType($type);
        $catalog2->setName('Complex Catalog 2');
        $catalog2->setDescription('Second complex catalog');

        $catalogRepository->save($catalog1);
        $catalogRepository->save($catalog2, true);

        $typesWithMultipleCatalogs = self::getEntityManager()
            ->createQuery('SELECT ct FROM Tourze\CatalogBundle\Entity\CatalogType ct JOIN ct.catalogs c WHERE ct.code = :code GROUP BY ct.id HAVING COUNT(c) >= 2')
            ->setParameter('code', 'test_complex_catalogs')
            ->getResult()
        ;

        /** @var CatalogType[] $typesWithMultipleCatalogs */
        $this->assertIsArray($typesWithMultipleCatalogs);
        $this->assertGreaterThanOrEqual(1, \count($typesWithMultipleCatalogs));

        $catalogRepository->remove($catalog1);
        $catalogRepository->remove($catalog2);
        $this->getCatalogTypeRepository()->remove($type, true);
    }

    public function testNullableDescriptionComplexQuery(): void
    {
        $typeWithDesc = new CatalogType();
        $typeWithDesc->setCode('test_nullable_desc_1');
        $typeWithDesc->setName('Type With Description');

        $typeWithoutDesc = new CatalogType();
        $typeWithoutDesc->setCode('test_nullable_desc_2');
        $typeWithoutDesc->setName('Type Without Description');

        $this->getCatalogTypeRepository()->save($typeWithDesc);
        $this->getCatalogTypeRepository()->save($typeWithoutDesc, true);

        $nullDescriptionTypes = self::getEntityManager()
            ->createQuery('SELECT ct FROM Tourze\CatalogBundle\Entity\CatalogType ct WHERE ct.description IS NULL')
            ->getResult()
        ;

        /** @var CatalogType[] $nullDescriptionTypes */
        $this->assertIsArray($nullDescriptionTypes);
        $this->assertGreaterThanOrEqual(1, \count($nullDescriptionTypes));

        $found = false;
        foreach ($nullDescriptionTypes as $nullType) {
            $this->assertInstanceOf(CatalogType::class, $nullType);
            if ('test_nullable_desc_2' === $nullType->getCode()) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);

        $this->getCatalogTypeRepository()->remove($typeWithDesc);
        $this->getCatalogTypeRepository()->remove($typeWithoutDesc, true);
    }

    public function testFindOneByAssociationCatalogsShouldReturnMatchingEntity(): void
    {
        $type = new CatalogType();
        $type->setCode('test_catalogs_association');
        $type->setName('Type with Catalogs');

        $this->getCatalogTypeRepository()->save($type, true);

        $catalogRepository = self::getService(CatalogRepository::class);

        $catalog = new Catalog();
        $catalog->setType($type);
        $catalog->setName('Test Catalog for Association');

        $catalogRepository->save($catalog, true);

        // 使用 DQL 查询来测试关联，因为 catalogs 是 inverse side
        $foundType = self::getEntityManager()
            ->createQuery('SELECT ct FROM Tourze\CatalogBundle\Entity\CatalogType ct JOIN ct.catalogs c WHERE c.id = :catalogId')
            ->setParameter('catalogId', $catalog->getId())
            ->getOneOrNullResult()
        ;

        $this->assertNotNull($foundType);
        $this->assertInstanceOf(CatalogType::class, $foundType);
        $this->assertEquals('test_catalogs_association', $foundType->getCode());

        $catalogRepository->remove($catalog);
        $this->getCatalogTypeRepository()->remove($type, true);
    }

    /**
     * @return ServiceEntityRepository<CatalogType>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return self::getService(CatalogTypeRepository::class);
    }

    protected function createNewEntity(): object
    {
        $type = new CatalogType();
        $type->setCode('test_type_' . uniqid());
        $type->setName('Test Type');
        $type->setEnabled(true);

        return $type;
    }
}
