<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\CatalogBundle\Entity\CatalogType;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<CatalogType>
 */
#[AsRepository(entityClass: CatalogType::class)]
class CatalogTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CatalogType::class);
    }

    /**
     * @return CatalogType[]
     */
    public function findEnabledTypes(): array
    {
        /** @var CatalogType[] */
        return $this->createQueryBuilder('ct')
            ->andWhere('ct.enabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('ct.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findOneByCode(string $code): ?CatalogType
    {
        /** @var CatalogType|null */
        return $this->createQueryBuilder('ct')
            ->andWhere('ct.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * @param array<string> $codes
     * @return CatalogType[]
     */
    public function findByCodesIn(array $codes): array
    {
        /** @var CatalogType[] */
        return $this->createQueryBuilder('ct')
            ->andWhere('ct.code IN (:codes)')
            ->setParameter('codes', $codes)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<string, CatalogType>
     */
    public function findAllIndexedByCode(): array
    {
        $types = $this->findAll();
        $indexed = [];

        foreach ($types as $type) {
            $indexed[$type->getCode()] = $type;
        }

        return $indexed;
    }

    public function save(CatalogType $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CatalogType $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
