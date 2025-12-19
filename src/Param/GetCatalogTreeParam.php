<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Param;

use Symfony\Component\Validator\Constraints as Assert;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;

readonly class GetCatalogTreeParam implements RpcParamInterface
{
    public function __construct(
        #[MethodParam(description: '分类类型ID')]
        public ?string $typeId = null,
        #[MethodParam(description: '最大层级深度')]
        #[Assert\Range(min: 1, max: 10)]
        public int $maxLevel = 5,
        #[MethodParam(description: '是否只获取启用的分类')]
        public bool $enabledOnly = true,
        #[MethodParam(description: '是否包含元数据')]
        public bool $includeMetadata = false,
    ) {
    }
}
