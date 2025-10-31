<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Procedure;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\CatalogBundle\Entity\Catalog;
use Tourze\CatalogBundle\Repository\CatalogRepository;
use Tourze\CatalogBundle\Repository\CatalogTypeRepository;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Procedure\BaseProcedure;
use Tourze\JsonRPCPaginatorBundle\Procedure\PaginatorTrait;

#[MethodTag(name: '分类管理')]
#[MethodDoc(summary: '获取分类列表')]
#[MethodExpose(method: 'GetCatalogList')]
final class GetCatalogList extends BaseProcedure
{
    use PaginatorTrait;

    #[MethodParam(description: '分类类型编码')]
    public ?string $typeCode = null;

    #[MethodParam(description: '父级分类ID')]
    public ?string $parentId = null;

    #[MethodParam(description: '搜索关键词')]
    public ?string $keyword = null;

    #[MethodParam(description: '是否只获取启用的分类')]
    public bool $enabledOnly = true;

    #[MethodParam(description: '是否包含子分类数量')]
    public bool $includeChildrenCount = false;

    #[MethodParam(description: '排序字段')]
    #[Assert\Choice(choices: ['sortOrder', 'name', 'createTime', 'updateTime'])]
    public string $orderBy = 'sortOrder';

    #[MethodParam(description: '排序方向')]
    #[Assert\Choice(choices: ['ASC', 'DESC'])]
    public string $orderDir = 'ASC';

    public function __construct(
        private readonly CatalogRepository $catalogRepository,
        private readonly CatalogTypeRepository $catalogTypeRepository,
    ) {
    }

    public function execute(): array
    {
        $this->validateTypeCode();
        $this->validateParentId();

        $qb = $this->buildQuery();

        return $this->fetchList(
            $qb,
            fn (Catalog $catalog): array => $this->formatCatalogData($catalog)
        );
    }

    private function validateTypeCode(): void
    {
        if (!$this->hasValidTypeCode()) {
            return;
        }

        $catalogType = $this->catalogTypeRepository->findOneByCode((string) $this->typeCode);
        if (null === $catalogType) {
            throw new ApiException('分类类型不存在');
        }

        if ($this->enabledOnly && !$catalogType->isEnabled()) {
            throw new ApiException('分类类型未启用');
        }
    }

    private function validateParentId(): void
    {
        if (!$this->hasValidParentId()) {
            return;
        }

        $parentCatalog = $this->catalogRepository->find($this->parentId);
        if (null === $parentCatalog) {
            throw new ApiException('父级分类不存在');
        }

        if ($this->enabledOnly && !$parentCatalog->isEnabled()) {
            throw new ApiException('父级分类未启用');
        }
    }

    private function buildQuery(): QueryBuilder
    {
        $qb = $this->catalogRepository->createQueryBuilder('c')
            ->orderBy('c.' . $this->orderBy, $this->orderDir)
        ;

        $this->applyTypeFilter($qb);
        $this->applyParentFilter($qb);
        $this->applyEnabledFilter($qb);
        $this->applyKeywordFilter($qb);

        return $qb;
    }

    private function applyTypeFilter(QueryBuilder $qb): void
    {
        if ($this->hasValidTypeCode()) {
            $qb->join('c.type', 'ct')
                ->andWhere('ct.code = :typeCode')
                ->setParameter('typeCode', $this->typeCode)
            ;
        }
    }

    private function applyParentFilter(QueryBuilder $qb): void
    {
        if ($this->hasValidParentId()) {
            $qb->andWhere('c.parent = :parentId')
                ->setParameter('parentId', $this->parentId)
            ;
        } elseif (null === $this->parentId) {
            $qb->andWhere('c.parent IS NULL');
        }
    }

    private function applyEnabledFilter(QueryBuilder $qb): void
    {
        if ($this->enabledOnly) {
            $qb->andWhere('c.enabled = :enabled')
                ->setParameter('enabled', true)
            ;
        }
    }

    private function applyKeywordFilter(QueryBuilder $qb): void
    {
        if ($this->hasValidKeyword()) {
            $qb->andWhere('c.name LIKE :keyword OR c.description LIKE :keyword')
                ->setParameter('keyword', '%' . $this->keyword . '%')
            ;
        }
    }

    private function hasValidTypeCode(): bool
    {
        return null !== $this->typeCode && '' !== $this->typeCode;
    }

    private function hasValidParentId(): bool
    {
        return null !== $this->parentId && '' !== $this->parentId;
    }

    private function hasValidKeyword(): bool
    {
        return null !== $this->keyword && '' !== $this->keyword;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCatalogData(Catalog $catalog): array
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

        if ($this->includeChildrenCount) {
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
    public static function getMockResult(): array
    {
        return [
            'list' => [
                [
                    'id' => '1',
                    'name' => '数码产品',
                    'description' => '各类数码产品分类',
                    'level' => 0,
                    'path' => 'digital',
                    'sortOrder' => 1,
                    'enabled' => true,
                    'hasChildren' => true,
                    'type' => [
                        'id' => '1',
                        'name' => '商品分类',
                        'code' => 'product',
                    ],
                    'parent' => null,
                    'childrenCount' => 5,
                    'createTime' => '2024-01-01 12:00:00',
                    'updateTime' => '2024-01-01 12:00:00',
                ],
                [
                    'id' => '2',
                    'name' => '服装鞋包',
                    'description' => '时尚服装和配件',
                    'level' => 0,
                    'path' => 'fashion',
                    'sortOrder' => 2,
                    'enabled' => true,
                    'hasChildren' => true,
                    'type' => [
                        'id' => '1',
                        'name' => '商品分类',
                        'code' => 'product',
                    ],
                    'parent' => null,
                    'childrenCount' => 3,
                    'createTime' => '2024-01-01 12:00:00',
                    'updateTime' => '2024-01-01 12:00:00',
                ],
            ],
            'pagination' => [
                'current' => 1,
                'pageSize' => 20,
                'total' => 2,
                'hasMore' => false,
            ],
        ];
    }
}
