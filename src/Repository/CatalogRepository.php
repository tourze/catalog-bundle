<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\CatalogBundle\Entity\Catalog;
use Tourze\CatalogBundle\Entity\CatalogType;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<Catalog>
 */
#[AsRepository(entityClass: Catalog::class)]
class CatalogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Catalog::class);
    }

    /**
     * @return Catalog[]
     */
    public function findRootsByType(CatalogType $type): array
    {
        /** @var Catalog[] */
        return $this->createQueryBuilder('c')
            ->andWhere('c.type = :type')
            ->andWhere('c.parent IS NULL')
            ->setParameter('type', $type)
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Catalog[]
     */
    public function findEnabledRootsByType(CatalogType $type): array
    {
        /** @var Catalog[] */
        return $this->createQueryBuilder('c')
            ->andWhere('c.type = :type')
            ->andWhere('c.parent IS NULL')
            ->andWhere('c.enabled = :enabled')
            ->setParameter('type', $type)
            ->setParameter('enabled', true)
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Catalog[]
     */
    public function findChildrenOf(Catalog $parent): array
    {
        /** @var Catalog[] */
        return $this->createQueryBuilder('c')
            ->andWhere('c.parent = :parent')
            ->setParameter('parent', $parent)
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Catalog[]
     */
    public function findEnabledChildrenOf(Catalog $parent): array
    {
        /** @var Catalog[] */
        return $this->createQueryBuilder('c')
            ->andWhere('c.parent = :parent')
            ->andWhere('c.enabled = :enabled')
            ->setParameter('parent', $parent)
            ->setParameter('enabled', true)
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findOneByPath(string $path): ?Catalog
    {
        /** @var Catalog|null */
        return $this->createQueryBuilder('c')
            ->andWhere('c.path = :path')
            ->setParameter('path', $path)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * @return Catalog[]
     */
    public function findByType(CatalogType $type): array
    {
        /** @var Catalog[] */
        return $this->createQueryBuilder('c')
            ->andWhere('c.type = :type')
            ->setParameter('type', $type)
            ->orderBy('c.level', 'ASC')
            ->addOrderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Catalog[]
     */
    public function findAllDescendantsOf(Catalog $catalog): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.path LIKE :path')
            ->setParameter('path', $catalog->getPath() . '/%')
            ->orderBy('c.level', 'ASC')
            ->addOrderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
        ;

        /** @var Catalog[] */
        return $qb->getQuery()->getResult();
    }

    /**
     * @return Catalog[]
     */
    public function findSiblings(Catalog $catalog): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.id != :id')
            ->setParameter('id', $catalog->getId())
        ;

        if (null === $catalog->getParent()) {
            $qb->andWhere('c.parent IS NULL');
        } else {
            $qb->andWhere('c.parent = :parent')
                ->setParameter('parent', $catalog->getParent())
            ;
        }

        $qb->andWhere('c.type = :type')
            ->setParameter('type', $catalog->getType())
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
        ;

        /** @var Catalog[] */
        return $qb->getQuery()->getResult();
    }

    public function createTreeQueryBuilder(CatalogType $type, bool $onlyEnabled = false): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.type = :type')
            ->setParameter('type', $type)
            ->orderBy('c.level', 'ASC')
            ->addOrderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
        ;

        if ($onlyEnabled) {
            $qb->andWhere('c.enabled = :enabled')
                ->setParameter('enabled', true)
            ;
        }

        return $qb;
    }

    /**
     * @return array<int, array{id: int, name: string, level: int, parent_id: int|null}>
     */
    public function findTreeArrayByType(CatalogType $type, bool $onlyEnabled = false): array
    {
        $qb = $this->createTreeQueryBuilder($type, $onlyEnabled);

        $qb->select('c.id', 'c.name', 'c.level', 'IDENTITY(c.parent) AS parent_id', 'c.sortOrder', 'c.path');

        /** @var array<int, array{id: int, name: string, level: int, parent_id: int|null}> */
        return $qb->getQuery()->getArrayResult();
    }

    public function getMaxSortOrder(?Catalog $parent = null, ?CatalogType $type = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('MAX(c.sortOrder)')
        ;

        if (null === $parent) {
            $qb->andWhere('c.parent IS NULL');
        } else {
            $qb->andWhere('c.parent = :parent')
                ->setParameter('parent', $parent)
            ;
        }

        if (null !== $type) {
            $qb->andWhere('c.type = :type')
                ->setParameter('type', $type)
            ;
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return null !== $result ? (int) $result : 0;
    }

    public function save(Catalog $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Catalog $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
