<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Procedure;

use Tourze\CatalogBundle\Entity\CatalogType;
use Tourze\CatalogBundle\Param\GetCatalogTypeListParam;
use Tourze\CatalogBundle\Repository\CatalogTypeRepository;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;
use Tourze\JsonRPC\Core\Result\ArrayResult;
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

    public function __construct(
        private readonly CatalogTypeRepository $catalogTypeRepository,
    ) {
    }

    /**
     * @phpstan-param GetCatalogTypeListParam $param
     */
    public function execute(GetCatalogTypeListParam|RpcParamInterface $param): ArrayResult
    {
        // 构建查询
        $qb = $this->catalogTypeRepository->createQueryBuilder('ct')
            ->orderBy('ct.' . $param->orderBy, $param->orderDir)
        ;

        // 添加筛选条件
        if ($param->enabledOnly) {
            $qb->andWhere('ct.enabled = :enabled')
                ->setParameter('enabled', true)
            ;
        }

        if (null !== $param->keyword && '' !== $param->keyword) {
            $qb->andWhere('ct.name LIKE :keyword OR ct.code LIKE :keyword OR ct.description LIKE :keyword')
                ->setParameter('keyword', '%' . $param->keyword . '%')
            ;
        }

        return new ArrayResult($this->fetchList(
            $qb,
            fn (CatalogType $catalogType): array => $this->formatCatalogTypeData($catalogType, $param),
            null,
            $param
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCatalogTypeData(CatalogType $catalogType, GetCatalogTypeListParam $param): array
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
        if ($param->includeCatalogCount) {
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
        yield 'catalog_type';
        yield 'catalog_type_list';
    }

    /**
     * @return array<string, mixed>
     */
}
