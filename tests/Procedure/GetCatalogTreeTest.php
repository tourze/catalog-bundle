<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Tests\Procedure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CatalogBundle\Entity\Catalog;
use Tourze\CatalogBundle\Entity\CatalogType;
use Tourze\CatalogBundle\Procedure\GetCatalogTree;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPC\Core\Tests\AbstractProcedureTestCase;

/**
 * @internal
 */
#[CoversClass(GetCatalogTree::class)]
#[RunTestsInSeparateProcesses]
final class GetCatalogTreeTest extends AbstractProcedureTestCase
{
    private GetCatalogTree $procedure;

    protected function onSetUp(): void
    {
        $this->procedure = self::getService(GetCatalogTree::class);
    }

    public function testExecuteWithoutTypeId(): void
    {
        // 创建测试数据
        $catalogType = $this->createCatalogType('商品分类', 'product-' . uniqid(), true);
        $parentCatalog = $this->createCatalog('数码产品', $catalogType);
        $childCatalog = $this->createCatalog('手机', $catalogType, $parentCatalog);

        $this->procedure->typeId = null;
        $this->procedure->maxLevel = 2;
        $this->procedure->enabledOnly = true;
        $this->procedure->includeMetadata = false;

        $result = $this->procedure->execute();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('tree', $result);
        $this->assertArrayHasKey('metadata', $result);

        /** @var array<string, mixed> $resultArray */
        $resultArray = $result;
        $this->assertIsArray($resultArray['metadata']);

        /** @var array<string, mixed> $metadata */
        $metadata = $resultArray['metadata'];
        $this->assertArrayHasKey('typeId', $metadata);
        $this->assertArrayHasKey('typeName', $metadata);
        $this->assertArrayHasKey('totalNodes', $metadata);
        $this->assertArrayHasKey('maxLevel', $metadata);
        $this->assertNull($metadata['typeId']);
        $this->assertNull($metadata['typeName']);
        $this->assertIsInt($metadata['totalNodes']);
        $this->assertIsInt($metadata['maxLevel']);
        $this->assertGreaterThanOrEqual(0, $metadata['totalNodes']);
        $this->assertEquals(2, $metadata['maxLevel']);
    }

    public function testExecuteWithValidTypeId(): void
    {
        // 创建测试数据
        $catalogType = $this->createCatalogType('商品分类', 'product-' . uniqid(), true);
        $parentCatalog = $this->createCatalog('数码产品', $catalogType);
        $childCatalog = $this->createCatalog('手机', $catalogType, $parentCatalog);

        $this->procedure->typeId = $catalogType->getId();
        $this->procedure->enabledOnly = true;

        $result = $this->procedure->execute();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('metadata', $result);

        /** @var array<string, mixed> $resultArray */
        $resultArray = $result;
        $this->assertIsArray($resultArray['metadata']);

        /** @var array<string, mixed> $metadata */
        $metadata = $resultArray['metadata'];
        $this->assertArrayHasKey('typeId', $metadata);
        $this->assertArrayHasKey('typeName', $metadata);
        $this->assertArrayHasKey('totalNodes', $metadata);
        $this->assertEquals($catalogType->getId(), $metadata['typeId']);
        $this->assertEquals('商品分类', $metadata['typeName']);
        $this->assertIsInt($metadata['totalNodes']);
        $this->assertGreaterThanOrEqual(0, $metadata['totalNodes']);
    }

    public function testExecuteWithInvalidTypeId(): void
    {
        $this->procedure->typeId = 'invalid-uuid-that-does-not-exist';

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('分类类型不存在');

        $this->procedure->execute();
    }

    public function testExecuteWithDisabledTypeWhenEnabledOnly(): void
    {
        // 创建一个禁用的分类类型
        $catalogType = $this->createCatalogType('禁用分类', 'disabled-' . uniqid(), false);

        $this->procedure->typeId = $catalogType->getId();
        $this->procedure->enabledOnly = true;

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('分类类型未启用');

        $this->procedure->execute();
    }

    public function testGetCacheKey(): void
    {
        $params = new JsonRpcParams(['typeId' => '123', 'maxLevel' => 3]);
        $request = new JsonRpcRequest();
        $request->setId('1');
        $request->setMethod('test.method');
        $request->setParams($params);

        $cacheKey = $this->procedure->getCacheKey($request);

        $this->assertIsString($cacheKey);
        $this->assertStringContainsString('GetCatalogTree', $cacheKey);
    }

    public function testGetCacheDuration(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);

        $duration = $this->procedure->getCacheDuration($request);

        $this->assertEquals(900, $duration);
    }

    public function testGetCacheTags(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);

        // 不带 typeId
        $this->procedure->typeId = null;
        $tags = iterator_to_array($this->procedure->getCacheTags($request));

        $this->assertContains('catalog', $tags);
        $this->assertContains('catalog_tree', $tags);

        // 带 typeId
        $this->procedure->typeId = '123';
        $tags = iterator_to_array($this->procedure->getCacheTags($request));

        $this->assertContains('catalog', $tags);
        $this->assertContains('catalog_tree', $tags);
        $this->assertContains('catalog_type_123', $tags);
    }

    public function testGetMockResult(): void
    {
        $mockResult = GetCatalogTree::getMockResult();

        $this->assertIsArray($mockResult);
        $this->assertArrayHasKey('tree', $mockResult);
        $this->assertArrayHasKey('metadata', $mockResult);

        $this->assertIsArray($mockResult['tree']);
        $this->assertIsArray($mockResult['metadata']);

        // 验证 metadata 结构
        $metadata = $mockResult['metadata'];
        $this->assertArrayHasKey('typeId', $metadata);
        $this->assertArrayHasKey('typeName', $metadata);
        $this->assertArrayHasKey('totalNodes', $metadata);
        $this->assertArrayHasKey('maxLevel', $metadata);
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

        /** @var CatalogType $result */
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

        if (null !== $parent) {
            $catalog->setParent($parent);
            $catalog->setLevel($parent->getLevel() + 1);
        } else {
            $catalog->setLevel(0);
        }

        $firstSave = $this->persistAndFlush($catalog);

        /** @var Catalog $firstSave */
        $this->assertInstanceOf(Catalog::class, $firstSave);

        // 在持久化后设置路径（使用 ID）
        if (null !== $parent) {
            $firstSave->setPath($parent->getPath() . '/' . (string) $firstSave->getId());
        } else {
            $firstSave->setPath((string) $firstSave->getId());
        }

        $finalSave = $this->persistAndFlush($firstSave);

        /** @var Catalog $finalSave */
        $this->assertInstanceOf(Catalog::class, $finalSave);

        return $finalSave;
    }
}
