<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Param;

use Symfony\Component\Validator\Constraints as Assert;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;

/**
 * GetCatalogDetail Procedure 的参数对象
 *
 * 用于获取分类详情的请求参数
 */
readonly class GetCatalogDetailParam implements RpcParamInterface
{
    public function __construct(
        #[MethodParam(description: '分类ID')]
        #[Assert\NotBlank]
        public string $catalogId,

        #[MethodParam(description: '是否包含祖先分类')]
        public bool $includeAncestors = false,

        #[MethodParam(description: '是否包含直接子分类')]
        public bool $includeChildren = false,

        #[MethodParam(description: '是否包含兄弟分类')]
        public bool $includeSiblings = false,

        #[MethodParam(description: '是否只获取启用的分类')]
        public bool $enabledOnly = true,
    ) {
    }
}
