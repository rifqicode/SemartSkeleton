<?php

declare(strict_types=1);

namespace KejawenLab\Semart\Skeleton\Repository;

use Doctrine\Common\Persistence\ManagerRegistry;
use KejawenLab\Semart\Skeleton\Entity\Group;
use KejawenLab\Semart\Skeleton\Entity\Menu;
use KejawenLab\Semart\Skeleton\Entity\Role;
use PHLAK\Twine\Str;

/**
 * @author Muhamad Surya Iksanudin <surya.iksanudin@gmail.com>
 */
class RoleRepository extends Repository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    public function findOneBy(array $criteria, array $orderBy = null)
    {
        $key = md5(sprintf('%s:%s:%s:%s', __CLASS__, __METHOD__, serialize($criteria), serialize($orderBy)));

        $object = $this->getItem($key);
        if (!$object) {
            $object = parent::findOneBy($criteria, $orderBy);

            $this->cache($key, $object);
        }

        return $object;
    }

    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        $key = md5(sprintf('%s:%s:%s:%s:%s:%s', __CLASS__, __METHOD__, serialize($criteria), serialize($orderBy), $limit, $offset));

        $objects = $this->getItem($key);
        if (!$objects) {
            $objects = parent::findBy($criteria, $orderBy, $limit, $offset);

            $this->cache($key, $objects);
        }

        return $objects;
    }

    public function findRole(Group $group, Menu $menu): ?Role
    {
        $key = md5(sprintf('%s:%s:%s:%s', __CLASS__, __METHOD__, serialize($group), serialize($menu)));

        $role = $this->getItem($key);
        if (!$role) {
            $queryBuilder = $this->createQueryBuilder('o');
            $queryBuilder->leftJoin('o.group', 'g');
            $queryBuilder->leftJoin('o.menu', 'm');
            $queryBuilder->andWhere($queryBuilder->expr()->eq('g.id', $queryBuilder->expr()->literal($group->getId())));
            $queryBuilder->andWhere($queryBuilder->expr()->eq('m.id', $queryBuilder->expr()->literal($menu->getId())));

            $role = $queryBuilder->getQuery()->getOneOrNullResult();

            $this->cache($key, $role);
        }

        return $role;
    }

    public function findParentMenuByGroup(Group $group): array
    {
        $key = md5(sprintf('%s:%s:%s', __CLASS__, __METHOD__, serialize($group)));

        $results = $this->getItem($key);
        if (!$results) {
            $queryBuilder = $this->createQueryBuilder('o');
            $queryBuilder->select('o');
            $queryBuilder->leftJoin('o.group', 'g');
            $queryBuilder->leftJoin('o.menu', 'm');
            $queryBuilder->leftJoin('m.parent', 'p');
            $queryBuilder->andWhere($queryBuilder->expr()->eq('g.id', $queryBuilder->expr()->literal($group->getId())));
            $queryBuilder->andWhere($queryBuilder->expr()->eq('o.viewable', $queryBuilder->expr()->literal(true)));
            $queryBuilder->andWhere($queryBuilder->expr()->eq('m.showable', $queryBuilder->expr()->literal(true)));
            $queryBuilder->andWhere($queryBuilder->expr()->isNull('p'));
            $queryBuilder->addOrderBy('m.menuOrder', 'ASC');
            $queryBuilder->andWhere($queryBuilder->expr()->isNull('m.deletedAt'));
            $queryBuilder->andWhere($queryBuilder->expr()->isNull('g.deletedAt'));
            $queryBuilder->andWhere($queryBuilder->expr()->isNull('p.deletedAt'));

            $results = $this->filterMenu($queryBuilder->getQuery()->getResult());

            $this->cache($key, $results);
        }

        return $results;
    }

    public function findChildMenuByGroupAndMenu(Group $group, Menu $menu): array
    {
        $key = md5(sprintf('%s:%s:%s:%s', __CLASS__, __METHOD__, serialize($group), serialize($menu)));

        $results = $this->getItem($key);
        if (!$results) {
            $queryBuilder = $this->createQueryBuilder('o');
            $queryBuilder->select('o');
            $queryBuilder->leftJoin('o.group', 'g');
            $queryBuilder->leftJoin('o.menu', 'm');
            $queryBuilder->leftJoin('m.parent', 'p');
            $queryBuilder->andWhere($queryBuilder->expr()->eq('o.group', $queryBuilder->expr()->literal($group->getId())));
            $queryBuilder->andWhere($queryBuilder->expr()->eq('o.viewable', $queryBuilder->expr()->literal(true)));
            $queryBuilder->andWhere($queryBuilder->expr()->eq('m.showable', $queryBuilder->expr()->literal(true)));
            $queryBuilder->andWhere($queryBuilder->expr()->eq('p', $queryBuilder->expr()->literal($menu->getId())));
            $queryBuilder->addOrderBy('m.menuOrder', 'ASC');
            $queryBuilder->andWhere($queryBuilder->expr()->isNull('m.deletedAt'));
            $queryBuilder->andWhere($queryBuilder->expr()->isNull('g.deletedAt'));
            $queryBuilder->andWhere($queryBuilder->expr()->isNull('p.deletedAt'));

            $results = $this->filterMenu($queryBuilder->getQuery()->getResult());

            $this->cache($key, $results);
        }

        return $results;
    }

    public function findRolesByGroup(Group $group, string $queryString = ''): ?array
    {
        $key = md5(sprintf('%s:%s:%s:%s', __CLASS__, __METHOD__, serialize($group), $queryString));

        $results = $this->getItem($key);
        if (!$results) {
            $queryBuilder = $this->createQueryBuilder('o');
            $queryBuilder->join('o.group', 'g');
            $queryBuilder->join('o.menu', 'm');
            $queryBuilder->leftJoin('m.parent', 'p');
            $queryBuilder->orWhere($queryBuilder->expr()->like('m.name', $queryBuilder->expr()->literal(sprintf('%%%s%%', Str::make($queryString)->uppercase()))));
            $queryBuilder->andWhere($queryBuilder->expr()->eq('o.group', $queryBuilder->expr()->literal($group->getId())));
            $queryBuilder->addOrderBy('p.name', 'ASC');
            $queryBuilder->addOrderBy('m.menuOrder', 'ASC');

            $results = $queryBuilder->getQuery()->getResult();

            $this->cache($key, $results);
        }

        return $results;
    }

    public function persist(Role $role): void
    {
        $this->_em->persist($role);
    }

    public function commit(): void
    {
        $this->_em->flush();
    }

    private function filterMenu(array $roles): array
    {
        $menus = [];
        /** @var Role $role */
        foreach ($roles as $role) {
            $menus[] = $role->getMenu();
        }

        return $menus;
    }
}
