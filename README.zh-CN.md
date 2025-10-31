# Catalog Bundle

[English](README.md) | [中文](README.zh-CN.md)

用于管理分类和目录功能的 Symfony Bundle。

## 安装

将此 Bundle 添加到您的 Symfony 项目中：

```bash
composer require tourze/catalog-bundle
```

## 快速开始

1. 在您的 `config/bundles.php` 中注册 Bundle：

```php
<?php

return [
    // ... 其他 bundles
    Tourze\CatalogBundle\CatalogBundle::class => ['all' => true],
];
```

2. 该 Bundle 为您的 Symfony 应用程序提供基本的分类管理基础结构。

## 功能特性

- 基础分类 Bundle 结构
- 兼容 Symfony 6.4+
- 支持 PHP 8.1+
- Doctrine ORM 集成
- 时间戳追踪支持

## 配置

该 Bundle 使用默认的 Symfony 配置。服务自动注册并启用自动装配。

## 使用方法

此 Bundle 为您的 Symfony 应用程序中的分类相关功能提供基础。它包括：

- 带有依赖管理的 Bundle 类
- 服务配置扩展
- 基本服务配置

```php
// 在您的应用程序中使用示例
use Tourze\CatalogBundle\CatalogBundle;

// Bundle 会自动注册和配置
```

## 测试

运行测试套件：

```bash
./vendor/bin/phpunit packages/catalog-bundle/tests
```

## 依赖

- PHP 8.1+
- Symfony 6.4+
- Doctrine ORM 3.0+
- tourze/doctrine-timestamp-bundle

## 许可证

此 Bundle 在 MIT 许可证下发布。详情请查看 [LICENSE](LICENSE) 文件。
