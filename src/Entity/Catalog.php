<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\CatalogBundle\Repository\CatalogRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

#[ORM\Entity(repositoryClass: CatalogRepository::class)]
#[ORM\Table(name: 'catalogs', options: ['comment' => '分类表'])]
#[ORM\Index(columns: ['type_id', 'parent_id'], name: 'catalogs_idx_catalog_type_parent')]
#[ORM\Index(columns: ['parent_id', 'type_id'], name: 'catalogs_idx_catalog_parent_type')]
class Catalog implements \Stringable
{
    use SnowflakeKeyAware;
    use TimestampableAware;

    #[ORM\ManyToOne(targetEntity: CatalogType::class, cascade: ['persist'], inversedBy: 'catalogs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?CatalogType $type = null;

    #[ORM\Column(type: Types::STRING, length: 100, options: ['comment' => '分类名称'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '分类描述'])]
    #[Assert\Length(max: 65535)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?self $parent = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(value: ['sortOrder' => 'ASC'])]
    private Collection $children;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '排序值'])]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    #[IndexColumn]
    private int $sortOrder = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '层级深度'])]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    private int $level = 0;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '完整路径'])]
    #[Assert\Length(max: 255)]
    private ?string $path = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否启用'])]
    #[Assert\NotNull]
    private bool $enabled = true;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '元数据'])]
    #[Assert\Type(type: 'array')]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '封面图片'])]
    #[Assert\Length(max: 255)]
    private ?string $thumb = null;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    public function getType(): ?CatalogType
    {
        return $this->type;
    }

    public function setType(?CatalogType $type): void
    {
        $this->type = $type;
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

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): void
    {
        if ($this->parent !== $parent) {
            if (null !== $this->parent) {
                $this->parent->removeChild($this);
            }

            $this->parent = $parent;

            if (null !== $parent) {
                $parent->addChild($this);
            }
        }

        $this->updateLevel();
        $this->updatePath();
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): void
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            if ($child->getParent() !== $this) {
                $child->setParent($this);
            }
        }
    }

    public function removeChild(self $child): void
    {
        if ($this->children->removeElement($child)) {
            if ($child->getParent() === $this) {
                $child->parent = null;
                $child->updateLevel();
                $child->updatePath();
            }
        }
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): void
    {
        $this->level = $level;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): void
    {
        $this->path = $path;
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
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function setMetadata(?array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function getThumb(): ?string
    {
        return $this->thumb;
    }

    public function setThumb(?string $thumb): void
    {
        $this->thumb = $thumb;
    }

    /**
     * @return array<string>
     */
    public function getAncestorIds(): array
    {
        $ids = [];
        $parent = $this->getParent();

        while (null !== $parent) {
            $parentId = $parent->getId();
            if (null !== $parentId) {
                $ids[] = $parentId;
            }
            $parent = $parent->getParent();
        }

        return array_reverse($ids);
    }

    /**
     * @return array<self>
     */
    public function getAncestors(): array
    {
        $ancestors = [];
        $parent = $this->getParent();

        while (null !== $parent) {
            $ancestors[] = $parent;
            $parent = $parent->getParent();
        }

        return array_reverse($ancestors);
    }

    public function isAncestorOf(self $catalog): bool
    {
        $parent = $catalog->getParent();

        while (null !== $parent) {
            if ($parent === $this) {
                return true;
            }
            $parent = $parent->getParent();
        }

        return false;
    }

    public function isDescendantOf(self $catalog): bool
    {
        return $catalog->isAncestorOf($this);
    }

    private function updateLevel(): void
    {
        if (null === $this->parent) {
            $this->level = 0;
        } else {
            $this->level = $this->parent->getLevel() + 1;
        }

        foreach ($this->children as $child) {
            $child->updateLevel();
        }
    }

    private function updatePath(): void
    {
        $id = $this->getId();
        if (null === $id) {
            return;
        }

        if (null === $this->parent) {
            $this->path = $id;
        } else {
            $parentPath = $this->parent->getPath();
            if (null === $parentPath) {
                $this->parent->updatePath();
                $parentPath = $this->parent->getPath();
            }
            $this->path = $parentPath . '/' . $id;
        }

        foreach ($this->children as $child) {
            $child->updatePath();
        }
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
