<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Tests\Procedure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CatalogBundle\Entity\Catalog;
use Tourze\CatalogBundle\Entity\CatalogType;
use Tourze\CatalogBundle\Param\GetCatalogListParam;
use Tourze\CatalogBundle\Procedure\GetCatalogList;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\PHPUnitJsonRPC\AbstractProcedureTestCase;

/**
 * @internal
 */
#[CoversClass(GetCatalogList::class)]
#[RunTestsInSeparateProcesses]
final class GetCatalogListTest extends AbstractProcedureTestCase
{
    private GetCatalogList $procedure;

    protected function onSetUp(): void
    {
        $this->procedure = self::getService(GetCatalogList::class);
    }

    public function testExecuteBasicList(): void
    {
        // 创建测试数据
        $catalogType = $this->createCatalogType('商品分类', 'product-' . uniqid(), true);
        $catalog1 = $this->createCatalog('数码产品', $catalogType);
        $catalog2 = $this->createCatalog('服装鞋包', $catalogType);

        $param = new GetCatalogListParam(
            typeCode: null,
            parentId: null,
            keyword: null,
            enabledOnly: true,
            includeChildrenCount: false,
            orderBy: 'sortOrder',
            orderDir: 'ASC',
            pageSize: 10,
            currentPage: 1,
        );

        $result = $this->procedure->execute($param);
        $data = $result->data;

        $this->assertIsArray($data);
        $this->assertArrayHasKey('list', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertIsArray($data['list']);
        $this->assertGreaterThanOrEqual(2, \count($data['list']));

        // 检查分类数据结构
        /** @var array<mixed> $list */
        $list = $data['list'];
        foreach ($list as $item) {
            $this->assertIsArray($item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('description', $item);
            $this->assertArrayHasKey('level', $item);
            $this->assertArrayHasKey('path', $item);
            $this->assertArrayHasKey('sortOrder', $item);
            $this->assertArrayHasKey('enabled', $item);
            $this->assertArrayHasKey('hasChildren', $item);
            $this->assertArrayHasKey('type', $item);
            $this->assertArrayHasKey('parent', $item);
        }
    }

    public function testExecuteWithSpecificType(): void
    {
        // 创建不同类型的分类
        $productType = $this->createCatalogType('商品分类', 'product-' . uniqid(), true);
        $contentType = $this->createCatalogType('内容分类', 'content-' . uniqid(), true);

        $productCatalog = $this->createCatalog('商品A', $productType);
        $contentCatalog = $this->createCatalog('内容A', $contentType);

        $param = new GetCatalogListParam(
            typeCode: $productType->getCode(),
            enabledOnly: true,
            pageSize: 10,
            currentPage: 1,
        );

        $result = $this->procedure->execute($param);
        $data = $result->data;

        $this->assertIsArray($data);
        $this->assertArrayHasKey('list', $data);
        /** @var array<mixed> $list */
        $list = $data['list'];
        $this->assertGreaterThanOrEqual(1, \count($list));

        // 所有结果应该都是指定类型的
        foreach ($list as $item) {
            $this->assertIsArray($item);
            $this->assertArrayHasKey('type', $item);
            $this->assertIsArray($item['type']);
            $this->assertEquals($productType->getId(), $item['type']['id']);
            $this->assertEquals('商品分类', $item['type']['name']);
            $this->assertEquals($productType->getCode(), $item['type']['code']);
        }
    }

    public function testExecuteWithParentFilter(): void
    {
        // 创建父子分类结构
        $catalogType = $this->createCatalogType('商品分类', 'product-' . uniqid(), true);
        $parent = $this->createCatalog('数码产品', $catalogType);
        $child1 = $this->createCatalog('手机', $catalogType, $parent);
        $child2 = $this->createCatalog('电脑', $catalogType, $parent);
        $otherParent = $this->createCatalog('服装', $catalogType);

        $param = new GetCatalogListParam(
            parentId: $parent->getId(),
            enabledOnly: true,
            pageSize: 10,
            currentPage: 1,
        );

        $result = $this->procedure->execute($param);
        $data = $result->data;

        $this->assertIsArray($data);
        $this->assertArrayHasKey('list', $data);
        /** @var array<mixed> $list */
        $list = $data['list'];
        $this->assertGreaterThanOrEqual(2, \count($list));

        // 所有结果应该都是指定父级的子分类
        foreach ($list as $item) {
            $this->assertIsArray($item);
            $this->assertArrayHasKey('parent', $item);
            $this->assertNotNull($item['parent']);
            $this->assertIsArray($item['parent']);
            $this->assertEquals($parent->getId(), $item['parent']['id']);
        }
    }

    public function testExecuteTopLevelOnly(): void
    {
        // 创建多级分类结构
        $catalogType = $this->createCatalogType('商品分类', 'product-' . uniqid(), true);
        $topLevel1 = $this->createCatalog('数码产品', $catalogType);
        $topLevel2 = $this->createCatalog('服装', $catalogType);
        $child = $this->createCatalog('手机', $catalogType, $topLevel1);

        // 不指定 parentId 时，应该只返回顶级分类
        $param = new GetCatalogListParam(
            parentId: null,
            enabledOnly: true,
            pageSize: 10,
            currentPage: 1,
        );

        $result = $this->procedure->execute($param);
        $data = $result->data;

        $this->assertIsArray($data);
        $this->assertArrayHasKey('list', $data);

        /** @var array<string, mixed> $resultArray */
        $resultArray = $data;
        $this->assertIsArray($resultArray['list']);
        $this->assertGreaterThanOrEqual(2, \count($resultArray['list']));

        // 所有结果应该都是顶级分类（parent 为 null）
        /** @var array<mixed> $list */
        $list = $resultArray['list'];
        foreach ($list as $item) {
            $this->assertIsArray($item);
            $this->assertArrayHasKey('parent', $item);
            $this->assertArrayHasKey('level', $item);
            $this->assertNull($item['parent']);
            $this->assertEquals(0, $item['level']);
        }
    }

    public function testExecuteWithKeywordSearch(): void
    {
        // 创建测试数据
        $catalogType = $this->createCatalogType('商品分类', 'product-' . uniqid(), true);
        $catalog1 = $this->createCatalog('苹果手机', $catalogType);
        $catalog2 = $this->createCatalog('华为手机', $catalogType);
        $catalog3 = $this->createCatalog('笔记本电脑', $catalogType);

        $param = new GetCatalogListParam(
            keyword: '手机',
            enabledOnly: true,
            pageSize: 10,
            currentPage: 1,
        );

        $result = $this->procedure->execute($param);
        $data = $result->data;

        $this->assertIsArray($data);
        $this->assertArrayHasKey('list', $data);

        /** @var array<string, mixed> $resultArray */
        $resultArray = $data;
        $this->assertIsArray($resultArray['list']);
        $this->assertGreaterThanOrEqual(2, \count($resultArray['list']));

        // 所有结果应该都包含关键词"手机"
        /** @var array<mixed> $list */
        $list = $resultArray['list'];
        foreach ($list as $item) {
            $this->assertIsArray($item);
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('description', $item);
            $this->assertIsString($item['name']);
            $name = $item['name'];
            $description = $item['description'];
            $this->assertTrue(
                str_contains($name, '手机') || (is_string($description) && str_contains($description, '手机')),
                '分类名称或描述应该包含关键词"手机"'
            );
        }
    }

    public function testExecuteWithIncludeChildrenCount(): void
    {
        // 创建带子分类的结构
        $catalogType = $this->createCatalogType('商品分类', 'product-' . uniqid(), true);
        $parent = $this->createCatalog('数码产品', $catalogType);
        $child1 = $this->createCatalog('手机', $catalogType, $parent);
        $child2 = $this->createCatalog('电脑', $catalogType, $parent);

        $param = new GetCatalogListParam(
            includeChildrenCount: true,
            enabledOnly: true,
            pageSize: 10,
            currentPage: 1,
        );

        $result = $this->procedure->execute($param);
        $data = $result->data;

        $this->assertIsArray($data);
        $this->assertArrayHasKey('list', $data);

        /** @var array<string, mixed> $resultArray */
        $resultArray = $data;
        $this->assertIsArray($resultArray['list']);

        // 找到有子分类的项目并验证
        $parentFound = false;
        /** @var array<mixed> $list */
        $list = $resultArray['list'];
        foreach ($list as $item) {
            $this->assertIsArray($item);
            $this->assertArrayHasKey('id', $item);
            if ($item['id'] === $parent->getId()) {
                $parentFound = true;
                $this->assertArrayHasKey('childrenCount', $item);
                $this->assertGreaterThanOrEqual(2, $item['childrenCount']);
            }
        }
        $this->assertTrue($parentFound, '应该找到有子分类的父分类');
    }

    public function testExecuteWithDifferentSortingOptions(): void
    {
        // 创建测试数据，设置不同的排序值
        $catalogType = $this->createCatalogType('商品分类', 'product-' . uniqid(), true);
        $catalog1 = $this->createCatalog('A分类', $catalogType);
        $catalog1->setSortOrder(3);
        $this->persistAndFlush($catalog1);

        $catalog2 = $this->createCatalog('B分类', $catalogType);
        $catalog2->setSortOrder(1);
        $this->persistAndFlush($catalog2);

        $catalog3 = $this->createCatalog('C分类', $catalogType);
        $catalog3->setSortOrder(2);
        $this->persistAndFlush($catalog3);

        // 测试按排序字段升序
        $param = new GetCatalogListParam(
            orderBy: 'sortOrder',
            orderDir: 'ASC',
            pageSize: 10,
            currentPage: 1,
        );

        $result = $this->procedure->execute($param);
        $data = $result->data;

        $this->assertIsArray($data);
        $this->assertArrayHasKey('list', $data);

        /** @var array<string, mixed> $resultArray */
        $resultArray = $data;
        $this->assertIsArray($resultArray['list']);
        $this->assertGreaterThanOrEqual(3, \count($resultArray['list']));

        // 验证排序
        $sortOrders = [];
        /** @var array<mixed> $list */
        $list = $resultArray['list'];
        foreach ($list as $item) {
            $this->assertIsArray($item);
            $this->assertArrayHasKey('sortOrder', $item);
            $sortOrders[] = $item['sortOrder'];
        }

        $sorted = $sortOrders;
        sort($sorted);
        $this->assertEquals($sorted, $sortOrders, '结果应该按 sortOrder 升序排列');
    }

    public function testExecuteWithInvalidTypeCode(): void
    {
        $param = new GetCatalogListParam(
            typeCode: 'invalid-type-code',
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('分类类型不存在');

        $this->procedure->execute($param);
    }

    public function testExecuteWithDisabledTypeWhenEnabledOnly(): void
    {
        // 创建禁用的分类类型
        $catalogType = $this->createCatalogType('禁用分类', 'disabled-' . uniqid(), false);

        $param = new GetCatalogListParam(
            typeCode: $catalogType->getCode(),
            enabledOnly: true,
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('分类类型未启用');

        $this->procedure->execute($param);
    }

    public function testExecuteWithInvalidParentId(): void
    {
        $param = new GetCatalogListParam(
            parentId: 'invalid-parent-id',
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('父级分类不存在');

        $this->procedure->execute($param);
    }

    public function testExecuteWithDisabledParentWhenEnabledOnly(): void
    {
        // 创建禁用的父级分类
        $catalogType = $this->createCatalogType('商品分类', 'product-' . uniqid(), true);
        $parent = $this->createCatalog('禁用分类', $catalogType);
        $parent->setEnabled(false);
        $this->persistAndFlush($parent);

        $param = new GetCatalogListParam(
            parentId: $parent->getId(),
            enabledOnly: true,
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('父级分类未启用');

        $this->procedure->execute($param);
    }

    /**
     * 创建测试用的分类类型
     */
    private function createCatalogType(string $name, string $code, bool $enabled): CatalogType
    {
        $catalogType = new CatalogType();
        $catalogType->setName($name);
        $catalogType->setCode($code);
        $catalogType->setDescription($name . '描述');
        $catalogType->setEnabled($enabled);

        $result = $this->persistAndFlush($catalogType);
        $this->assertInstanceOf(CatalogType::class, $result);

        return $result;
    }

    /**
     * 创建测试用的分类
     */
    private function createCatalog(string $name, CatalogType $type, ?Catalog $parent = null): Catalog
    {
        $catalog = new Catalog();
        $catalog->setName($name);
        $catalog->setDescription($name . '描述');
        $catalog->setType($type);
        $catalog->setEnabled(true);
        $catalog->setSortOrder(1);
        $catalog->setMetadata(['test' => 'data']);

        if (null !== $parent) {
            $catalog->setParent($parent);
            $catalog->setLevel($parent->getLevel() + 1);
        } else {
            $catalog->setLevel(0);
        }

        $result = $this->persistAndFlush($catalog);
        $this->assertInstanceOf(Catalog::class, $result);

        // 在持久化后设置路径（因为需要 ID）
        if (null !== $parent) {
            $result->setPath($parent->getPath() . '/' . (string) $result->getId());
        } else {
            $result->setPath((string) $result->getId());
        }

        $finalResult = $this->persistAndFlush($result);
        $this->assertInstanceOf(Catalog::class, $finalResult);

        return $finalResult;
    }
}
