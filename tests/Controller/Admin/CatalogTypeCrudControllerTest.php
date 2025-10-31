<?php

declare(strict_types=1);

namespace Tourze\CatalogBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Tourze\CatalogBundle\Controller\Admin\CatalogTypeCrudController;
use Tourze\CatalogBundle\Entity\CatalogType;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(CatalogTypeCrudController::class)]
#[RunTestsInSeparateProcesses]
final class CatalogTypeCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testGetEntityFqcn(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsAdmin($client, 'admin@test.com', 'password');

        $client->request('GET', '/admin');
        $this->assertSame(CatalogType::class, CatalogTypeCrudController::getEntityFqcn());
    }

    public function testConfigureFields(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsAdmin($client, 'admin@test.com', 'password');

        $client->request('GET', '/admin');
        $controller = new CatalogTypeCrudController();

        $fields = iterator_to_array($controller->configureFields('index'));
        $this->assertNotEmpty($fields);
    }

    public function testUnauthenticatedAccessSecurity(): void
    {
        $client = self::createClientWithDatabase();

        try {
            $client->request('GET', '/admin/catalog/type');
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

        $client->request('GET', '/admin/catalog/type');
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

        $client->request('GET', '/admin/catalog/type', ['query' => 'test']);
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

        $client->request('GET', '/admin/catalog/type/new');
        $response = $client->getResponse();
        $this->assertTrue(
            $response->isSuccessful(),
            'Response should be successful for new page access'
        );

        $content = $response->getContent();
        $this->assertIsString($content, 'Response content should be a string');
        $this->assertStringContainsString('type', $content);
    }

    public function testValidationErrors(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsAdmin($client, 'admin@test.com', 'password');

        $client->request('POST', '/admin/catalog/type/new', [
            'CatalogType' => [
                'code' => '',
                'name' => '',
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

        $client->request('POST', '/admin/catalog/type/new', [
            'CatalogType' => [
                'code' => '',
                'name' => '',
                'enabled' => true,
            ],
        ]);

        $response = $client->getResponse();
        $this->assertSame(422, $response->getStatusCode(), 'Response status code should be 422 for validation errors');
        $content = $response->getContent();
        $this->assertIsString($content, 'Response content should be a string');
        $this->assertStringContainsString('not be blank', $content);
    }

    public function testCodeFieldValidation(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsAdmin($client, 'admin@test.com', 'password');

        $client->request('POST', '/admin/catalog/type/new', [
            'CatalogType' => [
                'code' => 'Invalid-Code-With-Dashes',
                'name' => 'Valid Name',
                'enabled' => true,
            ],
        ]);

        $response = $client->getResponse();
        $this->assertSame(422, $response->getStatusCode(), 'Response status code should be 422 for validation errors');
    }

    public function testNameFieldValidation(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsAdmin($client, 'admin@test.com', 'password');

        $longName = str_repeat('a', 101);
        $client->request('POST', '/admin/catalog/type/new', [
            'CatalogType' => [
                'code' => 'valid_code',
                'name' => $longName,
                'enabled' => true,
            ],
        ]);

        $response = $client->getResponse();
        $this->assertSame(422, $response->getStatusCode(), 'Response status code should be 422 for validation errors');
    }

    /**
     * 返回 CatalogTypeCrudController 的实例
     */
    // @phpstan-ignore-next-line missingType.generics
    protected function getControllerService(): AbstractCrudController
    {
        return self::getService(CatalogTypeCrudController::class);
    }

    /**
     * 返回索引页面显示的表头
     *
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield '类型编码' => ['类型编码'];
        yield '类型名称' => ['类型名称'];
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
        yield '类型编码' => ['code'];
        yield '类型名称' => ['name'];
        yield '类型描述' => ['description'];
        yield '是否启用' => ['enabled'];
    }

    /**
     * 返回编辑页面显示的字段名称
     *
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield '类型编码' => ['code'];
        yield '类型名称' => ['name'];
        yield '类型描述' => ['description'];
        yield '是否启用' => ['enabled'];
    }
}
