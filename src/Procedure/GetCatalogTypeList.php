<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Procedure;

use Symfony\Component\Validator\Constraints as Assert;
use Tourze\CatalogBundle\Entity\CatalogType;
use Tourze\CatalogBundle\Repository\CatalogTypeRepository;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPCCacheBundle\Procedure\CacheableProcedure;
use Tourze\JsonRPCPaginatorBundle\Procedure\PaginatorTrait;

#[MethodTag(name: '分类管理')]
#[MethodDoc(summary: '获取分类类型列表')]
#[MethodExpose(method: 'GetCatalogTypeList')]
final class GetCatalogTypeList extends CacheableProcedure
{
    use PaginatorTrait;

    #[MethodParam(description: '搜索关键词')]
    public ?string $keyword = null;

    #[MethodParam(description: '是否只获取启用的类型')]
    public bool $enabledOnly = true;

    #[MethodParam(description: '是否包含分类数量统计')]
    public bool $includeCatalogCount = false;

    #[MethodParam(description: '排序字段')]
    #[Assert\Choice(choices: ['name', 'code', 'createTime', 'updateTime'])]
    public string $orderBy = 'createTime';

    #[MethodParam(description: '排序方向')]
    #[Assert\Choice(choices: ['ASC', 'DESC'])]
    public string $orderDir = 'DESC';

    public function __construct(
        private readonly CatalogTypeRepository $catalogTypeRepository,
    ) {
    }

    public function execute(): array
    {
        // 构建查询
        $qb = $this->catalogTypeRepository->createQueryBuilder('ct')
            ->orderBy('ct.' . $this->orderBy, $this->orderDir)
        ;

        // 添加筛选条件
        if ($this->enabledOnly) {
            $qb->andWhere('ct.enabled = :enabled')
                ->setParameter('enabled', true)
            ;
        }

        if (null !== $this->keyword && '' !== $this->keyword) {
            $qb->andWhere('ct.name LIKE :keyword OR ct.code LIKE :keyword OR ct.description LIKE :keyword')
                ->setParameter('keyword', '%' . $this->keyword . '%')
            ;
        }

        return $this->fetchList(
            $qb,
            fn (CatalogType $catalogType): array => $this->formatCatalogTypeData($catalogType)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCatalogTypeData(CatalogType $catalogType): array
    {
        $data = [
            'id' => $catalogType->getId(),
            'code' => $catalogType->getCode(),
            'name' => $catalogType->getName(),
            'description' => $catalogType->getDescription(),
            'enabled' => $catalogType->isEnabled(),
            'createTime' => $catalogType->getCreateTime()?->format('Y-m-d H:i:s'),
            'updateTime' => $catalogType->getUpdateTime()?->format('Y-m-d H:i:s'),
        ];

        // 包含分类数量统计
        if ($this->includeCatalogCount) {
            $data['catalogCount'] = $catalogType->getCatalogs()->count();
        }

        return $data;
    }

    public function getCacheKey(JsonRpcRequest $request): string
    {
        $params = $request->getParams();
        if (null === $params) {
            $params = new JsonRpcParams([]);
        }

        return $this->buildParamCacheKey($params);
    }

    public function getCacheDuration(JsonRpcRequest $request): int
    {
        return 1800; // 30分钟 - 类型变化较少
    }

    /**
     * @return iterable<string>
     */
    public function getCacheTags(JsonRpcRequest $request): iterable
    {
        return ['catalog_type', 'catalog_type_list'];
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
                    'code' => 'product',
                    'name' => '商品分类',
                    'description' => '商品分类体系，用于管理商品的层级分类',
                    'enabled' => true,
                    'catalogCount' => 25,
                    'createTime' => '2024-01-01 12:00:00',
                    'updateTime' => '2024-01-01 12:00:00',
                ],
                [
                    'id' => '2',
                    'code' => 'article',
                    'name' => '文章分类',
                    'description' => '文章内容分类，用于管理文章的类别划分',
                    'enabled' => true,
                    'catalogCount' => 12,
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
