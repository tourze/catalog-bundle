<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Tourze\CatalogBundle\Controller\Admin\CatalogCrudController;
use Tourze\CatalogBundle\Entity\Catalog;
use Tourze\CatalogBundle\Entity\CatalogType;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(CatalogCrudController::class)]
#[RunTestsInSeparateProcesses]
final class CatalogCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testGetEntityFqcn(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsAdmin($client, 'admin@test.com', 'password');

        $client->request('GET', '/admin');
        $this->assertSame(Catalog::class, CatalogCrudController::getEntityFqcn());
    }

    public function testConfigureFields(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsAdmin($client, 'admin@test.com', 'password');

        $client->request('GET', '/admin');
        $entityManager = self::getEntityManager();
        $controller = new CatalogCrudController($entityManager);

        $fields = iterator_to_array($controller->configureFields('index'));
        $this->assertNotEmpty($fields);
    }

    public function testUnauthenticatedAccessSecurity(): void
    {
        $client = self::createClientWithDatabase();

        try {
            $client->request('GET', '/admin/catalog/catalog');
            $response = $client->getResponse();
            $this->assertTrue(
                $response->isRedirection() || $response->getStatusCode() >= 400,
                'Unauthenticated access should be denied with redirect or error status'
            );
        } catch (AccessDeniedException $e) {
            $this->assertStringContainsString('Access Denied', $e->getMessage());
        }
    }

    public function testAuthenticatedIndexAccess(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsAdmin($client, 'admin@test.com', 'password');

        $client->request('GET', '/admin/catalog/catalog');
        $response = $client->getResponse();
        $this->assertTrue(
            $response->isSuccessful(),
            'Response should be successful for authenticated access'
        );
    }

    public function testSearchParameters(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsAdmin($client, 'admin@test.com', 'password');

        $client->request('GET', '/admin/catalog/catalog', ['query' => 'test']);
        $response = $client->getResponse();
        $this->assertTrue(
            $response->isSuccessful(),
            'Response should be successful for search parameters'
        );
    }

    public function testPageAccessibility(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsAdmin($client, 'admin@test.com', 'password');

        $client->request('GET', '/admin/catalog/catalog/new');
        $response = $client->getResponse();
        $this->assertTrue(
            $response->isSuccessful(),
            'Response should be successful for new page access'
        );

        $content = $response->getContent();
        $this->assertIsString($content, 'Response content should be a string');
        $this->assertStringContainsString('catalog', $content);
    }

    public function testValidationErrors(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsAdmin($client, 'admin@test.com', 'password');

        // 先创建一个 CatalogType 以满足外键约束
        $entityManager = self::getEntityManager();
        $catalogType = new CatalogType();
        $catalogType->setCode('test_type');
        $catalogType->setName('Test Type');
        $catalogType->setEnabled(true);

        $entityManager->persist($catalogType);
        $entityManager->flush();

        $client->request('POST', '/admin/catalog/catalog/new', [
            'Catalog' => [
                'name' => '',
                'type' => $catalogType->getId(),
                'sortOrder' => 0,
                'enabled' => true,
            ],
        ]);

        $response = $client->getResponse();
        $this->assertSame(422, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertIsString($content, 'Response content should be a string');
        $this->assertStringContainsString('should not be blank', $content);
    }

    public function testRequiredFieldValidation(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsAdmin($client, 'admin@test.com', 'password');

        // 先创建一个 CatalogType 以满足外键约束
        $entityManager = self::getEntityManager();
        $catalogType = new CatalogType();
        $catalogType->setCode('test_type_alt');
        $catalogType->setName('Test Type Alt');
        $catalogType->setEnabled(true);

        $entityManager->persist($catalogType);
        $entityManager->flush();

        $client->request('POST', '/admin/catalog/catalog/new', [
            'Catalog' => [
                'name' => '',
                'type' => $catalogType->getId(),
                'sortOrder' => 0,
                'enabled' => true,
            ],
        ]);

        $response = $client->getResponse();
        $this->assertSame(422, $response->getStatusCode(), 'Response status code should be 422 for validation errors');
        $content = $response->getContent();
        $this->assertIsString($content, 'Response content should be a string');
        $this->assertStringContainsString('not be blank', $content);
    }

    public function testNameFieldValidation(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsAdmin($client, 'admin@test.com', 'password');

        // 先创建一个 CatalogType 以满足外键约束
        $entityManager = self::getEntityManager();
        $catalogType = new CatalogType();
        $catalogType->setCode('test_type_2');
        $catalogType->setName('Test Type 2');
        $catalogType->setEnabled(true);

        $entityManager->persist($catalogType);
        $entityManager->flush();

        $longName = str_repeat('a', 101);
        $client->request('POST', '/admin/catalog/catalog/new', [
            'Catalog' => [
                'name' => $longName,
                'type' => $catalogType->getId(),
                'sortOrder' => 0,
                'enabled' => true,
            ],
        ]);

        $response = $client->getResponse();
        $this->assertSame(422, $response->getStatusCode(), 'Response status code should be 422 for validation errors');
    }

    public function testFormAccessibility(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsAdmin($client, 'admin@test.com', 'password');

        // 测试可以访问新建表单页面
        $client->request('GET', '/admin/catalog/catalog/new');
        $response = $client->getResponse();

        $this->assertTrue(
            $response->isSuccessful(),
            'New catalog form should be accessible'
        );

        $content = $response->getContent();
        $this->assertIsString($content);

        // 验证表单不包含 slug 字段（因为我们移除了它）
        $this->assertStringNotContainsString('Catalog[slug]', $content);

        // 验证包含必要的字段
        $this->assertStringContainsString('Catalog[name]', $content);
        $this->assertStringContainsString('Catalog[type]', $content);
        $this->assertStringContainsString('Catalog[sortOrder]', $content);
        $this->assertStringContainsString('Catalog[enabled]', $content);
    }

    /**
     * 返回 CatalogCrudController 的实例
     */
    // @phpstan-ignore-next-line missingType.generics
    protected function getControllerService(): AbstractCrudController
    {
        return self::getService(CatalogCrudController::class);
    }

    /**
     * 返回索引页面显示的表头
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield '分类类型' => ['分类类型'];
        yield '分类名称' => ['分类名称'];
        yield '封面图片' => ['封面图片'];
        yield '上级分类' => ['上级分类'];
        yield '是否启用' => ['是否启用'];
        yield '创建时间' => ['创建时间'];
        yield '更新时间' => ['更新时间'];
    }

    /**
     * 返回新建页面显示的字段名称
     *
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield '分类类型' => ['type'];
        yield '分类名称' => ['name'];
        yield '封面图片' => ['thumb'];
        yield '上级分类' => ['parent'];
        yield '分类描述' => ['description'];
        yield '排序值' => ['sortOrder'];
        yield '是否启用' => ['enabled'];
    }

    /**
     * 返回编辑页面显示的字段名称
     *
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield '分类类型' => ['type'];
        yield '分类名称' => ['name'];
        yield '封面图片' => ['thumb'];
        yield '上级分类' => ['parent'];
        yield '分类描述' => ['description'];
        yield '排序值' => ['sortOrder'];
        yield '是否启用' => ['enabled'];
    }
}
