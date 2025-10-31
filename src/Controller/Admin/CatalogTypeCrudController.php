<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Tourze\CatalogBundle\Entity\CatalogType;

/**
 * 分类类型管理控制器
 */
#[AdminCrud(routePath: '/catalog/type', routeName: 'catalog_type')]
final class CatalogTypeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CatalogType::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('分类类型')
            ->setEntityLabelInPlural('分类类型')
            ->setPageTitle('index', '分类类型管理')
            ->setPageTitle('new', '创建分类类型')
            ->setPageTitle('edit', '编辑分类类型')
            ->setPageTitle('detail', '分类类型详情')
            ->setHelp('index', '管理系统中的分类类型，如商品分类、文章分类等')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['code', 'name', 'description'])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->setMaxLength(9999)
            ->hideOnForm()
        ;

        yield TextField::new('code', '类型编码')
            ->setHelp('唯一的类型标识符，只能包含小写字母、数字和下划线')
            ->setMaxLength(50)
            ->setRequired(true)
        ;

        yield TextField::new('name', '类型名称')
            ->setHelp('分类类型的显示名称')
            ->setMaxLength(100)
            ->setRequired(true)
        ;

        yield TextareaField::new('description', '类型描述')
            ->setHelp('对此分类类型的详细说明')
            ->setMaxLength(65535)
            ->hideOnIndex()
        ;

        yield BooleanField::new('enabled', '是否启用')
            ->setHelp('控制此分类类型是否可用')
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
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('code', '类型编码'))
            ->add(TextFilter::new('name', '类型名称'))
            ->add(BooleanFilter::new('enabled', '是否启用'))
        ;
    }
}
