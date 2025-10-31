<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Tests\Procedure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CatalogBundle\Entity\Catalog;
use Tourze\CatalogBundle\Entity\CatalogType;
use Tourze\CatalogBundle\Procedure\GetCatalogDetail;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPC\Core\Tests\AbstractProcedureTestCase;

/**
 * @internal
 */
#[CoversClass(GetCatalogDetail::class)]
#[RunTestsInSeparateProcesses]
final class GetCatalogDetailTest extends AbstractProcedureTestCase
{
    private GetCatalogDetail $procedure;

    protected function onSetUp(): void
    {
        $this->procedure = self::getService(GetCatalogDetail::class);
    }

    public function testExecuteWithBasicCatalogDetail(): void
    {
        // 创建测试数据
        $catalogType = $this->createCatalogType('商品分类', 'product-' . uniqid(), true);
        $catalog = $this->createCatalog('数码产品', $catalogType);

        $this->assertNotNull($catalog->getId());
        $this->procedure->catalogId = $catalog->getId();
        $this->procedure->includeAncestors = false;
        $this->procedure->includeChildren = false;
        $this->procedure->includeSiblings = false;
        $this->procedure->enabledOnly = true;

        $result = $this->procedure->execute();

        $this->assertIsArray($result);
        $this->assertEquals($catalog->getId(), $result['id']);
        $this->assertEquals('数码产品', $result['name']);
        $this->assertEquals('数码产品描述', $result['description']);
        $this->assertEquals(0, $result['level']);
        $this->assertEquals($catalog->getPath(), $result['path']);
        $this->assertTrue($result['enabled']);
        $this->assertIsArray($result['type']);
        $this->assertEquals($catalogType->getId(), $result['type']['id']);
        $this->assertEquals('商品分类', $result['type']['name']);
        $this->assertEquals($catalogType->getCode(), $result['type']['code']);
        $this->assertNull($result['parent']);
    }

    public function testExecuteWithAncestors(): void
    {
        // 创建嵌套分类结构
        $catalogType = $this->createCatalogType('商品分类', 'product-' . uniqid(), true);
        $grandparent = $this->createCatalog('电子产品', $catalogType);
        $parent = $this->createCatalog('数码产品', $catalogType, $grandparent);
        $child = $this->createCatalog('手机', $catalogType, $parent);

        $childId = $child->getId();
        $this->assertNotNull($childId);
        $this->procedure->catalogId = $childId;
        $this->procedure->includeAncestors = true;
        $this->procedure->includeChildren = false;
        $this->procedure->includeSiblings = false;

        $result = $this->procedure->execute();
        $this->assertIsArray($result);

        $this->assertArrayHasKey('ancestors', $result);
        $this->assertIsArray($result['ancestors']);
        $this->assertGreaterThanOrEqual(2, \count($result['ancestors']));

        // 检查祖先数据结构
        foreach ($result['ancestors'] as $ancestor) {
            $this->assertIsArray($ancestor);
            $this->assertArrayHasKey('id', $ancestor);
            $this->assertArrayHasKey('name', $ancestor);
            $this->assertArrayHasKey('path', $ancestor);
            $this->assertArrayHasKey('level', $ancestor);
        }
    }

    public function testExecuteWithChildren(): void
    {
        // 创建带子分类的结构
        $catalogType = $this->createCatalogType('商品分类', 'product-' . uniqid(), true);
        $parent = $this->createCatalog('数码产品', $catalogType);
        $child1 = $this->createCatalog('手机', $catalogType, $parent);
        $child2 = $this->createCatalog('电脑', $catalogType, $parent);

        $parentId = $parent->getId();
        $this->assertNotNull($parentId);
        $this->procedure->catalogId = $parentId;
        $this->procedure->includeChildren = true;
        $this->procedure->enabledOnly = true;

        $result = $this->procedure->execute();
        $this->assertIsArray($result);

        $this->assertArrayHasKey('children', $result);
        $this->assertIsArray($result['children']);
        $this->assertGreaterThanOrEqual(2, \count($result['children']));

        // 检查子分类数据结构
        foreach ($result['children'] as $child) {
            $this->assertIsArray($child);
            $this->assertArrayHasKey('id', $child);
            $this->assertArrayHasKey('name', $child);
            $this->assertArrayHasKey('path', $child);
            $this->assertArrayHasKey('level', $child);
            $this->assertArrayHasKey('sortOrder', $child);
            $this->assertArrayHasKey('enabled', $child);
            $this->assertArrayHasKey('hasChildren', $child);
        }

        // 验证子分类是按排序字段排序的
        $sortOrders = array_column($result['children'], 'sortOrder');
        $this->assertEquals($sortOrders, array_values($sortOrders)); // 已排序
    }

