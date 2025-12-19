<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Tests\Procedure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CatalogBundle\Entity\Catalog;
use Tourze\CatalogBundle\Entity\CatalogType;
use Tourze\CatalogBundle\Param\GetCatalogTreeParam;
use Tourze\CatalogBundle\Procedure\GetCatalogTree;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\PHPUnitJsonRPC\AbstractProcedureTestCase;

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

        $param = new GetCatalogTreeParam(
            typeId: null,
            maxLevel: 2,
            enabledOnly: true,
            includeMetadata: false
        );

        $result = $this->procedure->execute($param);
        $data = $result->data;

        $this->assertIsArray($data);
        $this->assertArrayHasKey('tree', $data);
        $this->assertArrayHasKey('metadata', $data);

        /** @var array<string, mixed> $resultArray */
        $resultArray = $data;
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

        $param = new GetCatalogTreeParam(
            typeId: (string) $catalogType->getId(),
            enabledOnly: true
        );

        $result = $this->procedure->execute($param);
        $data = $result->data;

        $this->assertIsArray($data);
        $this->assertArrayHasKey('metadata', $data);

        /** @var array<string, mixed> $resultArray */
        $resultArray = $data;
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
        $param = new GetCatalogTreeParam(
            typeId: 'invalid-uuid-that-does-not-exist'
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('分类类型不存在');

        $this->procedure->execute($param);
    }

    public function testExecuteWithDisabledTypeWhenEnabledOnly(): void
    {
        // 创建一个禁用的分类类型
        $catalogType = $this->createCatalogType('禁用分类', 'disabled-' . uniqid(), false);

        $param = new GetCatalogTreeParam(
            typeId: (string) $catalogType->getId(),
            enabledOnly: true
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('分类类型未启用');

        $this->procedure->execute($param);
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
        $request = new JsonRpcRequest();
        $request->setMethod('test.method');

        $duration = $this->procedure->getCacheDuration($request);

        $this->assertEquals(900, $duration);
    }

    public function testGetCacheTags(): void
    {
        // 不带 typeId
        $params = new JsonRpcParams([]);
        $request = new JsonRpcRequest();
        $request->setMethod('test.method');
        $request->setParams($params);

        $tags = iterator_to_array($this->procedure->getCacheTags($request));

        $this->assertContains('catalog', $tags);
        $this->assertContains('catalog_tree', $tags);

        // 带 typeId
        $params = new JsonRpcParams(['typeId' => '123']);
        $request = new JsonRpcRequest();
        $request->setMethod('test.method');
        $request->setParams($params);

        $tags = iterator_to_array($this->procedure->getCacheTags($request));

        $this->assertContains('catalog', $tags);
        $this->assertContains('catalog_tree', $tags);
        $this->assertContains('catalog_type_123', $tags);
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
