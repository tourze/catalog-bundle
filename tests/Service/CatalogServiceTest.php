<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CatalogBundle\Entity\Catalog;
use Tourze\CatalogBundle\Entity\CatalogType;
use Tourze\CatalogBundle\Service\CatalogService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CatalogService::class)]
#[RunTestsInSeparateProcesses]
final class CatalogServiceTest extends AbstractIntegrationTestCase
{
    private CatalogService $service;

    protected function onSetUp(): void
    {
        $this->service = self::getService(CatalogService::class);
    }

    public function testFindBy(): void
    {
        $catalogType = new CatalogType();
        $catalogType->setName('Test Type');
        $catalogType->setCode('test-type-' . uniqid());
        $this->persistAndFlush($catalogType);

        $catalog = new Catalog();
        $catalog->setType($catalogType);
        $catalog->setName('Test Catalog');
        $this->persistAndFlush($catalog);

        $criteria = ['name' => 'Test Catalog'];
        $orderBy = ['id' => 'ASC'];
        $limit = 10;
        $offset = 0;

        $result = $this->service->findBy($criteria, $orderBy, $limit, $offset);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('Test Catalog', $result[0]->getName());
    }

    public function testFind(): void
    {
        $catalogType = new CatalogType();
        $catalogType->setName('Test Type');
        $catalogType->setCode('test-type-' . uniqid());
        $this->persistAndFlush($catalogType);

        $catalog = new Catalog();
        $catalog->setType($catalogType);
        $catalog->setName('Test Catalog');
        $this->persistAndFlush($catalog);
        $id = $catalog->getId();

        $result = $this->service->find($id);

        $this->assertInstanceOf(Catalog::class, $result);
        $this->assertSame('Test Catalog', $result->getName());
    }

    public function testFindAll(): void
    {
        $catalogType = new CatalogType();
        $catalogType->setName('Test Type');
        $catalogType->setCode('test-type-' . uniqid());
        $this->persistAndFlush($catalogType);

        $catalog1 = new Catalog();
        $catalog1->setType($catalogType);
        $catalog1->setName('Catalog 1');
        $catalog2 = new Catalog();
        $catalog2->setType($catalogType);
        $catalog2->setName('Catalog 2');

        $this->persistAndFlush($catalog1);
        $this->persistAndFlush($catalog2);

        $result = $this->service->findAll();

        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(2, \count($result));
    }

    public function testFindOneBy(): void
    {
        $catalogType = new CatalogType();
        $catalogType->setName('Test Type');
        $catalogType->setCode('test-type-' . uniqid());
        $this->persistAndFlush($catalogType);

        $catalog = new Catalog();
        $catalog->setType($catalogType);
        $catalog->setName('Test Catalog');
        $this->persistAndFlush($catalog);

        $criteria = ['name' => 'Test Catalog'];
        $orderBy = ['id' => 'ASC'];

        $result = $this->service->findOneBy($criteria, $orderBy);

        $this->assertInstanceOf(Catalog::class, $result);
        $this->assertSame('Test Catalog', $result->getName());
    }

    public function testFindOneByReturnsNull(): void
    {
        $criteria = ['name' => 'non-existent'];

        $result = $this->service->findOneBy($criteria);

        $this->assertNull($result);
    }

    public function testFindByIds(): void
    {
        $catalogType = new CatalogType();
        $catalogType->setName('Test Type');
        $catalogType->setCode('test-type-' . uniqid());
        $this->persistAndFlush($catalogType);

        $catalog1 = new Catalog();
        $catalog1->setType($catalogType);
        $catalog1->setName('Catalog 1');
        $this->persistAndFlush($catalog1);

        $catalog2 = new Catalog();
        $catalog2->setType($catalogType);
        $catalog2->setName('Catalog 2');
        $this->persistAndFlush($catalog2);

        $ids = [(string) $catalog1->getId(), (string) $catalog2->getId()];

        $result = $this->service->findByIds($ids);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);

        $names = array_map(fn (Catalog $catalog) => $catalog->getName(), $result);
        $this->assertContains('Catalog 1', $names);
        $this->assertContains('Catalog 2', $names);
    }

    public function testFindByIdsWithEmptyArray(): void
    {
        $result = $this->service->findByIds([]);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testFindByIdsWithNonExistentIds(): void
    {
        $result = $this->service->findByIds(['999999', '888888']);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testFindCatalogTypeOneBy(): void
    {
        $catalogType = new CatalogType();
        $catalogType->setName('Unique Type');
        $catalogType->setCode('unique-type-' . uniqid());
        $this->persistAndFlush($catalogType);

        $criteria = ['name' => 'Unique Type'];
        $orderBy = ['id' => 'ASC'];

        $result = $this->service->findCatalogTypeOneBy($criteria, $orderBy);

        $this->assertInstanceOf(CatalogType::class, $result);
        $this->assertSame('Unique Type', $result->getName());
    }

    public function testFindCatalogTypeOneByReturnsNull(): void
    {
        $criteria = ['name' => 'non-existent-type'];

        $result = $this->service->findCatalogTypeOneBy($criteria);

        $this->assertNull($result);
    }
}
