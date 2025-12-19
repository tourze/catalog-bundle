<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Procedure;

use Tourze\CatalogBundle\Entity\Catalog;
use Tourze\CatalogBundle\Param\GetCatalogDetailParam;
use Tourze\CatalogBundle\Repository\CatalogRepository;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;
use Tourze\JsonRPC\Core\Result\ArrayResult;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPCCacheBundle\Procedure\CacheableProcedure;

#[MethodTag(name: '分类管理')]
#[MethodDoc(summary: '获取分类详情')]
#[MethodExpose(method: 'GetCatalogDetail')]
final class GetCatalogDetail extends CacheableProcedure
{
    public function __construct(
        private readonly CatalogRepository $catalogRepository,
    ) {
    }

    /**
     * @phpstan-param GetCatalogDetailParam $param
     */
    public function execute(GetCatalogDetailParam|RpcParamInterface $param): ArrayResult
    {
        $catalog = $this->validateAndGetCatalog($param);
        $result = $this->buildBasicCatalogData($catalog);

        $result = $this->appendAncestorsIfRequested($result, $catalog, $param);
        $result = $this->appendChildrenIfRequested($result, $catalog, $param);
        $result = $this->appendSiblingsIfRequested($result, $catalog, $param);

        return new ArrayResult($result);
    }

    private function validateAndGetCatalog(GetCatalogDetailParam $param): Catalog
    {
        $catalog = $this->catalogRepository->find($param->catalogId);
        if (null === $catalog) {
            throw new ApiException('分类不存在');
        }

        if ($param->enabledOnly && !$catalog->isEnabled()) {
            throw new ApiException('分类未启用');
        }

        return $catalog;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBasicCatalogData(Catalog $catalog): array
    {
        return [
            'id' => $catalog->getId(),
            'name' => $catalog->getName(),
            'description' => $catalog->getDescription(),
            'level' => $catalog->getLevel(),
            'path' => $catalog->getPath(),
            'sortOrder' => $catalog->getSortOrder(),
            'enabled' => $catalog->isEnabled(),
            'metadata' => $catalog->getMetadata(),
            'type' => [
                'id' => $catalog->getType()?->getId(),
                'name' => $catalog->getType()?->getName(),
                'code' => $catalog->getType()?->getCode(),
                'description' => $catalog->getType()?->getDescription(),
            ],
            'parent' => null !== $catalog->getParent() ? [
                'id' => $catalog->getParent()->getId(),
                'name' => $catalog->getParent()->getName(),
                'path' => $catalog->getParent()->getPath(),
            ] : null,
            'createTime' => $catalog->getCreateTime()?->format('Y-m-d H:i:s'),
            'updateTime' => $catalog->getUpdateTime()?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function appendAncestorsIfRequested(array $result, Catalog $catalog, GetCatalogDetailParam $param): array
    {
        if (!$param->includeAncestors) {
            return $result;
        }

        $ancestors = $catalog->getAncestors();
        $result['ancestors'] = array_map(fn ($ancestor) => [
            'id' => $ancestor->getId(),
            'name' => $ancestor->getName(),
            'path' => $ancestor->getPath(),
            'level' => $ancestor->getLevel(),
        ], $ancestors);

        return $result;
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function appendChildrenIfRequested(array $result, Catalog $catalog, GetCatalogDetailParam $param): array
    {
        if (!$param->includeChildren) {
            return $result;
        }

        $children = $this->getFilteredChildren($catalog, $param);
        $result['children'] = $this->formatCatalogNodes($children);
        usort($result['children'], static fn (array $a, array $b): int => $a['sortOrder'] <=> $b['sortOrder']);

        return $result;
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function appendSiblingsIfRequested(array $result, Catalog $catalog, GetCatalogDetailParam $param): array
    {
        if (!$param->includeSiblings || null === $catalog->getParent()) {
            return $result;
        }

        $siblings = $this->getFilteredSiblings($catalog, $param);
        $result['siblings'] = $this->formatCatalogNodes($siblings);
        usort($result['siblings'], static fn (array $a, array $b): int => $a['sortOrder'] <=> $b['sortOrder']);

        return $result;
    }

    /**
     * @return array<Catalog>
     */
    private function getFilteredChildren(Catalog $catalog, GetCatalogDetailParam $param): array
    {
        $children = $catalog->getChildren()->toArray();

        return $param->enabledOnly
            ? array_filter($children, static fn (Catalog $child): bool => $child->isEnabled())
            : $children;
    }

    /**
     * @return array<Catalog>
     */
    private function getFilteredSiblings(Catalog $catalog, GetCatalogDetailParam $param): array
    {
        $parent = $catalog->getParent();
        if (null === $parent) {
            return [];
        }

        $siblings = $parent->getChildren()->toArray();
        $siblings = array_filter($siblings, static fn (Catalog $sibling): bool => $sibling->getId() !== $catalog->getId());

        return $param->enabledOnly
            ? array_filter($siblings, static fn (Catalog $sibling): bool => $sibling->isEnabled())
            : $siblings;
    }

    /**
     * @param array<Catalog> $nodes
     * @return array<array<string, mixed>>
     */
    private function formatCatalogNodes(array $nodes): array
    {
        return array_map(static fn (Catalog $node): array => [
            'id' => $node->getId(),
            'name' => $node->getName(),
            'path' => $node->getPath(),
            'level' => $node->getLevel(),
            'sortOrder' => $node->getSortOrder(),
            'enabled' => $node->isEnabled(),
            'hasChildren' => !$node->getChildren()->isEmpty(),
        ], array_values($nodes));
    }

    public function getCacheKey(JsonRpcRequest $request): string
    {
        $params = $request->getParams();
        if (null === $params) {
            return '';
        }

        return $this->buildParamCacheKey($params);
    }

    public function getCacheDuration(JsonRpcRequest $request): int
    {
        return 1800; // 30分钟
    }

    /**
     * @return iterable<string>
     */
    public function getCacheTags(JsonRpcRequest $request): iterable
    {
        $catalogId = $request->getParams()?->get('catalogId', '');

        yield 'catalog';
        yield 'catalog_' . $catalogId;
    }

    /**
     * @return array<string, mixed>
     */
}
