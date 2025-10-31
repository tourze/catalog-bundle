<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Service;

use Knp\Menu\ItemInterface;
use Tourze\CatalogBundle\Entity\Catalog;
use Tourze\CatalogBundle\Entity\CatalogType;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;

/**
 * 分类管理菜单服务
 */
readonly class AdminMenu implements MenuProviderInterface
{
    public function __construct(
        private LinkGeneratorInterface $linkGenerator,
    ) {
    }

    public function __invoke(ItemInterface $item): void
    {
        if (null === $item->getChild('分类管理')) {
            $item->addChild('分类管理');
        }

        $catalogMenu = $item->getChild('分类管理');
        if (null === $catalogMenu) {
            return;
        }

        // 分类类型管理菜单
        $catalogMenu->addChild('分类类型')
            ->setUri($this->linkGenerator->getCurdListPage(CatalogType::class))
            ->setAttribute('icon', 'fas fa-tags')
        ;

        // 分类管理菜单
        $catalogMenu->addChild('分类管理')
            ->setUri($this->linkGenerator->getCurdListPage(Catalog::class))
            ->setAttribute('icon', 'fas fa-sitemap')
        ;
    }
}
