<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Tests\Procedure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CatalogBundle\Entity\Catalog;
use Tourze\CatalogBundle\Entity\CatalogType;
use Tourze\CatalogBundle\Procedure\GetCatalogTypeList;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPC\Core\Tests\AbstractProcedureTestCase;

/**
 * @internal
 */
#[CoversClass(GetCatalogTypeList::class)]
#[RunTestsInSeparateProcesses]
final class GetCatalogTypeListTest extends AbstractProcedureTestCase
{
    private GetCatalogTypeList $procedure;

    protected function onSetUp(): void
    {
        $this->procedure = self::getService(GetCatalogTypeList::class);
    }

    public function testExecuteBasicList(): void
    {
        // 创建测试数据
        $type1 = $this->createCatalogType('商品分类', 'product-' . uniqid(), '商品分类体系', true);
        $type2 = $this->createCatalogType('内容分类', 'content-' . uniqid(), '内容分类体系', true);

        $this->procedure->keyword = null;
        $this->procedure->enabledOnly = true;
        $this->procedure->includeCatalogCount = false;
        $this->procedure->orderBy = 'createTime';
        $this->procedure->orderDir = 'DESC';
        $this->procedure->currentPage = 1;
        $this->procedure->pageSize = 10;

        $result = $this->procedure->execute();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('list', $result);
        $this->assertArrayHasKey('pagination', $result);

        /** @var array<string, mixed> $resultArray */
        $resultArray = $result;
        $this->assertIsArray($resultArray['list']);
        $this->assertGreaterThanOrEqual(2, \count($resultArray['list']));

        // 检查分类类型数据结构
        /** @var array<mixed> $list */
        $list = $resultArray['list'];
        foreach ($list as $item) {
            $this->assertIsArray($item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('code', $item);
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('description', $item);
            $this->assertArrayHasKey('enabled', $item);
            $this->assertArrayHasKey('createTime', $item);
            $this->assertArrayHasKey('updateTime', $item);
            $this->assertTrue($item['enabled'], '启用状态应该为 true');
        }
    }

    public function testExecuteWithEnabledOnlyFalse(): void
    {
        // 创建启用和禁用的分类类型
        $enabledType = $this->createCatalogType('启用分类', 'enabled-' . uniqid(), '启用的分类类型', true);
        $disabledType = $this->createCatalogType('禁用分类', 'disabled-' . uniqid(), '禁用的分类类型', false);

        $this->procedure->enabledOnly = false;
        $this->procedure->currentPage = 1;
        $this->procedure->pageSize = 10;

        $result = $this->procedure->execute();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('list', $result);

        /** @var array<string, mixed> $resultArray */
        $resultArray = $result;
        $this->assertIsArray($resultArray['list']);
        $this->assertGreaterThanOrEqual(2, \count($resultArray['list']));

        // 应该包含启用和禁用的类型
        /** @var array<mixed> $list */
        $list = $resultArray['list'];
        $enabledStatuses = array_column($list, 'enabled');
        $this->assertContains(true, $enabledStatuses);
        $this->assertContains(false, $enabledStatuses);
    }

    public function testExecuteWithKeywordSearch(): void
    {
        // 创建测试数据
        $type1 = $this->createCatalogType('商品分类', 'product-' . uniqid(), '商品管理分类', true);
        $type2 = $this->createCatalogType('文章分类', 'article-' . uniqid(), '内容文章分类', true);
        $type3 = $this->createCatalogType('用户分类', 'user-' . uniqid(), '用户管理分类', true);

        // 搜索包含"分类"的名称
        $this->procedure->keyword = '文章';
        $this->procedure->enabledOnly = true;
        $this->procedure->currentPage = 1;
        $this->procedure->pageSize = 10;

        $result = $this->procedure->execute();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('list', $result);

        /** @var array<string, mixed> $resultArray */
        $resultArray = $result;
        $this->assertIsArray($resultArray['list']);
        $this->assertGreaterThanOrEqual(1, \count($resultArray['list']));

        // 所有结果应该都包含关键词"文章"
        /** @var array<mixed> $list */
        $list = $resultArray['list'];
        foreach ($list as $item) {
            $this->assertIsArray($item);
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('code', $item);
            $this->assertArrayHasKey('description', $item);
            $this->assertIsString($item['name']);
            $this->assertIsString($item['code']);
            $this->assertTrue(
                str_contains($item['name'], '文章')
                || str_contains($item['code'], '文章')
                || (is_string($item['description']) && str_contains($item['description'], '文章')),
                '名称、代码或描述应该包含关键词"文章"'
            );
        }
    }

    public function testExecuteWithIncludeCatalogCount(): void
    {
        // 创建分类类型和关联的分类
        $catalogType = $this->createCatalogType('商品分类', 'product-' . uniqid(), '商品分类体系', true);
        $catalog1 = $this->createCatalog('数码产品', $catalogType);
        $catalog2 = $this->createCatalog('服装鞋包', $catalogType);

        $this->procedure->includeCatalogCount = true;
        $this->procedure->enabledOnly = true;
        $this->procedure->currentPage = 1;
        $this->procedure->pageSize = 10;

        $result = $this->procedure->execute();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('list', $result);

        /** @var array<string, mixed> $resultArray */
        $resultArray = $result;
        $this->assertIsArray($resultArray['list']);
        $this->assertGreaterThanOrEqual(1, \count($resultArray['list']));

        // 找到有关联分类的类型并验证
        /** @var array<mixed> $list */
        $list = $resultArray['list'];
        foreach ($list as $item) {
            $this->assertIsArray($item);
            $this->assertArrayHasKey('id', $item);
            if ($item['id'] === $catalogType->getId()) {
                $this->assertArrayHasKey('catalogCount', $item);
                $this->assertGreaterThanOrEqual(2, $item['catalogCount']);
                break;
            }
        }
    }

    public function testExecuteWithDifferentSortingOptions(): void
    {
        // 创建测试数据，使用不同的名称确保排序效果
        $type1 = $this->createCatalogType('A分类', 'a-type-' . uniqid(), 'A分类描述', true);
        $type2 = $this->createCatalogType('B分类', 'b-type-' . uniqid(), 'B分类描述', true);
        $type3 = $this->createCatalogType('C分类', 'c-type-' . uniqid(), 'C分类描述', true);

        // 测试按名称升序排列
        $this->procedure->orderBy = 'name';
        $this->procedure->orderDir = 'ASC';
        $this->procedure->currentPage = 1;
        $this->procedure->pageSize = 10;

        $result = $this->procedure->execute();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('list', $result);

        /** @var array<string, mixed> $resultArray */
        $resultArray = $result;
        $this->assertIsArray($resultArray['list']);
        $this->assertGreaterThanOrEqual(3, \count($resultArray['list']));

        // 验证排序
        $names = [];
        /** @var array<mixed> $list */
        $list = $resultArray['list'];
        foreach ($list as $item) {
            $this->assertIsArray($item);
            $this->assertArrayHasKey('name', $item);
            $names[] = $item['name'];
        }

        $sorted = $names;
        sort($sorted);
        $this->assertEquals($sorted, $names, '结果应该按名称升序排列');
    }

    public function testExecuteWithCodeSorting(): void
    {
        // 创建测试数据，使用不同的代码确保排序效果
        $type1 = $this->createCatalogType('分类Z', 'z-code-' . uniqid(), 'Z分类描述', true);
        $type2 = $this->createCatalogType('分类A', 'a-code-' . uniqid(), 'A分类描述', true);
        $type3 = $this->createCatalogType('分类M', 'm-code-' . uniqid(), 'M分类描述', true);

        // 测试按代码升序排列
        $this->procedure->orderBy = 'code';
        $this->procedure->orderDir = 'ASC';
        $this->procedure->currentPage = 1;
        $this->procedure->pageSize = 10;

        $result = $this->procedure->execute();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('list', $result);

        /** @var array<string, mixed> $resultArray */
        $resultArray = $result;
        $this->assertIsArray($resultArray['list']);
        $this->assertGreaterThanOrEqual(3, \count($resultArray['list']));

        // 验证排序
        $codes = [];
        /** @var array<mixed> $list */
        $list = $resultArray['list'];
        foreach ($list as $item) {
            $this->assertIsArray($item);
            $this->assertArrayHasKey('code', $item);
            $codes[] = $item['code'];
        }

        $sorted = $codes;
        sort($sorted);
        $this->assertEquals($sorted, $codes, '结果应该按代码升序排列');
    }

    public function testExecuteWithPagination(): void
    {
        // 创建足够的测试数据来测试分页
        for ($i = 1; $i <= 5; ++$i) {
            $this->createCatalogType("分类类型{$i}", "type-{$i}", "第{$i}个分类类型", true);
        }

        // 测试第一页
        $this->procedure->currentPage = 1;
        $this->procedure->pageSize = 2;
        $this->procedure->orderBy = 'name';
        $this->procedure->orderDir = 'ASC';

        $result = $this->procedure->execute();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('list', $result);
        $this->assertArrayHasKey('pagination', $result);
        /** @var array<mixed> $list */
        $list = $result['list'];
        $this->assertLessThanOrEqual(2, \count($list));

        /** @var array<mixed> $pagination */
        $pagination = $result['pagination'];
        $this->assertIsArray($pagination);
        $this->assertArrayHasKey('current', $pagination);
        $this->assertArrayHasKey('pageSize', $pagination);
        $this->assertArrayHasKey('total', $pagination);
        $this->assertArrayHasKey('hasMore', $pagination);
        $this->assertEquals(1, $pagination['current']);
        $this->assertEquals(2, $pagination['pageSize']);
        $this->assertGreaterThanOrEqual(5, $pagination['total']);
    }

    public function testGetCacheKey(): void
    {
        $params = new JsonRpcParams([
            'keyword' => 'test',
            'enabledOnly' => true,
            'includeCatalogCount' => true,
        ]);
        $request = new JsonRpcRequest();
        $request->setId('1');
        $request->setMethod('catalog.type.list');
        $request->setParams($params);

        $cacheKey = $this->procedure->getCacheKey($request);

        $this->assertIsString($cacheKey);
        $this->assertStringContainsString('GetCatalogTypeList', $cacheKey);
    }

    public function testGetCacheDuration(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);

        $duration = $this->procedure->getCacheDuration($request);

        $this->assertEquals(1800, $duration); // 30分钟
    }

