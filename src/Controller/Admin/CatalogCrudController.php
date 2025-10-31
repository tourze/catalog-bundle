<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Tourze\CatalogBundle\Entity\Catalog;
use Tourze\CatalogBundle\Entity\CatalogType;
use Tourze\EasyAdminTreeSelectFieldBundle\DataProvider\EntityTreeDataProvider;
use Tourze\EasyAdminTreeSelectFieldBundle\Field\TreeSelectMultiField;
use Tourze\EasyAdminTreeSelectFieldBundle\Field\TreeSelectSingleField;
use Tourze\FileStorageBundle\Field\ImageGalleryField;

/**
 * 分类管理控制器
 */
#[AdminCrud(routePath: '/catalog/catalog', routeName: 'catalog_catalog')]
final class CatalogCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Catalog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('分类')
            ->setEntityLabelInPlural('分类')
            ->setPageTitle('index', '分类管理')
            ->setPageTitle('new', '创建分类')
            ->setPageTitle('edit', '编辑分类')
            ->setPageTitle('detail', '分类详情')
            ->setHelp('index', '管理具有树形结构的分类信息，支持多级嵌套分类')
            ->setDefaultSort(['type' => 'ASC', 'level' => 'ASC', 'sortOrder' => 'ASC'])
            ->setSearchFields(['name', 'description', 'path'])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->setMaxLength(9999)
            ->hideOnForm()
        ;

        yield AssociationField::new('type', '分类类型')
            ->setHelp('选择此分类所属的类型')
            ->setRequired(true)
            ->autocomplete()
            ->formatValue(function ($value) {
                return $value instanceof CatalogType ? $value->getName() : '';
            })
        ;

        yield TextField::new('name', '分类名称')
            ->setHelp('分类的显示名称')
            ->setMaxLength(100)
            ->setRequired(true)
        ;

        yield ImageGalleryField::new('thumb', '封面图片')
            ->setHelp('分类封面图片（可选）')
        ;

        yield AssociationField::new('parent', '上级分类')
            ->setHelp('选择上级分类，留空则为顶级分类')
            ->autocomplete()
            ->hideOnForm()
            ->formatValue(function ($value) {
                return $value instanceof Catalog ? $this->formatCatalogName($value) : '顶级分类';
            })
        ;

        yield TreeSelectSingleField::new('parent', '上级分类')
            ->setDataProvider(new EntityTreeDataProvider(
                $this->entityManager,
                Catalog::class,
                [
                    'id_field' => 'id',
                    'label_field' => 'name',
                    'parent_field' => 'parent',
                    'order_by' => ['sortOrder' => 'ASC', 'name' => 'ASC'],
                ]
            ))
            ->setEntityClass(Catalog::class)
            ->setSearchable(true)
            ->setExpandedLevel(2)
            ->setPlaceholder('请选择分类')
            ->setHelp('选择上级分类，留空则为顶级分类')
            ->hideOnIndex()
        ;

        yield TextareaField::new('description', '分类描述')
            ->setHelp('对此分类的详细说明')
            ->setMaxLength(65535)
            ->hideOnIndex()
        ;

        yield IntegerField::new('sortOrder', '排序值')
            ->setHelp('数值越小排序越靠前')
            ->hideOnIndex()
        ;

        //        yield IntegerField::new('level', '层级深度')
        //            ->setHelp('系统自动维护的层级深度，0为顶级')
        //            ->hideOnForm()
        //        ;

        yield TextField::new('path', '完整路径')
            ->setHelp('系统自动维护的完整路径')
            ->hideOnForm()
            ->hideOnIndex()
        ;

        yield BooleanField::new('enabled', '是否启用')
            ->setHelp('控制此分类是否可用')
        ;

        yield TextareaField::new('metadata', '元数据')
            ->setHelp('存储额外的JSON格式数据（JSON格式）')
            ->hideOnIndex()
            ->hideOnForm()
        ;

        yield DateTimeField::new('createTime', '创建时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnForm()
        ;

        yield DateTimeField::new('updateTime', '更新时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnForm()
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('type', '分类类型'))
            ->add(EntityFilter::new('parent', '上级分类'))
            ->add(TextFilter::new('name', '分类名称'))
            //            ->add(NumericFilter::new('level', '层级深度'))
            ->add(BooleanFilter::new('enabled', '是否启用'))
        ;
    }

    /**
     * 格式化分类名称显示
     */
    private function formatCatalogName(Catalog $catalog): string
    {
        $ancestors = $catalog->getAncestors();
        $path = [];

        foreach ($ancestors as $ancestor) {
            $path[] = $ancestor->getName();
        }
        $path[] = $catalog->getName();

        return implode(' > ', $path) . ' ( ' . $catalog->getLevel() + 2 . '级)';
    }
}
