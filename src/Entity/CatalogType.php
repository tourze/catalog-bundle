<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\CatalogBundle\Repository\CatalogTypeRepository;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

#[ORM\Entity(repositoryClass: CatalogTypeRepository::class)]
#[ORM\Table(name: 'catalog_types', options: ['comment' => '分类类型表'])]
class CatalogType implements \Stringable
{
    use SnowflakeKeyAware;
    use TimestampableAware;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true, options: ['comment' => '类型编码'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[Assert\Regex(pattern: '/^[a-z0-9_]+$/', message: '编码只能包含小写字母、数字和下划线')]
    private string $code;

    #[ORM\Column(type: Types::STRING, length: 100, options: ['comment' => '类型名称'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '类型描述'])]
    #[Assert\Length(max: 65535)]
    private ?string $description = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否启用'])]
    #[Assert\NotNull]
    private bool $enabled = true;

    /**
     * @var Collection<int, Catalog>
     */
    #[ORM\OneToMany(targetEntity: Catalog::class, mappedBy: 'type')]
    private Collection $catalogs;

    public function __construct()
    {
        $this->catalogs = new ArrayCollection();
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * @return Collection<int, Catalog>
     */
    public function getCatalogs(): Collection
    {
        return $this->catalogs;
    }

    public function addCatalog(Catalog $catalog): void
    {
        if (!$this->catalogs->contains($catalog)) {
            $this->catalogs->add($catalog);
            $catalog->setType($this);
        }
    }

    public function removeCatalog(Catalog $catalog): void
    {
        if ($this->catalogs->removeElement($catalog)) {
            if ($catalog->getType() === $this) {
                $catalog->setType(null);
            }
        }
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