    public function testGetCacheTags(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);

        $tags = iterator_to_array($this->procedure->getCacheTags($request));

        $this->assertContains('catalog_type', $tags);
        $this->assertContains('catalog_type_list', $tags);
    }

    public function testGetMockResult(): void
    {
        $mockResult = GetCatalogTypeList::getMockResult();

        $this->assertIsArray($mockResult);
        $this->assertArrayHasKey('list', $mockResult);
        $this->assertArrayHasKey('pagination', $mockResult);
        $this->assertIsArray($mockResult['list']);
        $this->assertIsArray($mockResult['pagination']);

        // 检查列表项结构
        /** @var array<string, mixed> $mockResultArray */
        $mockResultArray = $mockResult;
        $this->assertIsArray($mockResultArray['list']);

        /** @var array<mixed> $list */
        $list = $mockResultArray['list'];
        if ([] !== $list) {
            $this->assertIsArray($list[0]);
            /** @var array<string, mixed> $item */
            $item = $list[0];
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('code', $item);
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('description', $item);
            $this->assertArrayHasKey('enabled', $item);
            $this->assertArrayHasKey('createTime', $item);
            $this->assertArrayHasKey('updateTime', $item);
            $this->assertArrayHasKey('catalogCount', $item);
        }

        // 检查分页结构
        $pagination = $mockResult['pagination'];
        $this->assertArrayHasKey('current', $pagination);
        $this->assertArrayHasKey('pageSize', $pagination);
        $this->assertArrayHasKey('total', $pagination);
        $this->assertArrayHasKey('hasMore', $pagination);
    }

    /**
     * 创建测试用的分类类型
     */
    private function createCatalogType(string $name, string $code, string $description, bool $enabled): CatalogType
    {
        $catalogType = new CatalogType();
        $catalogType->setName($name);
        $catalogType->setCode($code);
        $catalogType->setEnabled($enabled);

        $result = $this->persistAndFlush($catalogType);
        $this->assertInstanceOf(CatalogType::class, $result);

        return $result;
    }

    /**
     * 创建测试用的分类
     */
    private function createCatalog(string $name, CatalogType $type): Catalog
    {
        $catalog = new Catalog();
        $catalog->setName($name);
        $catalog->setDescription($name . '描述');
        $catalog->setType($type);
        $catalog->setEnabled(true);
        $catalog->setSortOrder(1);
        $catalog->setLevel(0);

        // 确保双向关联正确建立
        $type->addCatalog($catalog);

        $persistedCatalog = $this->persistAndFlush($catalog);
        $this->assertInstanceOf(Catalog::class, $persistedCatalog);

        // 在持久化后设置路径（使用 ID）
        $persistedCatalog->setPath((string) $persistedCatalog->getId());

        $finalCatalog = $this->persistAndFlush($persistedCatalog);
        $this->assertInstanceOf(Catalog::class, $finalCatalog);

        return $finalCatalog;
    }
}
