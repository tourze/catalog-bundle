<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Procedure;

use Doctrine\ORM\QueryBuilder;
use Tourze\CatalogBundle\Entity\Catalog;
use Tourze\CatalogBundle\Param\GetCatalogListParam;
use Tourze\CatalogBundle\Repository\CatalogRepository;
use Tourze\CatalogBundle\Repository\CatalogTypeRepository;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;
use Tourze\JsonRPC\Core\Result\ArrayResult;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Procedure\BaseProcedure;
use Tourze\JsonRPCPaginatorBundle\Procedure\PaginatorTrait;

#[MethodTag(name: '分类管理')]
#[MethodDoc(summary: '获取分类列表')]
#[MethodExpose(method: 'GetCatalogList')]
final class GetCatalogList extends BaseProcedure
{
    use PaginatorTrait;

    public function __construct(
        private readonly CatalogRepository $catalogRepository,
        private readonly CatalogTypeRepository $catalogTypeRepository,
    ) {
    }

    /**
     * @phpstan-param GetCatalogListParam $param
     */
    public function execute(GetCatalogListParam|RpcParamInterface $param): ArrayResult
    {
        $this->validateTypeCode($param);
        $this->validateParentId($param);

        $qb = $this->buildQuery($param);

        return new ArrayResult($this->fetchList(
            $qb,
            fn (Catalog $catalog): array => $this->formatCatalogData($catalog, $param),
            null,
            $param
        ));
    }

    private function validateTypeCode(GetCatalogListParam $param): void
    {
        if (!$this->hasValidTypeCode($param)) {
            return;
        }

        $catalogType = $this->catalogTypeRepository->findOneByCode((string) $param->typeCode);
        if (null === $catalogType) {
            throw new ApiException('分类类型不存在');
        }

        if ($param->enabledOnly && !$catalogType->isEnabled()) {
            throw new ApiException('分类类型未启用');
        }
    }

    private function validateParentId(GetCatalogListParam $param): void
    {
        if (!$this->hasValidParentId($param)) {
            return;
        }

        $parentCatalog = $this->catalogRepository->find($param->parentId);
        if (null === $parentCatalog) {
            throw new ApiException('父级分类不存在');
        }

        if ($param->enabledOnly && !$parentCatalog->isEnabled()) {
            throw new ApiException('父级分类未启用');
        }
    }

    private function buildQuery(GetCatalogListParam $param): QueryBuilder
    {
        $qb = $this->catalogRepository->createQueryBuilder('c')
            ->orderBy('c.' . $param->orderBy, $param->orderDir)
        ;

        $this->applyTypeFilter($qb, $param);
        $this->applyParentFilter($qb, $param);
        $this->applyEnabledFilter($qb, $param);
        $this->applyKeywordFilter($qb, $param);

        return $qb;
    }

    private function applyTypeFilter(QueryBuilder $qb, GetCatalogListParam $param): void
    {
        if ($this->hasValidTypeCode($param)) {
            $qb->join('c.type', 'ct')
                ->andWhere('ct.code = :typeCode')
                ->setParameter('typeCode', $param->typeCode)
            ;
        }
    }

    private function applyParentFilter(QueryBuilder $qb, GetCatalogListParam $param): void
    {
        if ($this->hasValidParentId($param)) {
            $qb->andWhere('c.parent = :parentId')
                ->setParameter('parentId', $param->parentId)
            ;
        } elseif (null === $param->parentId) {
            $qb->andWhere('c.parent IS NULL');
        }
    }

    private function applyEnabledFilter(QueryBuilder $qb, GetCatalogListParam $param): void
    {
        if ($param->enabledOnly) {
            $qb->andWhere('c.enabled = :enabled')
                ->setParameter('enabled', true)
            ;
        }
    }

    private function applyKeywordFilter(QueryBuilder $qb, GetCatalogListParam $param): void
    {
        if ($this->hasValidKeyword($param)) {
            $qb->andWhere('c.name LIKE :keyword OR c.description LIKE :keyword')
                ->setParameter('keyword', '%' . $param->keyword . '%')
            ;
        }
    }

    private function hasValidTypeCode(GetCatalogListParam $param): bool
    {
        return null !== $param->typeCode && '' !== $param->typeCode;
    }

    private function hasValidParentId(GetCatalogListParam $param): bool
    {
        return null !== $param->parentId && '' !== $param->parentId;
    }

    private function hasValidKeyword(GetCatalogListParam $param): bool
    {
        return null !== $param->keyword && '' !== $param->keyword;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCatalogData(Catalog $catalog, GetCatalogListParam $param): array
    {
        $data = [
            'id' => $catalog->getId(),
            'name' => $catalog->getName(),
            'description' => $catalog->getDescription(),
            'level' => $catalog->getLevel(),
            'path' => $catalog->getPath(),
            'sortOrder' => $catalog->getSortOrder(),
            'enabled' => $catalog->isEnabled(),
            'thumb' => $catalog->getThumb(),
            'hasChildren' => !$catalog->getChildren()->isEmpty(),
            'type' => [
                'id' => $catalog->getType()?->getId(),
                'name' => $catalog->getType()?->getName(),
                'code' => $catalog->getType()?->getCode(),
            ],
            'parent' => ($parent = $catalog->getParent()) !== null ? [
                'id' => $parent->getId(),
                'name' => $parent->getName(),
                'path' => $parent->getPath(),
            ] : null,
            'createTime' => $catalog->getCreateTime()?->format('Y-m-d H:i:s'),
            'updateTime' => $catalog->getUpdateTime()?->format('Y-m-d H:i:s'),
        ];

        if ($param->includeChildrenCount) {
            $data['childrenCount'] = $catalog->getChildren()->count();
        }

        $metadata = $catalog->getMetadata();
        if (null !== $metadata) {
            $data['metadata'] = $metadata;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
}
