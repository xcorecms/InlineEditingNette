<?php

declare(strict_types=1);

namespace XcoreCMS\InlineEditingNette\Tests\DI;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver as SQLiteDriver;
use FreezyBee\PrependRoute\DI\PrependRouteExtension;
use Latte\Engine;
use Nette\Application\Routers\RouteList;
use Nette\Bridges\ApplicationLatte\LatteFactory;
use Nette\Caching\Storages\MemoryStorage;
use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\MissingServiceException;
use Nette\Http\IResponse;
use Nette\Http\Response;
use Nette\Security\User;
use Nette\Security\UserStorage;
use Tester\Assert;
use Tester\TestCase;
use XcoreCMS\InlineEditing\Model\Simple\ContentProvider;
use XcoreCMS\InlineEditingNette\DI\InlineEditingExtension;
use XcoreCMS\InlineEditingNette\Security\InlinePermissionChecker;

require __DIR__ . '/../bootstrap.php';

/**
 * @testCase
 */
class InlineEditingExtensionTest extends TestCase
{
    private static int $counter = 0;

    /**
     * @param array<string, mixed> $config
     */
    private function createContainer(array $config = []): Container
    {
        $compiler = new Compiler;
        $builder = $compiler->getContainerBuilder();
        $builder->parameters['wwwDir'] = TEMP_DIR;

        $compiler->addExtension('prependRoute', new PrependRouteExtension);
        $compiler->addExtension('inline', new InlineEditingExtension);

        // services normally provided by a full Nette application
        $builder->addDefinition('cache.storage')
            ->setFactory(MemoryStorage::class);
        $builder->addImportedDefinition('doctrine.default.connection')
            ->setType(Connection::class);
        $builder->addFactoryDefinition('latte.latteFactory')
            ->setImplement(LatteFactory::class)
            ->getResultDefinition()
            ->setFactory(Engine::class);
        $builder->addDefinition('http.response')
            ->setType(IResponse::class)
            ->setFactory(Response::class);
        $builder->addDefinition('router')
            ->setFactory(RouteList::class);
        $builder->addImportedDefinition('security.userStorage')
            ->setType(UserStorage::class);
        $builder->addDefinition('security.user')
            ->setFactory(User::class);

        $compiler->addConfig([
            'inline' => $config + ['install' => ['assets' => false, 'database' => false]],
        ]);

        $className = 'TestContainer' . self::$counter++;
        eval($compiler->setClassName($className)->compile());

        /** @var Container $container */
        $container = new $className;
        $container->addService('doctrine.default.connection', new Connection([], new SQLiteDriver));
        return $container;
    }

    public function testServicesAreRegistered(): void
    {
        $container = $this->createContainer();

        Assert::true($container->hasService('inline.router'));
        Assert::true($container->hasService('inline.cache'));
        Assert::true($container->hasService('inline.persistenceLayer'));
        Assert::true($container->hasService('inline.contentProvider'));
        Assert::true($container->hasService('inline.permissionChecker'));

        Assert::type(InlinePermissionChecker::class, $container->getService('inline.permissionChecker'));
        Assert::type(InlinePermissionChecker::class, $container->getByType(InlinePermissionChecker::class));
    }

    public function testContentProviderIsCreatable(): void
    {
        $container = $this->createContainer();

        Assert::type(ContentProvider::class, $container->getService('inline.contentProvider'));
    }

    public function testSimpleCheckerRegisteredOnlyWithAllowedRoles(): void
    {
        Assert::false($this->createContainer()->hasService('inline.simpleChecker'));
        Assert::true($this->createContainer(['allowedRoles' => ['admin']])->hasService('inline.simpleChecker'));
    }

    public function testEntityPersisterNotRegisteredByDefault(): void
    {
        // entityMode: true requires doctrine/orm which is not a dev dependency
        Assert::false($this->createContainer()->hasService('inline.entityPersister'));
    }

    public function testMissingPrependRouteExtensionThrows(): void
    {
        Assert::exception(function (): void {
            $compiler = new Compiler;
            $compiler->addExtension('inline', new InlineEditingExtension);
            $compiler->addConfig([
                'inline' => ['install' => ['assets' => false, 'database' => false]],
            ]);
            $compiler->compile();
        }, MissingServiceException::class);
    }
}

(new InlineEditingExtensionTest)->run();
