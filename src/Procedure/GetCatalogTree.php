<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Procedure;

use Tourze\CatalogBundle\Entity\Catalog;
use Tourze\CatalogBundle\Entity\CatalogType;
use Tourze\CatalogBundle\Param\GetCatalogTreeParam;
use Tourze\CatalogBundle\Repository\CatalogRepository;
use Tourze\CatalogBundle\Repository\CatalogTypeRepository;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;
use Tourze\JsonRPC\Core\Result\ArrayResult;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPCCacheBundle\Procedure\CacheableProcedure;

#[MethodTag(name: '分类管理')]
#[MethodDoc(summary: '获取分类树形结构')]
#[MethodExpose(method: 'GetCatalogTree')]
final class GetCatalogTree extends CacheableProcedure
{
    public function __construct(
        private readonly CatalogRepository $catalogRepository,
        private readonly CatalogTypeRepository $catalogTypeRepository,
    ) {
    }

    /**
     * @phpstan-param GetCatalogTreeParam $param
     */
    public function execute(GetCatalogTreeParam|RpcParamInterface $param): ArrayResult
    {
        $catalogType = $this->validateAndGetCatalogType($param);
        $tree = $this->fetchRootCatalogs($catalogType, $param);

        return new ArrayResult([
            'tree' => $this->formatTreeNodes($tree, $param),
            'metadata' => $this->buildMetadata($catalogType, $tree, $param),
        ]);
    }

    private function validateAndGetCatalogType(GetCatalogTreeParam $param): ?CatalogType
    {
        if (!$this->hasValidTypeId($param)) {
            return null;
        }

        $catalogType = $this->catalogTypeRepository->find($param->typeId);
        if (null === $catalogType) {
            throw new ApiException('分类类型不存在');
        }

        if ($param->enabledOnly && !$catalogType->isEnabled()) {
            throw new ApiException('分类类型未启用');
        }

        return $catalogType;
    }

    /**
     * @return array<Catalog>
     */
    private function fetchRootCatalogs(?CatalogType $catalogType, GetCatalogTreeParam $param): array
    {
        if (null !== $catalogType) {
            return $this->fetchRootsByType($catalogType, $param);
        }

        return $this->fetchAllRoots($param);
    }

    /**
     * @return array<Catalog>
     */
    private function fetchRootsByType(CatalogType $catalogType, GetCatalogTreeParam $param): array
    {
        return $param->enabledOnly
            ? $this->catalogRepository->findEnabledRootsByType($catalogType)
            : $this->catalogRepository->findRootsByType($catalogType);
    }

    /**
     * @return array<Catalog>
     */
    private function fetchAllRoots(GetCatalogTreeParam $param): array
    {
        $qb = $this->catalogRepository->createQueryBuilder('c')
            ->where('c.parent IS NULL')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
        ;

        if ($param->enabledOnly) {
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
    private function buildMetadata(?CatalogType $catalogType, array $tree, GetCatalogTreeParam $param): array
    {
        return [
            'typeId' => $param->typeId,
            'typeName' => $catalogType?->getName(),
            'totalNodes' => $this->countTreeNodes($tree),
            'maxLevel' => $this->getMaxTreeLevel($tree),
        ];
    }

    private function hasValidTypeId(GetCatalogTreeParam $param): bool
    {
        return null !== $param->typeId && '' !== $param->typeId;
    }

    /**
     * @param array<Catalog> $nodes
     * @return array<array<string, mixed>>
     */
    private function formatTreeNodes(array $nodes, GetCatalogTreeParam $param): array
    {
        return array_map(function (Catalog $catalog) use ($param): array {
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

            if ($param->includeMetadata && null !== $catalog->getMetadata()) {
                $data['metadata'] = $catalog->getMetadata();
            }

            // 递归处理子节点
            $children = $catalog->getChildren()->toArray();
            if ([] !== $children && $catalog->getLevel() < $param->maxLevel - 1) {
                $data['children'] = $this->formatTreeNodes($children, $param);
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
        yield 'catalog';
        yield 'catalog_tree';

        $typeId = $request->getParams()?->get('typeId', null);
        if (null !== $typeId && '' !== $typeId) {
            yield 'catalog_type_' . $typeId;
        }
    }

    /**
     * @return array<string, mixed>
     */
}
