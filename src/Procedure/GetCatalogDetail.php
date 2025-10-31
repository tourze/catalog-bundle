<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Procedure;

use Symfony\Component\Validator\Constraints as Assert;
use Tourze\CatalogBundle\Entity\Catalog;
use Tourze\CatalogBundle\Repository\CatalogRepository;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPCCacheBundle\Procedure\CacheableProcedure;

#[MethodTag(name: '分类管理')]
#[MethodDoc(summary: '获取分类详情')]
#[MethodExpose(method: 'GetCatalogDetail')]
final class GetCatalogDetail extends CacheableProcedure
{
    #[MethodParam(description: '分类ID')]
    #[Assert\NotBlank]
    public string $catalogId;

    #[MethodParam(description: '是否包含祖先分类')]
    public bool $includeAncestors = false;

    #[MethodParam(description: '是否包含直接子分类')]
    public bool $includeChildren = false;

    #[MethodParam(description: '是否包含兄弟分类')]
    public bool $includeSiblings = false;

    #[MethodParam(description: '是否只获取启用的分类')]
    public bool $enabledOnly = true;

    public function __construct(
        private readonly CatalogRepository $catalogRepository,
    ) {
    }

    public function execute(): array
    {
        $catalog = $this->validateAndGetCatalog();
        $result = $this->buildBasicCatalogData($catalog);

        $result = $this->appendAncestorsIfRequested($result, $catalog);
        $result = $this->appendChildrenIfRequested($result, $catalog);

        return $this->appendSiblingsIfRequested($result, $catalog);
    }

    private function validateAndGetCatalog(): Catalog
    {
        $catalog = $this->catalogRepository->find($this->catalogId);
        if (null === $catalog) {
            throw new ApiException('分类不存在');
        }

        if ($this->enabledOnly && !$catalog->isEnabled()) {
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
    private function appendAncestorsIfRequested(array $result, Catalog $catalog): array
    {
        if (!$this->includeAncestors) {
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
    private function appendChildrenIfRequested(array $result, Catalog $catalog): array
    {
        if (!$this->includeChildren) {
            return $result;
        }

        $children = $this->getFilteredChildren($catalog);
        $result['children'] = $this->formatCatalogNodes($children);
        usort($result['children'], static fn (array $a, array $b): int => $a['sortOrder'] <=> $b['sortOrder']);

        return $result;
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function appendSiblingsIfRequested(array $result, Catalog $catalog): array
    {
        if (!$this->includeSiblings || null === $catalog->getParent()) {
            return $result;
        }

        $siblings = $this->getFilteredSiblings($catalog);
        $result['siblings'] = $this->formatCatalogNodes($siblings);
        usort($result['siblings'], static fn (array $a, array $b): int => $a['sortOrder'] <=> $b['sortOrder']);

        return $result;
    }

    /**
     * @return array<Catalog>
     */
    private function getFilteredChildren(Catalog $catalog): array
    {
        $children = $catalog->getChildren()->toArray();

        return $this->enabledOnly
            ? array_filter($children, static fn (Catalog $child): bool => $child->isEnabled())
            : $children;
    }

    /**
     * @return array<Catalog>
     */
    private function getFilteredSiblings(Catalog $catalog): array
    {
        $parent = $catalog->getParent();
        if (null === $parent) {
            return [];
        }

        $siblings = $parent->getChildren()->toArray();
        $siblings = array_filter($siblings, static fn (Catalog $sibling): bool => $sibling->getId() !== $catalog->getId());

        return $this->enabledOnly
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
        return [
            'catalog',
            'catalog_' . $this->catalogId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getMockResult(): array
    {
        return [
            'id' => '1',
            'name' => '数码产品',
            'description' => '各类数码产品分类，包含手机、电脑、相机等电子产品',
            'level' => 0,
            'path' => 'digital',
            'sortOrder' => 1,
            'enabled' => true,
            'metadata' => [
                'icon' => 'digital-icon.svg',
                'banner' => 'digital-banner.jpg',
                'keywords' => ['数码', '电子', '科技'],
            ],
            'type' => [
                'id' => '1',
                'name' => '商品分类',
                'code' => 'product',
                'description' => '商品分类体系',
            ],
            'parent' => null,
            'ancestors' => [],
            'children' => [
                [
                    'id' => '2',
                    'name' => '手机',
                    'path' => 'digital/phones',
                    'level' => 1,
                    'sortOrder' => 1,
                    'enabled' => true,
                    'hasChildren' => true,
                ],
                [
                    'id' => '3',
                    'name' => '电脑',
                    'path' => 'digital/computers',
                    'level' => 1,
                    'sortOrder' => 2,
                    'enabled' => true,
                    'hasChildren' => true,
                ],
            ],
            'siblings' => [],
            'createTime' => '2024-01-01 12:00:00',
            'updateTime' => '2024-01-01 12:00:00',
        ];
    }
}
