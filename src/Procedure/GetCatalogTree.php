<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Procedure;

use Symfony\Component\Validator\Constraints as Assert;
use Tourze\CatalogBundle\Entity\Catalog;
use Tourze\CatalogBundle\Entity\CatalogType;
use Tourze\CatalogBundle\Repository\CatalogRepository;
use Tourze\CatalogBundle\Repository\CatalogTypeRepository;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPCCacheBundle\Procedure\CacheableProcedure;

#[MethodTag(name: '分类管理')]
#[MethodDoc(summary: '获取分类树形结构')]
#[MethodExpose(method: 'GetCatalogTree')]
final class GetCatalogTree extends CacheableProcedure
{
    #[MethodParam(description: '分类类型ID')]
    public ?string $typeId = null;

    #[MethodParam(description: '最大层级深度')]
    #[Assert\Range(min: 1, max: 10)]
    public int $maxLevel = 5;

    #[MethodParam(description: '是否只获取启用的分类')]
    public bool $enabledOnly = true;

    #[MethodParam(description: '是否包含元数据')]
    public bool $includeMetadata = false;

    public function __construct(
        private readonly CatalogRepository $catalogRepository,
        private readonly CatalogTypeRepository $catalogTypeRepository,
    ) {
    }

    public function execute(): array
    {
        $catalogType = $this->validateAndGetCatalogType();
        $tree = $this->fetchRootCatalogs($catalogType);

        return [
            'tree' => $this->formatTreeNodes($tree),
            'metadata' => $this->buildMetadata($catalogType, $tree),
        ];
    }

    private function validateAndGetCatalogType(): ?CatalogType
    {
        if (!$this->hasValidTypeId()) {
            return null;
        }

        $catalogType = $this->catalogTypeRepository->find($this->typeId);
        if (null === $catalogType) {
            throw new ApiException('分类类型不存在');
        }

        if ($this->enabledOnly && !$catalogType->isEnabled()) {
            throw new ApiException('分类类型未启用');
        }

        return $catalogType;
    }

    /**
     * @return array<Catalog>
     */
    private function fetchRootCatalogs(?CatalogType $catalogType): array
    {
        if (null !== $catalogType) {
            return $this->fetchRootsByType($catalogType);
        }

        return $this->fetchAllRoots();
    }

    /**
     * @return array<Catalog>
     */
    private function fetchRootsByType(CatalogType $catalogType): array
    {
        return $this->enabledOnly
            ? $this->catalogRepository->findEnabledRootsByType($catalogType)
            : $this->catalogRepository->findRootsByType($catalogType);
    }

    /**
     * @return array<Catalog>
     */
    private function fetchAllRoots(): array
    {
        $qb = $this->catalogRepository->createQueryBuilder('c')
            ->where('c.parent IS NULL')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
        ;

        if ($this->enabledOnly) {
            $qb->andWhere('c.enabled = :enabled')
                ->setParameter('enabled', true)
            ;
        }

        /** @var array<Catalog> */
        return $qb->getQuery()->getResult();
    }

    /**
     * @param array<Catalog> $tree
     * @return array<string, mixed>
     */
    private function buildMetadata(?CatalogType $catalogType, array $tree): array
    {
        return [
            'typeId' => $this->typeId,
            'typeName' => $catalogType?->getName(),
            'totalNodes' => $this->countTreeNodes($tree),
            'maxLevel' => $this->getMaxTreeLevel($tree),
        ];
    }

    private function hasValidTypeId(): bool
    {
        return null !== $this->typeId && '' !== $this->typeId;
    }

    /**
     * @param array<Catalog> $nodes
     * @return array<array<string, mixed>>
     */
    private function formatTreeNodes(array $nodes): array
    {
        return array_map(function (Catalog $catalog): array {
            $data = [
                'id' => $catalog->getId(),
                'name' => $catalog->getName(),
                'description' => $catalog->getDescription(),
                'level' => $catalog->getLevel(),
                'path' => $catalog->getPath(),
                'sortOrder' => $catalog->getSortOrder(),
                'enabled' => $catalog->isEnabled(),
                'hasChildren' => !$catalog->getChildren()->isEmpty(),
            ];

            if ($this->includeMetadata && null !== $catalog->getMetadata()) {
                $data['metadata'] = $catalog->getMetadata();
            }

            // 递归处理子节点
            $children = $catalog->getChildren()->toArray();
            if ([] !== $children && $catalog->getLevel() < $this->maxLevel - 1) {
                $data['children'] = $this->formatTreeNodes($children);
            } else {
                $data['children'] = [];
            }

            return $data;
        }, $nodes);
    }

    /**
     * @param array<Catalog> $nodes
     */
    private function countTreeNodes(array $nodes): int
    {
        $count = count($nodes);
        foreach ($nodes as $node) {
            $count += $this->countTreeNodes($node->getChildren()->toArray());
        }

        return $count;
    }

    /**
     * @param array<Catalog> $nodes
     */
    private function getMaxTreeLevel(array $nodes): int
    {
        $maxLevel = 0;
        foreach ($nodes as $node) {
            $maxLevel = max($maxLevel, $node->getLevel());
            if (!$node->getChildren()->isEmpty()) {
                $maxLevel = max($maxLevel, $this->getMaxTreeLevel($node->getChildren()->toArray()));
            }
        }

        return $maxLevel;
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
        return 900; // 15分钟
    }

    /**
     * @return iterable<string>
     */
    public function getCacheTags(JsonRpcRequest $request): iterable
    {
        $tags = ['catalog', 'catalog_tree'];
        if (null !== $this->typeId && '' !== $this->typeId) {
            $tags[] = 'catalog_type_' . $this->typeId;
        }

        return $tags;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getMockResult(): array
    {
        return [
            'tree' => [
                [
                    'id' => '1',
                    'name' => '数码产品',
                    'description' => '各类数码产品分类',
                    'level' => 0,
                    'path' => 'digital',
                    'sortOrder' => 1,
                    'enabled' => true,
                    'hasChildren' => true,
                    'children' => [
                        [
                            'id' => '2',
                            'name' => '手机',
                            'description' => '智能手机分类',
                            'level' => 1,
                            'path' => 'digital/phones',
                            'sortOrder' => 1,
                            'enabled' => true,
                            'hasChildren' => false,
                            'children' => [],
                        ],
                    ],
                ],
            ],
            'metadata' => [
                'typeId' => '1',
                'typeName' => '商品分类',
                'totalNodes' => 10,
                'maxLevel' => 2,
            ],
        ];
    }
}
