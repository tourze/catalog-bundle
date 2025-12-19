<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Param;

use Symfony\Component\Validator\Constraints as Assert;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;
use Tourze\JsonRPCPaginatorBundle\Param\PaginatorParamInterface;

readonly class GetCatalogListParam implements PaginatorParamInterface
{
    public function __construct(
        #[MethodParam(description: '分类类型编码')]
        public ?string $typeCode = null,

        #[MethodParam(description: '父级分类ID')]
        public ?string $parentId = null,

        #[MethodParam(description: '搜索关键词')]
        public ?string $keyword = null,

        #[MethodParam(description: '是否只获取启用的分类')]
        public bool $enabledOnly = true,

        #[MethodParam(description: '是否包含子分类数量')]
        public bool $includeChildrenCount = false,

        #[MethodParam(description: '排序字段')]
        #[Assert\Choice(choices: ['sortOrder', 'name', 'createTime', 'updateTime'])]
        public string $orderBy = 'sortOrder',

        #[MethodParam(description: '排序方向')]
        #[Assert\Choice(choices: ['ASC', 'DESC'])]
        public string $orderDir = 'ASC',

        #[MethodParam(description: '每页条数')]
        #[Assert\Range(min: 1, max: 2000)]
        public int $pageSize = 10,

        #[MethodParam(description: '当前页数')]
        #[Assert\Range(min: 1, max: 1000)]
        public int $currentPage = 1,

        #[MethodParam(description: '上一次拉取时，最后一条数据的主键ID')]
        public ?int $lastId = null,
    ) {
    }
}
