<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\CatalogBundle\Entity\CatalogType;

class CatalogTypeFixtures extends Fixture
{
    public const REFERENCE_PRODUCT_TYPE = 'catalog-type-product';
    public const REFERENCE_ARTICLE_TYPE = 'catalog-type-article';
    public const REFERENCE_LOTTERY_TYPE = 'catalog-type-lottery';

    public function load(ObjectManager $manager): void
    {
        $productType = new CatalogType();
        $productType->setCode('product');
        $productType->setName('商品分类');
        $productType->setDescription('用于商品的分类管理');
        $productType->setEnabled(true);
        $manager->persist($productType);
        $this->addReference(self::REFERENCE_PRODUCT_TYPE, $productType);

        $articleType = new CatalogType();
        $articleType->setCode('article');
        $articleType->setName('文章分类');
        $articleType->setDescription('用于文章的分类管理');
        $articleType->setEnabled(true);
        $manager->persist($articleType);
        $this->addReference(self::REFERENCE_ARTICLE_TYPE, $articleType);

        $lotteryType = new CatalogType();
        $lotteryType->setCode('lottery');
        $lotteryType->setName('抽奖分类');
        $lotteryType->setDescription('用于抽奖活动的分类管理');
        $lotteryType->setEnabled(true);
        $manager->persist($lotteryType);
        $this->addReference(self::REFERENCE_LOTTERY_TYPE, $lotteryType);

        $manager->flush();
    }
}
