<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\CatalogBundle\Entity\Catalog;
use Tourze\CatalogBundle\Entity\CatalogType;

class CatalogFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $productType = $this->getReference(CatalogTypeFixtures::REFERENCE_PRODUCT_TYPE, CatalogType::class);
        assert($productType instanceof CatalogType);

        $electronics = new Catalog();
        $electronics->setType($productType);
        $electronics->setName('电子产品');
        $electronics->setDescription('各类电子产品分类');
        $electronics->setSortOrder(1);
        $electronics->setEnabled(true);
        $manager->persist($electronics);

        $phones = new Catalog();
        $phones->setType($productType);
        $phones->setName('手机');
        $phones->setDescription('智能手机及配件');
        $phones->setParent($electronics);
        $phones->setSortOrder(1);
        $phones->setEnabled(true);
        $manager->persist($phones);

        $computers = new Catalog();
        $computers->setType($productType);
        $computers->setName('电脑');
        $computers->setDescription('台式机、笔记本电脑');
        $computers->setParent($electronics);
        $computers->setSortOrder(2);
        $computers->setEnabled(true);
        $manager->persist($computers);

        $laptops = new Catalog();
        $laptops->setType($productType);
        $laptops->setName('笔记本电脑');
        $laptops->setDescription('各品牌笔记本电脑');
        $laptops->setParent($computers);
        $laptops->setSortOrder(1);
        $laptops->setEnabled(true);
        $manager->persist($laptops);

        $articleType = $this->getReference(CatalogTypeFixtures::REFERENCE_ARTICLE_TYPE, CatalogType::class);
        assert($articleType instanceof CatalogType);

        $techArticles = new Catalog();
        $techArticles->setType($articleType);
        $techArticles->setName('技术文章');
        $techArticles->setDescription('技术相关文章');
        $techArticles->setSortOrder(1);
        $techArticles->setEnabled(true);
        $manager->persist($techArticles);

        $phpArticles = new Catalog();
        $phpArticles->setType($articleType);
        $phpArticles->setName('PHP开发');
        $phpArticles->setDescription('PHP开发相关文章');
        $phpArticles->setParent($techArticles);
        $phpArticles->setSortOrder(1);
        $phpArticles->setEnabled(true);
        $manager->persist($phpArticles);

        $lotteryType = $this->getReference(CatalogTypeFixtures::REFERENCE_LOTTERY_TYPE, CatalogType::class);
        assert($lotteryType instanceof CatalogType);

        $dailyLottery = new Catalog();
        $dailyLottery->setType($lotteryType);
        $dailyLottery->setName('每日抽奖');
        $dailyLottery->setDescription('每日抽奖活动');
        $dailyLottery->setSortOrder(1);
        $dailyLottery->setEnabled(true);
        $dailyLottery->setMetadata([
            'max_daily_draws' => 3,
            'points_required' => 10,
        ]);
        $manager->persist($dailyLottery);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            CatalogTypeFixtures::class,
        ];
    }
}
