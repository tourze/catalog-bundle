<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Service;

use Tourze\CatalogBundle\Entity\Catalog;
use Tourze\CatalogBundle\Entity\CatalogType;
use Tourze\CatalogBundle\Repository\CatalogRepository;
use Tourze\CatalogBundle\Repository\CatalogTypeRepository;

readonly class CatalogService
{
    public function __construct(
        private CatalogRepository $catalogRepository,
        private CatalogTypeRepository $catalogTypeRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, 'ASC'|'DESC'>|null $orderBy
     * @return array<Catalog>
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        return $this->catalogRepository->findBy($criteria, $orderBy, $limit, $offset);
    }

    public function find(mixed $id): ?Catalog
    {
        return $this->catalogRepository->find($id);
    }

    /**
     * @return array<Catalog>
     */
    public function findAll(): array
    {
        return $this->catalogRepository->findAll();
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, 'ASC'|'DESC'>|null $orderBy
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?Catalog
    {
        return $this->catalogRepository->findOneBy($criteria, $orderBy);
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, 'ASC'|'DESC'>|null $orderBy
     */
    public function findCatalogTypeOneBy(array $criteria, ?array $orderBy = null): ?CatalogType
    {
        return $this->catalogTypeRepository->findOneBy($criteria, $orderBy);
    }

    /**
     * @param array<string> $ids
     * @return array<Catalog>
     */
    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->catalogRepository->findBy(['id' => $ids]);
    }
}
