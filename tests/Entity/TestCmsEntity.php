<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Tests\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * 测试用的 CmsEntity 替代类
 *
 * 用于解决测试环境中 CmsBundle\Entity\Entity 依赖问题
 */
#[ORM\Entity]
#[ORM\Table(name: 'cms_entity_test', options: ['comment' => '测试CMS实体表'])]
class TestCmsEntity implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }
}