    public function testExecuteWithSiblings(): void
    {
        // 创建有兄弟分类的结构
        $catalogType = $this->createCatalogType('商品分类', 'product-' . uniqid(), true);
        $parent = $this->createCatalog('数码产品', $catalogType);
        $sibling1 = $this->createCatalog('手机', $catalogType, $parent);
        $target = $this->createCatalog('电脑', $catalogType, $parent);
        $sibling2 = $this->createCatalog('相机', $catalogType, $parent);

        $targetId = $target->getId();
        $this->assertNotNull($targetId);
        $this->procedure->catalogId = $targetId;
        $this->procedure->includeSiblings = true;
        $this->procedure->enabledOnly = true;

        $result = $this->procedure->execute();

        $this->assertArrayHasKey('siblings', $result);
        $this->assertIsArray($result['siblings']);
        $this->assertCount(2, $result['siblings']); // 不包含自己

        // 确认没有包含目标分类本身
        $siblingIds = array_column($result['siblings'], 'id');
        $this->assertNotContains($target->getId(), $siblingIds);

        // 但包含其他兄弟分类
        $this->assertContains($sibling1->getId(), $siblingIds);
        $this->assertContains($sibling2->getId(), $siblingIds);
    }

    public function testExecuteWithNonExistentCatalog(): void
    {
        $this->procedure->catalogId = 'non-existent-id';

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('分类不存在');

        $this->procedure->execute();
    }

    public function testExecuteWithDisabledCatalogWhenEnabledOnly(): void
    {
        // 创建禁用的分类
        $catalogType = $this->createCatalogType('商品分类', 'product-' . uniqid(), true);
        $catalog = $this->createCatalog('禁用分类', $catalogType);
        $catalog->setEnabled(false);
        $this->persistAndFlush($catalog);

        $this->assertNotNull($catalog->getId());
        $this->procedure->catalogId = $catalog->getId();
        $this->procedure->enabledOnly = true;

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('分类未启用');

        $this->procedure->execute();
    }

    public function testExecuteWithDisabledCatalogWhenEnabledOnlyFalse(): void
    {
        // 创建禁用的分类
        $catalogType = $this->createCatalogType('商品分类', 'product-' . uniqid(), true);
        $catalog = $this->createCatalog('禁用分类', $catalogType);
        $catalog->setEnabled(false);
        $this->persistAndFlush($catalog);

        $this->assertNotNull($catalog->getId());
        $this->procedure->catalogId = $catalog->getId();
        $this->procedure->enabledOnly = false;

        $result = $this->procedure->execute();

        $this->assertIsArray($result);
        $this->assertEquals($catalog->getId(), $result['id']);
        $this->assertFalse($result['enabled']);
    }

    public function testGetCacheKey(): void
    {
        $params = new JsonRpcParams([
            'catalogId' => '123',
            'includeAncestors' => true,
            'includeChildren' => true,
        ]);
        $request = new JsonRpcRequest();
        $request->setId('1');
        $request->setMethod('catalog.detail');
        $request->setParams($params);

        $cacheKey = $this->procedure->getCacheKey($request);

        $this->assertIsString($cacheKey);
        $this->assertStringContainsString('GetCatalogDetail', $cacheKey);
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
        $this->procedure->catalogId = '123';

        $tags = iterator_to_array($this->procedure->getCacheTags($request));

        $this->assertContains('catalog', $tags);
        $this->assertContains('catalog_123', $tags);
    }

    public function testGetMockResult(): void
    {
        $mockResult = GetCatalogDetail::getMockResult();

        $this->assertIsArray($mockResult);
        $this->assertArrayHasKey('id', $mockResult);
        $this->assertArrayHasKey('name', $mockResult);
        $this->assertArrayHasKey('description', $mockResult);
        $this->assertArrayHasKey('level', $mockResult);
        $this->assertArrayHasKey('path', $mockResult);
        $this->assertArrayHasKey('enabled', $mockResult);
        $this->assertArrayHasKey('type', $mockResult);
        $this->assertArrayHasKey('createTime', $mockResult);
        $this->assertArrayHasKey('updateTime', $mockResult);
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

        $persistedCatalog = $this->persistAndFlush($catalog);
        $this->assertInstanceOf(Catalog::class, $persistedCatalog);

        // 在持久化后设置路径（使用 ID）
        if (null !== $parent) {
            $persistedCatalog->setPath($parent->getPath() . '/' . (string) $persistedCatalog->getId());
        } else {
            $persistedCatalog->setPath((string) $persistedCatalog->getId());
        }

        $finalCatalog = $this->persistAndFlush($persistedCatalog);
        $this->assertInstanceOf(Catalog::class, $finalCatalog);

        return $finalCatalog;
    }
}
